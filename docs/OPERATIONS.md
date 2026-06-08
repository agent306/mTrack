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

### Deploying Through Coolify

1. Set the Coolify project build context to `api/`.
2. Confirm `api/nixpacks.toml` is detected and includes PHP 8.4, Node.js 22, nginx, `php artisan optimize`, and `NIXPACKS_PHP_ROOT_DIR=/app/public`.
3. Configure PostgreSQL, Redis, Reverb, mail, Google OAuth, and `APP_KEY` environment variables from `api/.env.example`.
4. Run `php artisan mtrack:production-check --strict` in the deployed container before routing traffic.
5. Run `php artisan migrate --force`, then deploy/restart the app process, Horizon, and Reverb.

### Rolling Back A Deployment

1. Put the app in maintenance mode with `php artisan down --render=errors::503` if the failed deploy is serving traffic.
2. Revert Coolify to the previous successful image/revision.
3. Run `php artisan optimize:clear && php artisan optimize`.
4. Restart Horizon with `php artisan horizon:terminate`; allow the supervisor to bring it back.
5. Bring the app back with `php artisan up` and check `/up`.

### Restarting Queue Workers

1. Check Horizon at `/horizon` as a platform admin.
2. Run `php artisan horizon:status`.
3. Run `php artisan horizon:terminate` to gracefully recycle workers.
4. Confirm queues drain and no long-wait alerts persist.

### Restarting Reverb

1. Run `php artisan mtrack:production-check --runtime` to confirm whether the Reverb socket is reachable.
2. Restart the Coolify/Reverb process.
3. Confirm `REVERB_SERVER_HOST`, `REVERB_SERVER_PORT`, `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET`.
4. Open a live tracking page and confirm a new normalized payload broadcasts immediately.

### Replaying Failed Ingestion Payloads

1. Find the rejected raw payload in Admin > Logs or Customer > Setting logs.
2. Confirm the parser contract key/version and rejection diagnostics.
3. Use the replay API route for permitted operators: `POST /api/raw-payloads/{rawPayload}/replay`.
4. Confirm `raw_payload.replayed` and the resulting normalization/rejection audit records.

### Restoring From Backup

1. Pick the backup directory under `storage/app/backups`.
2. Restore PostgreSQL with `psql` from `database.sql`, or copy the sqlite file for local smoke environments.
3. Restore uploaded files from the backup storage directories to `storage/app/private` and `storage/app/public`.
4. Run `php artisan migrate --force`, `php artisan storage:link`, and `php artisan mtrack:production-check --strict`.
5. Reconcile payment-slip records against restored private storage.

### Cleaning Raw Payloads By Retention Preset

1. Review tenant `raw_payload_retention_days`; valid presets are 30, 90, 180, and 365 days.
2. Dry-run cleanup with `php artisan mtrack:prune-raw-payloads --dry-run`.
3. Clean one tenant with `php artisan mtrack:prune-raw-payloads --tenant-id={id}` or all tenants with `php artisan mtrack:prune-raw-payloads`.
4. Confirm audit logs remain intact; they are retained indefinitely.

### Handling Stuck Payment Approvals

1. Inspect Admin > Payments and verify the slip status, requested device count, and rejection reason if present.
2. If a slip is pending but the customer claims payment completed, approve or reject through the admin payment action.
3. Confirm a license allocation was activated or renewed, tenant billing status changed to active, and audit logs contain payment/license actions.

### Investigating Missing Live Updates

1. Confirm the device payload reached `/api/ingest/{contractKey}` and did not exceed `MTRACK_INGESTION_MAX_PAYLOAD_BYTES`.
2. Check raw payload diagnostics for missing identity, invalid coordinates, malformed body, or unresolved tracker.
3. Confirm `php artisan mtrack:production-check --runtime` passes Reverb socket checks.
4. Verify Horizon workers are running if async jobs are introduced later; current normalization is synchronous.
5. Inspect browser/mobile WebSocket settings and Reverb app credentials.

## Backups

Backups must cover:

- PostgreSQL.
- Uploaded payment slips and fleet images.
- Any persisted raw payload storage not stored directly in PostgreSQL.

Use `php artisan mtrack:backup --dry-run` to print the launch backup plan and `php artisan mtrack:backup` to create a timestamped backup directory. The command records raw payload storage as PostgreSQL-backed and copies local private/public storage paths configured in `config/mtrack.php`.
