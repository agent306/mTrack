# mTrack Roadmap

## Phase 0: Product Definition

- Product decisions captured in `DECISIONS.md`.
- App blueprint captured in `APP_BLUEPRINT.md`.
- Data model captured in `DATA_MODEL.md`.
- Security model captured in `SECURITY.md`.
- Operations model captured in `OPERATIONS.md`.

## Phase 1: Core Platform Foundation

- Build from `APP_BLUEPRINT.md` rather than the Figma file.
- Organization and user access.
- Passwordless auth.
- Custom roles and permissions.
- Tracker/device registry.
- Device registry.
- Audit log foundation.

## Phase 2: Device Ingestion MVP

- Authenticated HTTP ingestion endpoint.
- Raw payload storage.
- Internal PHP parser contract model.
- Any HTTP body support.
- Normalized location event creation.
- Rejection diagnostics.

## Phase 3: Tracking Experience MVP

- Entity list and detail.
- Latest location state.
- Event timeline.
- OpenStreetMap web live view.
- Playback and trip analytics.
- Customer mobile Flutter app.

## Phase 4: Operational Features

- Contract testing UI.
- Payload replay.
- Geofences.
- Alerts.
- Ingestion metrics.
- Support diagnostics.

## Phase 5: Production Hardening

- Rate limiting.
- Idempotency.
- Secret rotation.
- Backup and restore.
- Incident runbooks.
- Load testing.
- Security review.
