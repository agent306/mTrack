# Device Contracts And Geolocation Pipeline

## Purpose

mTrack supports any device that can send any HTTP body to an HTTP endpoint. A device contract defines how a raw payload identifies a device and becomes a normalized location event.

Contracts are required because raw device payloads may differ by vendor, firmware, field names, units, timestamp format, nesting, transport headers, and metadata.

The app pages that expose contract setup, sample testing, diagnostics, replay, and audit behavior are defined in `../APP_BLUEPRINT.md`.

## Ingestion Endpoint Concept

Devices send HTTP requests to an endpoint assigned to an organization, device, integration, or contract route.

The endpoint must support any HTTP body in v1, including JSON, text, hex-like protocol payloads, form data, XML, and other body shapes that a parser/plugin contract can understand.

## Contract Responsibilities

A contract defines:

- Accepted content type.
- Authentication method.
- Device identity extraction.
- Field extraction paths.
- Required fields.
- Type conversions.
- Unit conversions.
- Timestamp parsing.
- Coordinate parsing.
- Validation rules.
- Metadata preservation.
- Rejection diagnostics.

## Contract Ownership

Contracts are developer-managed code/plugin artifacts in v1. Customers and administrators can inspect device logs, status, diagnostics, and normalized results, but they do not create or edit parser/plugin contracts in the app.

## Normalized Geolocation Fields

Required normalized fields:

- `eventTimestamp`.
- `latitude`.
- `longitude`.

Strongly recommended normalized fields:

- `accuracyMeters`.
- `speedMetersPerSecond`.
- `headingDegrees`.
- `altitudeMeters`.
- `batteryPercent`.
- `deviceStatus`.
- `sourceSequence` or source event id.

## Example Contract Shape

```json
{
  "name": "Example GPS Contract",
  "version": 1,
  "contentType": "application/json",
  "fields": {
    "eventTimestamp": { "path": "$.timestamp", "type": "datetime" },
    "latitude": { "path": "$.gps.lat", "type": "number" },
    "longitude": { "path": "$.gps.lng", "type": "number" },
    "speedMetersPerSecond": { "path": "$.speed", "type": "number", "unit": "kmh" },
    "batteryPercent": { "path": "$.battery", "type": "number" }
  },
  "required": ["eventTimestamp", "latitude", "longitude"],
  "metadata": {
    "sourceStatus": "$.status",
    "firmware": "$.firmware"
  }
}
```

This JSON example is illustrative only. V1 uses code/plugin contracts, so the final implementation may represent contract configuration differently.

## Processing Stages

1. Receive request.
2. Authenticate request path/token when applicable.
3. Store raw request body and selected headers.
4. Resolve active contract version.
5. Extract device identity through the contract.
6. Extract fields from payload.
7. Convert values into canonical units.
8. Validate required fields and coordinate bounds.
9. Store normalized event or rejection diagnostics.
10. Update tracked entity state and downstream processors.

## Validation Rules

- Latitude must be between -90 and 90.
- Longitude must be between -180 and 180.
- Event timestamp must parse into a valid instant.
- Required fields must be present after extraction.
- Contract version must be active for the device.
- Payload must not exceed configured size limits.

## Failure Handling

Invalid payloads should not create normalized location events. They should create ingestion diagnostics that include raw ingestion id, device id, contract version, failure reason, failed field, and received timestamp.

## Replay

Replay allows an operator to re-run stored raw payloads against the same or a newer contract version. Replay must be auditable and should not silently duplicate existing normalized events.
