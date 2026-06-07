# mTrack Canonical Decisions

This document is the decision source of truth. If another document conflicts with this file, this file wins.

## Product Scope

- V1 positioning: fleet tracking SaaS plus customer fleet portal.
- V1 surfaces: admin web, customer web, and customer mobile.
- V1 privacy scope: business asset/fleet tracking only. Personal tracking workflows are out of scope.
- First-class tracked entity: the tracker/device itself, with optional business labels such as boat, buggy, vessel, vehicle, fleet, or asset.

## Tenancy

- mTrack uses a hybrid tenancy model.
- The data model must be tenant-aware from day one.
- Deployment may be single-tenant or multi-tenant SaaS.
- Tenant isolation is mandatory for customers, users, roles, trackers, groups, geofences, events, billing, audit logs, reports, and raw payloads.

## Roles And Permissions

- V1 requires full custom roles.
- Roles support configurable permissions per module and per fleet/group.
- Permission levels are `hide`, `view`, and `edit`.
- Customer administrators can create and manage roles inside their tenant, subject to their own permission level.
- Platform administrators can manage all tenants and customer roles.

## Authentication

- User authentication is passwordless.
- V1 supports email magic links and Google sign-in.
- Password authentication is out of scope.
- Mobile authentication uses long-lived JWT-style access with refresh.
- SSO beyond Google is not required in v1, but the auth model should not block future OIDC expansion.

## Device Ingestion

- V1 ingestion accepts any HTTP body.
- Supported body shapes include JSON, raw text, hex-like tracker payloads, form data, XML, and other parser-readable bodies.
- Device identity is contract-defined.
- V1 starts with unsigned ingestion and parser/contract-defined identity.
- Unsigned ingestion requires rate limiting, payload size limits, diagnostics, and abuse monitoring.
- API tokens can be manually regenerated; old tokens revoke immediately.

## Parser Contracts

- Contracts are developer-managed internal PHP classes.
- No runtime user-uploaded parser code is allowed in v1.
- Contracts are deployed with the app, versioned, and covered by fixtures/tests.
- Customers/admins can inspect logs and normalized results but cannot author parser contracts in the app.

## Data Retention

- Raw payload retention is configurable per tenant.
- Supported raw payload retention presets are 30, 90, 180, and 365 days.
- Audit logs are retained indefinitely.

## Maps And Realtime

- Web maps use OpenStreetMap.
- Flutter mobile uses Google Maps on Android and Apple Maps on iOS where practical.
- Live updates publish immediately after payload processing.
- Actual update frequency depends on the tracker/device cadence.
- Realtime uses Laravel Reverb for web plus a compatible Flutter WebSocket channel/client for mobile.

## Alerts, Geofences, And Playback

- V1 alerts: geofence entrance/exit, overspeed, online/offline, and stale/no-data.
- Geofences support polygon and circle shapes.
- Geofences support groups, per-tracker assignments, entrance/exit alerts, and optional speed limits.
- Playback/history includes animated replay, route table, stops, moving/idle segments, speed graph, trip summary, and CSV export.

## Billing And Payments

- V1 uses hybrid license plans.
- Plans include base features plus per-device license counts.
- Customers can add and renew licenses.
- Payments are manual first, gateway later.
- V1 payment flow uses invoice/license request records with customer slip upload.
- Admins approve or reject payment slips with a reason.

## Reports And Exports

- V1 includes the PDF-visible report set: geofence, overspeed, device status, routes, device logs, analysis, audit log, and logs.
- CSV exports support configurable columns per report.

## Design System

- Modernize the visual design while preserving the PDF structure and interaction patterns.
- Teal remains the brand accent.
- The PDF is structure/component evidence, not a strict visual target.
- Exact design tokens are defined in `DESIGN_SYSTEM.md`.

## Stack And Deployment

- Web backend/frontend: Laravel/PHP, Inertia, Vue, TypeScript, Tailwind, Headless UI, Lucide icons.
- The Laravel backend and web app must live under `api/`.
- Mobile: Flutter.
- The Flutter app must live under `mobile/`.
- Database/cache/queues: PostgreSQL plus Redis.
- Queue monitoring: Laravel Horizon.
- Observability: Laravel logs plus Horizon and basic Coolify/server visibility.
- Initial hosting: Ubuntu Coolify server via Nixpacks using `api/nixpacks.toml`.
- No formal launch SLO in v1.
