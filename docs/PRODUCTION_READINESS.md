# Production Readiness Checklist

## Decision Status

All product-critical v1 decisions collected during the interviews are recorded in `DECISIONS.md` and `PRODUCTION_QUESTIONNAIRE.md`.

## Remaining Pre-Build Tasks

- Confirm final product copy during implementation.
- Convert `DATA_MODEL.md` into Laravel migrations.
- Convert `SECURITY.md` permissions into authorization policies.
- Convert `DESIGN_SYSTEM.md` into Tailwind theme tokens and Flutter theme tokens.
- Convert `OPERATIONS.md` into deployment/runbook files.

## Known V1 Tradeoffs

- Ingestion starts unsigned and relies on parser-defined identity plus operational safeguards.
- Observability is Laravel logs plus Horizon and basic Coolify/server visibility, not a full external observability stack.
- No formal uptime SLO is required in v1.
- Payment gateway support is deferred.

## Production Checklist

- Tenant isolation tests exist.
- Role permission tests exist.
- Parser contract fixtures exist.
- Ingestion rate limits and payload size limits are configured.
- Raw payload retention cleanup is scheduled.
- Audit logs cover all operational changes.
- Backups are configured for PostgreSQL and uploaded files.
- Horizon workers are running and monitored.
- Reverb is running for realtime updates.
- Coolify deployment succeeds using `api/nixpacks.toml`.

## Recommendation

Proceed to implementation from the documentation set. The highest-risk implementation area remains ingestion/parser correctness, so build parser fixtures, raw-payload diagnostics, and replay support early.
