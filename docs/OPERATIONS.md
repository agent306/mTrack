# mTrack Operations

## Launch Environment

Initial deployment target is an Ubuntu Coolify server using Nixpacks. The Laravel backend/web app lives in `api/`; the Flutter app lives in `mobile/`.

`api/nixpacks.toml` defines deployment behavior for the Laravel app:

- PHP 8.4.
- Composer.
- Node.js 22.
- nginx.
- Laravel build and optimize commands.
- Upload/body limits for large files.
- nginx routing to `/app/public` when the Coolify/Nixpacks build context is `api/`.

## Runtime Services

- Laravel application.
- Laravel application under `api/`.
- PostgreSQL database.
- Redis.
- Laravel Horizon workers.
- Laravel Reverb for realtime web updates.
- Flutter WebSocket channel/client for mobile realtime updates.
- Flutter app under `mobile/`.

## Observability

V1 observability uses:

- Laravel logs.
- Laravel Horizon.
- Basic Coolify/server visibility.

No formal uptime SLO is required in v1.

## Required Runbooks

Runbooks should exist before customer launch for:

- Deploying through Coolify.
- Rolling back a deployment.
- Restarting queue workers.
- Restarting Reverb.
- Replaying failed ingestion payloads.
- Restoring from backup.
- Cleaning raw payloads by retention preset.
- Handling stuck payment approvals.
- Investigating missing live updates.

## Backups

Backups must cover:

- PostgreSQL.
- Uploaded payment slips and fleet images.
- Any persisted raw payload storage not stored directly in PostgreSQL.
