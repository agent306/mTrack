# mTrack Architecture

## System Overview

mTrack is composed of an application layer, ingestion layer, normalization pipeline, storage layer, and observability/audit layer.

External devices send arbitrary HTTP payloads to ingestion endpoints. The system stores the raw payload, resolves contract-defined device identity, runs the developer-managed parser/plugin contract, normalizes the payload into a canonical location event, stores the normalized event, and updates downstream views such as maps, dashboards, alerts, and timelines.

The application surfaces this architecture through the standalone page structure defined in `APP_BLUEPRINT.md`.

## Major Services

### Application API

Serves user-facing product features: authentication, organizations, tracked entities, devices, contracts, geofences, alerts, dashboards, and diagnostics.

### Ingestion API

Receives device payloads over HTTP. Responsibilities include device authentication, rate limiting, idempotency, raw capture, contract lookup, and enqueueing normalization work.

### Contract Engine

Executes versioned developer-managed code/plugin contracts. It extracts identity and data from arbitrary HTTP bodies, validates required values, transforms values into canonical types, and records diagnostics.

### Location Event Pipeline

Creates normalized location events and optional derived events such as geofence entry/exit, overspeed, online/offline, stale/no-data status, trip analytics, and alert candidates.

### Realtime Delivery

Publishes live updates immediately after payload processing through WebSockets/gRPC where applicable. Actual update cadence depends on each tracker/device.

### Observability And Audit

Tracks ingestion rates, failures, contract errors, event latency, rejected payloads, and replay attempts.

## Canonical Data Flow

1. Device posts any HTTP body.
2. Ingestion API authenticates request path/token when available and stores raw request.
3. Raw payload record is stored.
4. Active code/plugin contract version is resolved.
5. Contract engine identifies device, parses, validates, and normalizes payload.
6. Normalized location event is stored.
7. Downstream processors update entity state, alerts, geofence events, and dashboards.
8. Diagnostics remain available for audit and support.

## Canonical Location Event

The normalized event should include at minimum:

- organization id.
- device id.
- tracked entity id when assigned.
- contract id and version.
- raw ingestion id.
- event timestamp.
- received timestamp.
- latitude.
- longitude.
- optional altitude.
- optional speed.
- optional heading.
- optional accuracy.
- optional battery/device health.
- normalized metadata.
- validation status.

## Storage Requirements

- Raw payloads must be stored separately from normalized events.
- Contract versions must be immutable once published.
- Normalized events must reference raw payload and contract version.
- Tenant boundaries must be explicit in every table/collection.
- Raw payload retention policies must be configurable per tenant.

## Deployment Target

Initial deployment target is an Ubuntu Coolify server using Nixpacks. `api/nixpacks.toml` defines PHP 8.4, Composer, Node.js 22, nginx, Laravel build steps, upload/body limits, and nginx routing for the Laravel app.

## Reliability Requirements

- Idempotency for repeated device posts.
- Rate limits per device and organization.
- Replay support for failed or newly updated contracts.
- Dead-letter handling for invalid or unprocessable payloads.
- Metrics for ingestion latency and contract failure rates.

## Security Requirements

- Device-level credentials or signed requests.
- Tenant isolation in ingestion and app APIs.
- Audit logs for contract changes and device credential changes.
- Secret rotation support.
- No trusted parsing of external payloads before validation.
