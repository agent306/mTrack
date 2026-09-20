import net from 'node:net';

export type TrackerPacket = {
  imei: string;
  protocol: number;
  packet_hex: string;
  iccid?: string;
  location?: { timestamp: string; latitude: number; longitude: number; speedKmh: number; heading: number };
};

// GT06/Jimi frames use CRC-16/X25 over length through serial number.
export function crc16(bytes: Buffer): number {
  let crc = 0xffff;
  for (const byte of bytes) {
    crc ^= byte;
    for (let bit = 0; bit < 8; bit++) crc = crc & 1 ? (crc >>> 1) ^ 0x8408 : crc >>> 1;
  }
  return (~crc) & 0xffff;
}

export function frame(protocol: number, serial: number, payload = Buffer.alloc(0), extended = false): Buffer {
  const prefix = extended ? 4 : 3;
  const result = Buffer.alloc(prefix + 1 + payload.length + 6);
  result.writeUInt16BE(extended ? 0x7979 : 0x7878, 0);
  if (extended) result.writeUInt16BE(payload.length + 5, 2);
  else result[2] = payload.length + 5;
  result[prefix] = protocol;
  payload.copy(result, prefix + 1);
  result.writeUInt16BE(serial, result.length - 6);
  result.writeUInt16BE(crc16(result.subarray(2, -4)), result.length - 4);
  result.writeUInt16BE(0x0d0a, result.length - 2);
  return result;
}

export class Gt06Decoder {
  private pending: Buffer = Buffer.alloc(0);
  private imei = '';

  push(chunk: Buffer): Array<{ packet: TrackerPacket; ack?: Buffer }> {
    this.pending = Buffer.concat([this.pending, chunk]);
    if (this.pending.length > 65536) throw new Error('frame_buffer_limit');
    const output: Array<{ packet: TrackerPacket; ack?: Buffer }> = [];
    while (this.pending.length >= 5) {
      const header = this.pending.readUInt16BE(0);
      if (header !== 0x7878 && header !== 0x7979) throw new Error('invalid_frame_header');
      const extended = header === 0x7979;
      const prefix = extended ? 4 : 3;
      const length = extended ? this.pending.readUInt16BE(2) : this.pending[2]!;
      if (length < 5 || length > 4090) throw new Error('invalid_frame_length');
      const total = prefix + length + 2;
      if (this.pending.length < total) break;
      const data = this.pending.subarray(0, total);
      this.pending = this.pending.subarray(total);
      if (data.readUInt16BE(total - 2) !== 0x0d0a || crc16(data.subarray(2, -4)) !== data.readUInt16BE(total - 4)) {
        throw new Error('invalid_frame_checksum');
      }
      const protocol = data[prefix]!;
      const serial = data.readUInt16BE(total - 6);
      const body = data.subarray(prefix + 1, -6);
      if (protocol === 0x01) {
        if (body.length < 8) throw new Error('short_login');
        const identity = body.subarray(0, 8).toString('hex');
        if (!/^0\d{15}$/.test(identity)) throw new Error('invalid_imei');
        if (this.imei && this.imei !== identity.slice(1)) throw new Error('identity_changed');
        this.imei = identity.slice(1);
      }
      if (!this.imei) throw new Error('login_required');
      const packet: TrackerPacket = { imei: this.imei, protocol, packet_hex: data.toString('hex') };
      if ([0x10, 0x12, 0x22, 0x16, 0x26, 0xa0, 0xa2].includes(protocol)) {
        if (body.length < 18) throw new Error('short_location');
        const [year, month, day, hour, minute, second] = [...body.subarray(0, 6)] as [number, number, number, number, number, number];
        const date = new Date(Date.UTC(2000 + year, month - 1, day, hour, minute, second));
        if (date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day || hour > 23 || minute > 59 || second > 59) throw new Error('invalid_date');
        const flags = body.readUInt16BE(16);
        const latitude = body.readUInt32BE(7) / 1800000 * (flags & 0x0400 ? 1 : -1);
        const longitude = body.readUInt32BE(11) / 1800000 * (flags & 0x0800 ? -1 : 1);
        if (Math.abs(latitude) > 90 || Math.abs(longitude) > 180) throw new Error('invalid_coordinates');
        // Preserve invalid-fix packets for diagnosis without inventing a location.
        if (flags & 0x1000) packet.location = { timestamp: date.toISOString(), latitude, longitude, speedKmh: body[15]!, heading: flags & 0x03ff };
      }
      if (protocol === 0x94 && extended && body[0] === 0x0a && body.length >= 27) {
        const iccid = body.subarray(17, 27).toString('hex').replace(/f+$/, '');
        if (/^\d{18,22}$/.test(iccid)) packet.iccid = iccid;
      }
      if ([0x94, 0x15, 0x21].includes(protocol)) {
        const iccid = body.toString('ascii').match(/ICCID[=:]\s*(\d{18,22})(?!\d)/)?.[1];
        if (iccid) packet.iccid = iccid;
      }
      let ack: Buffer | undefined;
      if (protocol === 0x8a) {
        const now = new Date();
        ack = frame(protocol, serial, Buffer.from([now.getUTCFullYear() - 2000, now.getUTCMonth() + 1, now.getUTCDate(), now.getUTCHours(), now.getUTCMinutes(), now.getUTCSeconds()]), extended);
      } else if (protocol !== 0x94 && ![0x15, 0x21, 0x80, 0x81, 0x82].includes(protocol)) {
        ack = frame(protocol, serial, Buffer.alloc(0), extended);
      }
      output.push({ packet, ack });
    }
    return output;
  }
}

export function startGt06(socket: net.Socket, initial: Buffer, persist: (packet: TrackerPacket) => Promise<void>, log: (event: string, fields: Record<string, unknown>) => void): void {
  const decoder = new Gt06Decoder();
  let chain = Promise.resolve();
  let queued = 0;
  socket.setTimeout(180000, () => socket.destroy());
  const consume = (chunk: Buffer): void => {
    queued += chunk.length;
    if (queued > 65536) { socket.destroy(); return; }
    socket.pause();
    chain = chain.then(async () => {
      for (const { packet, ack } of decoder.push(chunk)) {
        await persist(packet);
        // Never acknowledge an upload that the database did not accept.
        if (ack && !socket.destroyed) socket.write(ack);
        log('tracker.packet_stored', { protocol: packet.protocol, hasLocation: !!packet.location });
      }
      queued -= chunk.length;
      if (!socket.destroyed) socket.resume();
    }).catch((error: Error) => {
      log('tracker.connection_failed', { reason: error.message });
      socket.destroy();
    });
  };
  socket.on('data', consume);
  socket.on('error', () => socket.destroy());
  consume(initial);
}
