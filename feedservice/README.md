# mTrack Feedservice

Small tracker feed relay for devices that can only be configured with a domain/IP and port.

Deploy this directory as its own Coolify/Nixpacks app. The production feed endpoint is assumed to be `mtrackerfeed.nashath.dev:5023`. Point tracker devices at that host and port. The service accepts any HTTP method/path, keeps the raw payload, and forwards it to the mTrack ingestion endpoint.

The primary `PORT` auto-detects HTTP callbacks and raw TCP packets, so devices do not need a URL path. TCP payloads are forwarded as `application/octet-stream`.

## Environment

```dotenv
PORT=5023
FEEDSERVICE_PUBLIC_URL=http://mtrackerfeed.nashath.dev:5023
MTRACK_INGEST_URL=https://mtrack.nashath.dev/api/ingest
FEEDSERVICE_MAX_PAYLOAD_BYTES=1048576
FEEDSERVICE_FORWARD_TIMEOUT_MS=15000
FEEDSERVICE_FORWARD_METHOD=POST
FEEDSERVICE_HEALTH_PATH=/health
FEEDSERVICE_LOG_BODY=false
FEEDSERVICE_SOCKET_DETECTION_TIMEOUT_MS=1000
FEEDSERVICE_TCP_IDLE_TIMEOUT_MS=1500
# Optional secondary raw TCP listener
# FEEDSERVICE_TCP_PORT=5024
```

`FEEDSERVICE_FORWARD_METHOD=POST` is recommended because the mTrack API accepts any method and POST reliably carries raw bodies. Query strings are preserved when devices send GET-style callbacks.

Use `http://mtrackerfeed.nashath.dev:5023` for HTTP-style trackers that require a URL field, and `mtrackerfeed.nashath.dev` with port `5023` for trackers that only accept host/port.

## Local Run

```bash
npm install
npm run build
npm start
```

Health check:

```bash
curl http://127.0.0.1:5023/health
```

Sample feed:

```bash
curl -X POST http://127.0.0.1:5023 \
  -H 'Content-Type: application/json' \
  --data '{"deviceId":"demo-vessel-001","timestamp":"2026-06-08T04:30:00Z","latitude":24.8607,"longitude":67.0011}'
```

Raw TCP smoke test:

```bash
printf 'raw-device-packet' | nc 127.0.0.1 5023
```
