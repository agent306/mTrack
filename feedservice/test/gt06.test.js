import assert from 'node:assert/strict';
import test from 'node:test';
import net from 'node:net';
import { once } from 'node:events';
import { Gt06Decoder, crc16, frame, startGt06 } from '../dist/gt06.js';

const imei = '123456789012345';
const login = () => frame(0x01, 1, Buffer.from('0' + imei, 'hex'));
const location = (serial = 2, valid = true) => {
  const bytes = Buffer.alloc(26);
  Buffer.from([26, 9, 20, 12, 30, 0, 0xc8]).copy(bytes);
  bytes.writeUInt32BE(24.5 * 1800000, 7);
  bytes.writeUInt32BE(67.25 * 1800000, 11);
  bytes[15] = 36;
  bytes.writeUInt16BE((valid ? 0x1000 : 0) | 0x0400 | 90, 16);
  return frame(0xa0, serial, bytes);
};

test('X25 checksum and published GT06 login acknowledgement', () => {
  assert.equal(crc16(Buffer.from('123456789')), 0x906e);
  assert.equal(frame(1, 1).toString('hex'), '787805010001d9dc0d0a');
});
test('fragmented login and coalesced location/heartbeat keep identity', () => {
  const decoder = new Gt06Decoder();
  assert.deepEqual(decoder.push(login().subarray(0, 1)), []);
  const first = decoder.push(login().subarray(1));
  assert.equal(first[0].packet.imei, imei);
  const records = decoder.push(Buffer.concat([location(), frame(0x13, 3, Buffer.from('0102030405', 'hex'))]));
  assert.equal(records.length, 2);
  assert.deepEqual(records[0].packet.location, { timestamp: '2026-09-20T12:30:00.000Z', latitude: 24.5, longitude: 67.25, speedKmh: 36, heading: 90 });
  assert.equal(records[1].packet.imei, imei);
});
test('bad checksum, missing login, oversized frames and identity swaps are rejected', () => {
  const bad = Buffer.from(login()); bad[5] ^= 1;
  assert.throws(() => new Gt06Decoder().push(bad), /checksum/);
  assert.throws(() => new Gt06Decoder().push(location()), /login_required/);
  assert.throws(() => new Gt06Decoder().push(Buffer.from('7979ffff01', 'hex')), /length/);
  const decoder = new Gt06Decoder(); decoder.push(login());
  assert.throws(() => decoder.push(frame(1, 2, Buffer.from('0987654321098765', 'hex'))), /identity_changed/);
});
test('invalid GPS fixes are preserved without fabricated coordinates', () => {
  const decoder = new Gt06Decoder(); decoder.push(login());
  assert.equal(decoder.push(location(2, false))[0].packet.location, undefined);
});
test('extended ICCID report and text status report are decoded', () => {
  const decoder = new Gt06Decoder(); decoder.push(login());
  const body = Buffer.concat([Buffer.from([0x0a]), Buffer.alloc(16), Buffer.from('89860012345678901234', 'hex')]);
  assert.equal(decoder.push(frame(0x94, 3, body, true))[0].packet.iccid, '89860012345678901234');
  assert.equal(decoder.push(frame(0x94, 4, Buffer.from('\x04ICCID=89860012345678901234;'), true))[0].packet.iccid, '89860012345678901234');
});
test('socket acknowledges persisted packets and remains open for later uploads', async () => {
  const stored = [];
  const server = net.createServer(socket => startGt06(socket, Buffer.alloc(0), async packet => { stored.push(packet); }, () => {}));
  server.listen(0, '127.0.0.1'); await once(server, 'listening');
  const client = net.connect(server.address().port, '127.0.0.1');
  try {
    await once(client, 'connect');
    client.write(login());
    const [ack] = await once(client, 'data');
    assert.equal(ack.toString('hex'), frame(1, 1).toString('hex'));
    client.write(location()); await once(client, 'data');
    assert.equal(stored.length, 2);
    assert.equal(client.destroyed, false);
  } finally { client.destroy(); server.close(); }
});
test('failed persistence closes socket without acknowledgement', async () => {
  const server = net.createServer(socket => startGt06(socket, Buffer.alloc(0), async () => { throw new Error('database unavailable'); }, () => {}));
  server.listen(0, '127.0.0.1'); await once(server, 'listening');
  const client = net.connect(server.address().port, '127.0.0.1');
  const replies = []; client.on('data', b => replies.push(b));
  try { await once(client, 'connect'); client.write(login()); await once(client, 'close'); assert.equal(replies.length, 0); }
  finally { client.destroy(); server.close(); }
});
