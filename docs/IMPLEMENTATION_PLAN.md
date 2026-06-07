# mTrack Implementation Plan

## Phase 1: Foundation

- Laravel, Inertia, Vue, TypeScript, Tailwind, Headless UI, Lucide setup under `api/`.
- Flutter app setup under `mobile/`.
- PostgreSQL and Redis setup.
- Tenant-aware data model.
- Passwordless auth: email magic link and Google sign-in.
- Custom roles and permissions.

## Phase 2: Tracking Core

- Tracker/device registry.
- Fleet groups.
- Raw payload ingestion endpoint.
- Internal PHP parser contract interface.
- Normalized location event storage.
- Live update publishing with Reverb.

## Phase 3: Customer Product

- Customer dashboard.
- Live map with OpenStreetMap.
- Playback and trip analytics.
- Reports and configurable CSV exports.
- Geofence management.
- Customer mobile app in Flutter under `mobile/`.

## Phase 4: Admin Product

- Customer management.
- Device assignment and discovery.
- Payment slip approval.
- License management.
- Raw device logs and diagnostics.

## Phase 5: Hardening

- Horizon worker monitoring.
- Retention cleanup.
- Audit log coverage.
- Replay tools.
- Backup/restore runbooks.
- Coolify deployment validation.
