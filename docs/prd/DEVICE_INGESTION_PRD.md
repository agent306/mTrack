# PRD: Device Ingestion And Contract Pipeline

## Objective

Allow any HTTP-capable tracking device to send any HTTP body to mTrack and convert valid payloads into normalized geolocation events through developer-managed versioned code/plugin contracts.

The user-facing management and diagnostics surfaces for this pipeline are defined in `../APP_BLUEPRINT.md`.

## Problem

Tracking devices do not share one payload format. A production system must accept diverse payloads while keeping normalized system data consistent, auditable, and reliable.

## Requirements

### Device Endpoint

- Each device, integration, organization, or contract route has an HTTP endpoint or endpoint credential.
- The endpoint receives any HTTP body subject to size and rate limits.
- Requests are authenticated before contract execution.

### Raw Capture

- Raw payloads are stored before normalization.
- Raw records include received timestamp, headers needed for diagnostics, device identity, and organization identity.

### Contract Execution

- Each device/integration resolves to an active contract version.
- Contracts extract device identity and map arbitrary payload data to normalized geolocation fields.
- Contracts validate required fields and canonical coordinate bounds.
- Published contract versions are immutable.
- Contracts are authored and deployed by developers in v1.

### Diagnostics

- Failed payloads produce diagnostics rather than normalized events.
- Diagnostics expose failure reason, failed field, raw ingestion id, and contract version.

### Replay

- Operators can replay raw payloads for diagnostics or contract migration.
- Replay must be auditable and idempotent.

## Acceptance Criteria

- A sample arbitrary JSON payload can be normalized through a contract.
- Invalid coordinates are rejected with diagnostics.
- Missing required fields are rejected with diagnostics.
- Normalized events reference raw payload id and contract version.
- Replaying a raw payload does not create uncontrolled duplicates.
- Live updates are published immediately after payload processing through WebSockets/gRPC where applicable.

## Metrics

- Ingestion request count.
- Normalization success rate.
- Contract failure rate.
- Average ingestion-to-event latency.
- Payload rejection reasons.
