# PRD: Tracking Application Experience

## Objective

Provide administrator desktop, customer desktop, and mobile interfaces for monitoring fleets/trackers, viewing location history, managing devices, handling customer billing, configuring geofences, reviewing reports, and investigating ingestion issues.

## Structural Source

The approved app structure is captured in `../APP_BLUEPRINT.md`. Visual design must be produced separately.

## Requirements

### Dashboard

- Show live operational summary.
- Surface active, stale, and alerting tracked entities.
- Show ingestion health at a glance.
- Show fleet status, geofence stats, moving hours, speed graph, and recent events for customer dashboards.
- Show current month customer/payment data for administrator dashboards.

### Entity List

- Show tracked entities with current status, last known location time, and assigned device state.
- Support filtering by status and search.

### Entity Detail

- Show latest normalized location.
- Show event timeline.
- Show assigned device and contract status.
- Provide access to related alerts and diagnostics.

### Device Detail

- Show assigned entity, latest ingestion status, active contract, credentials/endpoint metadata, and recent failures.

### Contract Detail

- Show version, field mappings, validation state, sample test results, and publish status.

### Map View

- Show current and historical location data.
- Distinguish live, stale, invalid, and historical states.
- Provide non-map access to key event details.
- Web maps use OpenStreetMap. Flutter/mobile uses Google Maps or Apple Maps based on platform.

### Device And Contract Admin

- Admins can view devices, assigned entities, active contracts, ingestion status, and recent failures.
- Admins can test sample payloads against contracts before publishing.

### Billing And Customer Admin

- Administrators can review customers, block customers, renew payment, add licenses, acknowledge new customers, and approve/reject payments.
- Customers can view licenses, add licenses, renew licenses, inspect active devices, and proceed to payment.

### Settings And Permissions

- Customers can manage dashboard preferences, geofence settings, preference/API token, users, roles, device logs, and audit logs.

### Diagnostics

- Users with permission can inspect raw ingestion records, normalized event output, rejection reasons, replay status, and audit records.
- Contract creation/editing is not exposed to customers/admins in v1; contracts are developer-managed.

### Playback And Trip Analytics

- Users can view animated replay, route tables, stops, moving/idle segments, speed graph, trip summary, and exports.

## Acceptance Criteria

- A user can find a tracked entity and inspect its current status.
- A user can view the latest location and event timeline.
- An admin can inspect why a device payload failed.
- Map-heavy screens are usable without relying only on color.
