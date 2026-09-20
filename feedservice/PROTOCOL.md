# Native tracker listener

The primary port recognizes GT06/Jimi `7878` and `7979` framing alongside the existing HTTP/raw relay. It validates CRC-16/X25, buffers split frames, separates coalesced frames, binds the login IMEI to the connection, and keeps the session open. Login, status, and supported GPS uploads receive binary acknowledgements after the API accepts persistence. Failed persistence closes the connection without a success acknowledgement so the tracker can retry.

Supported GPS layouts: 0x10, 0x12, 0x22, 0x16, 0x26, 0xA0, 0xA2. Invalid GPS fixes remain raw packets and do not create locations. Other framed messages are preserved; unsupported packet types do not fabricate locations. ICCID is extracted from extended 0x94/0x0A reports and textual ICCID status reports. Firmware variants still require verification with real hardware.

The implementation follows the published GT06 layout and CRC check vector. Reference used to verify field meanings: https://github.com/traccar/traccar/blob/master/src/main/java/org/traccar/protocol/Gt06ProtocolDecoder.java . Configuration guide: https://www.jimiiot.com/wp-content/uploads/2023/05/VL512-User-Manual-V1.1.pdf .

## Deployment

Deploy `/api` and `/feedservice` separately. Keep router forwarding external TCP 5023 to `192.168.18.58:5023`. The feed application's mapping is `5023:5023` and `PORT=5023`. Native traffic bypasses HTTP domain routing.

Set an identical random `TRACKER_GATEWAY_SECRET` of at least 32 characters on both applications (build/runtime for Laravel's configuration cache, runtime for Node). Never commit it. Set `TRACKER_GATEWAY_URL=https://mtrack.nashath.dev/api/gateway/packets` on the listener. Deploy the API and run `php artisan migrate --force` before deploying the listener. Coolify already has this migration post-deployment command.

Customers send `SERVER,0,124.195.201.14,5023#` themselves. Discovery requires IMEI and full SIM ICCID, without a global device directory. If firmware does not report ICCID, an operator can independently verify ownership and run `php artisan mtrack:claim-code <imei>` to issue a private one-time code. Deliver that code privately to the verified owner.

Packets are held before claiming. Claiming serializes on the device row, assigns a tenant-owned tracker, and processes buffered locations. Fingerprints prevent packet retries from duplicating locations. Raw packets use hex so binary data cannot produce PostgreSQL UTF-8 errors.

Geofence events establish an initial inside/outside baseline. Only subsequent transitions are reported. Late positions remain historical records but cannot move latest state backwards. Boundary edits reset the baseline. Reports use inclusive UTC calendar dates and escape spreadsheet formula prefixes in CSV.

Run `npm test` for protocol/socket tests; run `php artisan test` and frontend typecheck/build in `api/`. A successful SMS or healthy container alone does not prove GPS reporting: verify the actual device connection, packet types, valid locations, and ownership.
