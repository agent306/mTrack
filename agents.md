# mTrack repository guide

Updated 2026-09-20 for the customer tracking overhaul.

## Working preferences

- Stay within the requested scope. Ask before adjacent investigations or optional refactoring.
- Keep routine changes and verification focused. Do not delegate unless explicitly requested.
- Complete authorized implementation steps without repeated permission requests.
- Never print or commit secrets from local environment files.
- Networking and SMS workflow clarification is complete. Customers send SMS themselves; a paid SMS provider is deferred. Production deployment is authorized after implementation and verification.

## Application map

- `api/`: Laravel 13 / PHP 8.4 backend and Inertia 3 / Vue 3 / TypeScript web application. Vite builds the frontend; Tailwind provides styling and Leaflet provides maps.
- `api/routes/web.php`: Google and magic-link authentication, customer portal, and administrative routes.
- `api/routes/api.php`: ingestion and authenticated API routes.
- `api/app/Http/Controllers/Customer/CustomerController.php` and `api/resources/js/Pages/Customer/Portal.vue`: customer dashboard, live tracking, playback, events, fleet groups, geofences, analytics, routes, settings, billing, and audit views.
- `api/app/Http/Controllers/Admin/AdminController.php` and `api/resources/js/Pages/Admin/Workspace.vue`: administration, including device assignment and discovered-payload assignment.
- `api/app/Models/`: tenants, users, roles, trackers, raw payloads, normalized locations, geofences, alerts, fleet groups, billing records, settings, and audit records.
- `api/app/Support/CurrentTenant.php`, tenant middleware, policies, and permission matrix: customer isolation and access control.
- `api/app/Ingestion/`: raw capture, versioned parser contracts, normalization, replay, persistence, and location broadcasts.
- `api/app/Tracking/TrackerStateService.php`: latest tracker status and online/offline-related alerts.
- `api/app/Reports/ReportExportService.php`: report export data.
- `feedservice/`: separate Node.js 22+ / TypeScript network relay, with its own Nixpacks deployment definition.
- `mobile/`: Flutter customer application.
- `docs/`: existing product, design, security, architecture, operations, and implementation references. Distinguish planned behavior in these documents from actual code. The current user request takes precedence over older product decisions.

## Current authentication and ingestion

Google authentication uses Laravel Socialite. Verified new Google accounts receive an isolated customer tenant and owner role through CustomerRegistration. Existing users retain their roles and inactive accounts remain blocked. DefaultAccessProvisioner still reads configured tenants, roles, and users.

The feed service binds `0.0.0.0`, defaults to `PORT=5023`, and recognizes native GT06/Jimi framing before the legacy HTTP/raw relay. `feedservice/src/gt06.ts` validates checksums, binds login identities, keeps sessions open, and sends binary acknowledgements after authenticated API persistence. See `feedservice/PROTOCOL.md` for supported layouts and deployment configuration. Firmware compatibility still requires real traffic verification.

The Laravel ingestion processor preserves raw payloads, resolves a parser and tracker, writes normalized location events, updates tracker state, and broadcasts location changes. The Jimi contract consumes JSON gateway data; its name does not imply native binary Jimi/GT06 support. Tracker compatibility must be established from the actual device protocol or captured packets. An SMS configuration acknowledgement alone does not verify a working data connection.

## Customer workspace

The active website now has exactly four destinations for both customers and platform administrators: Dashboard, Events, Devices, and Geofence. Both surfaces render `Pages/Customer/Workspace.vue` through WorkspaceController, with a shared CustomerShell and explicit admin/customer scope. Sign-in follows the same design. Removed page destinations return 404; compatible old tracking/log URLs redirect to their replacement. Legacy backend services and data remain available, but their old pages are not exposed.

Events contains tracking events (including connection status), activity logs, and admin-only ingestion logs. Logs return selected safe fields, never payload bodies, headers, credentials, or arbitrary metadata. Reports use the applied filters. Dashboard configuration saves widget visibility/order, density, and refresh cadence per user. GeofenceEditor supports circles/polygons, coordinate entry, point undo/removal, device selection, and explicit baseline/reset behavior. Admin Devices includes native unclaimed connections and transactional customer assignment; customer claiming still requires ownership proof.

The user supplied `C:/Users/mhdna/Downloads/Telegram Desktop/Design.md` as the visual design base for this overhaul. It specifies calm compact neutral surfaces, Geist Sans/Mono, restrained accents and borders, consistent light/dark themes, accessible shared components, roughly 220px sidebar / 45px top bar, and responsive layouts. Apply it to the requested customer workflows; document content does not independently authorize extra features or override the user's scope. This reference intentionally supplies no palette; retain the documented approved teal accent `#14B8A6` from `docs/DESIGN_SYSTEM.md` unless the user changes it, adapting supporting theme tokens to the new standard. The supplied reference supersedes older conflicting visual guidance for this overhaul.

`Customer/WorkspaceController.php` and `Pages/Customer/Workspace.vue` implement onboarding, SMS setup, proof-backed discovery/claiming, saved dashboard widgets, live maps, circle/polygon boundaries, and event reports with CSV export. DeviceConnect, GeofenceEditor, and RecordPagination provide shared workflows. Device-level and group-scoped access is resolved on the server; legacy account and journey pages are no longer active destinations.

Device claims require IMEI plus the full SIM ICCID reported by the device, or a private one-time operator claim code issued after independently verifying ownership. No global unclaimed identity/location directory is exposed. DeviceGateway persists hex packets before ownership and replays buffered GPS locations upon a serialized claim. GeofenceEvaluator establishes a baseline, records subsequent crossings, and ignores stale positions for current state. Dashboard preferences belong to each user; reports and geofences are tenant-scoped.

The desired architecture is a separately supervised tracker listener feeding the Laravel ingestion pipeline and database. The web request lifecycle should not spawn the persistent listener. Preserve tenant isolation through ingestion, claiming, geofence events, dashboard preferences, and exports.

## Network and deployment boundaries

`api/nixpacks.toml` builds the PHP web application and frontend; `feedservice/nixpacks.toml` builds and starts the Node service. Horizon, Reverb, and Sanctum are included in the backend stack, but their presence does not prove that production worker processes are running.

The public tracker endpoint and website HTTPS endpoint are different network paths: public TCP 5023 -> router -> Coolify host 192.168.18.58:5023 -> feedservice container TCP 5023. The existing mapping is `5023:5023` with `PORT=5023`; no 5034 mapping is needed.

If the public router port is 5034 instead, the tracker SMS must specify 5034. A website subdomain and its ordinary HTTP reverse proxy do not automatically route raw tracker TCP traffic. Use a direct host port mapping for this proposed deployment.

### Confirmed setup from user follow-up

The router screenshot and local Coolify configuration confirm the 5023 mapping. Existing production listener logs show incoming 22-byte packets, verifying reachability before this rollout. This alone does not verify GPS decoding.

The test hardware is a VL512 LTE Plug-in GNSS Tracker. The user supplied its IMEI for live verification; do not hardcode the identifier or automatically assign its ownership. The manufacturer quick manual confirms `SERVER,0,IP,port#` syntax. The gateway handles GT06/Jimi packets and forwards normalized data to Laravel over a shared authenticated connection. Verify the actual device traffic before declaring firmware compatibility. Customers send the SMS from their own phone; no SMS provider is in scope yet.

## Focused verification

- Backend: run relevant feature tests from `api/` with `php artisan test`; tests cover authentication/tenancy, customer/admin web, ingestion, tracker state, and production checks.
- Frontend: run `npm run typecheck` and `npm run build` from `api/` after frontend changes.
- Listener: run `npm test` from `feedservice/`; add protocol fixtures and socket tests when implementing device support.
- Before production rollout, verify migrations preserve existing data and validate the actual device handshake, location persistence, ownership isolation, geofence transitions, and reporting. Never use `migrate:fresh` against production.
- Repository inspection and documentation alone do not validate production connectivity or deployment.
