# mTrack App Blueprint

## Status

This document is the approved structure source for implementation and replaces the need to reference the original Figma export.

The captured blueprint confirms three product surfaces: administrator desktop, customer desktop, and mobile app.

## Product Shape

mTrack is a fleet/tracker monitoring platform for administrators and customers. The core experience is built around customers, fleets/trackers, devices, normalized location events, maps, playback, reports, geofences, billing/licenses, users, roles, audit logs, and raw device logs.

The visible structure supports these major jobs:

1. Understand fleet status and operational metrics from dashboards.
2. View fleets live on a map.
3. Replay historical movement for a selected fleet.
4. Review geofence, overspeed, device-status, route, device-log, analysis, and audit reports.
5. Manage devices/trackers, fleet groups, customers, payments, geofences, users, roles, and dashboard preferences.
6. Diagnose raw tracker payloads and ingestion behavior.
7. Manage licenses, renewals, payment approval, and customer access.

## Canvas Structure Captured

The captured blueprint contains 86 pages. Page 1 lists global administrator requirements. Pages 2-21 cover administrator desktop. Pages 22-57 cover customer desktop. Pages 58-86 cover mobile app.

### Administrator Desktop Modules

- Dashboard: current month data, live stats, customer recent logs, recent events.
- Live: fleet map with fleet/event side panel, grouped fleets, context menu actions.
- Playback: customer/fleet/date filters and map route playback.
- Events: geofence, over-speed, and device event tables.
- Devices: new discovery, assigned trackers, assign tracker modal, replace/unlink flows.
- Geofence: geofence list, add/modify geofence, polygon/circle drawing, entrance/exit/max-speed alert settings.
- Routes: route table and route map view.
- Customers: all/active/expired/new customer tabs, customer details, block, renew, add license, acknowledge.
- Payments: new/all/upcoming tabs, slip review, approve, verified, rejected.
- Logs: tracker raw payload/device logs filtered by tracker/protocol/identifier.

### Customer Desktop Modules

- Dashboard: fleet status, geofence stats, moving hours, speed graph, events.
- Live: map with fleet/event tabs, grouped fleet list, selected fleet context menu.
- Playback: fleet/date filters, route playback controls, speed multiplier, timeline slider.
- Events: geofence, over-speed, and device status event views.
- My Fleets: fleet list, device groups, edit tracker side panel, group management, public/private discovery.
- Geofence: geofence list, map preview, add/modify geofence.
- Analysis: dashboard-like analytics filtered by group, fleet, and date range.
- Routes: route table and expandable map detail.
- Setting: dashboard preferences, geofence settings, preference/API token, users, roles, device logs, audit log.
- Billing: license, add license, renew license, active/expired status, active devices, payment process.
- Audit Log: user/action/date filtered activity history.

### Mobile Modules

- Dashboard: fleet status, geofence stats, moving hours, speed graph, events.
- Reports: report landing page plus geofence, overspeed, devices status, routes, logs, device logs, audit log, and analysis.
- Live: map with search, filters, add action, fleet markers, selected fleet detail sheet.
- Playback: fleet/date search, map route playback, play/pause, step controls, speed multiplier, progress count.
- Setting: dashboard, geofence, preference/API token, users, roles.

### 1. Top Structural Cluster

The top cluster appears to define higher-level dashboard and overview screens. It includes analytics/card layouts, list/table-like layouts, detail panels, and at least one map/image-heavy screen.

Use this cluster as the structural source for:

- Operations dashboard.
- Metric summary cards.
- Activity/status lists.
- Admin overview screens.
- Map preview or highlighted tracking panel.
- Detail or modal states connected to overview data.

### 2. Middle Horizontal Flow

The middle cluster appears to show a sequential mobile user journey. The screens are arranged horizontally, with branching states and repeated white mobile pages using teal/green accents plus several darker map/detail views.

Use this cluster as the structural source for:

- Primary navigation flow.
- Entity list to entity detail path.
- Detail to map/tracking path.
- Form or action subflows.
- Confirmation, detail, or modal branches.

### 3. Main Lower Flow

The largest cluster appears to represent the most complete app flow. It contains many list, detail, form, map, modal, and branch-state screens.

Use this cluster as the structural source for the main production app inventory.

### 4. Bottom Exploration Cluster

The bottom cluster appears lower-fidelity and exploratory. It contains repeated narrow mobile layouts and small page/component variations.

Use this cluster as structural inspiration for reusable states and components only. Do not treat each bottom-cluster variant as a separate final page unless a product requirement confirms it.

## Canonical Page Inventory

### Access And Setup

These pages are required for a production app even if not clearly readable in the artifact:

- Sign in.
- Organization selection or workspace entry.
- User invitation acceptance.
- Basic account/settings entry.

### Administrator Dashboard

- Current month data cards: total customers, expired this month, expiring soon, new customer, pending payments, total received.
- Live stats cards: idle, moving, offline, on battery, total fleets, average speed, speed violation, total distance, maximum speed, trips.
- Customer recent logs.
- Recent events.

### Customer Dashboard And Analysis

- Fleet status.
- Geofence stats.
- Moving hours chart.
- Speed graph.
- Events list.
- Date/group/fleet-filtered analysis variant.

### Dashboard And Monitoring

- Operations dashboard.
- Status summary screen.
- Recent activity screen or panel.
- Alert summary screen or panel.
- Ingestion health summary screen or panel.

### Fleets And Trackers

- Tracked entity list.
- Tracked entity search/filter state.
- Tracked entity detail.
- Tracked entity current status panel.
- Tracked entity timeline/history.
- Tracked entity create/edit form.
- Tracked entity assignment screen for linking a device.
- Fleet group management.
- Public/private fleet discovery toggle.
- Edit tracker side panel with Basic Info, Geofence, and Alerts tabs.

### Map And Location

- Live map view.
- Entity-focused map detail.
- Location history/route view.
- Event detail from map or timeline.
- Empty/no-location state.
- Stale-location state.
- Invalid/rejected-location state.

### Reports

- Geofence report.
- Overspeed report.
- Device status report.
- Routes report.
- Logs report.
- Device logs report.
- Analysis report.
- Audit log report.

### Devices

- Device list.
- Device detail.
- Device create/edit form.
- Device credential or endpoint screen.
- Device assignment screen.
- Device ingestion status view.
- Device error/diagnostic view.
- New discovered tracker list.
- Assigned tracker list.
- Assign tracker modal.
- Replace tracker action.
- Unlink tracker action.

### Contracts And Normalization

- Contract list.
- Contract detail.
- Contract create/edit builder.
- Field mapping screen.
- Sample payload test screen.
- Validation result screen.
- Contract version history.
- Contract publish confirmation.

The PDF shows raw device logs and protocol/identifier filtering. V1 does not include an app contract-builder UI because parser contracts are developer-managed internal PHP classes.

### Alerts And Geofences

- Alert list.
- Alert detail.
- Alert create/edit rule form.
- Geofence list.
- Geofence create/edit screen.
- Geofence map selection screen.
- Alert event history.
- Per-tracker geofence alerts.
- Per-tracker max speed limit.
- Public geofence alert.
- Engine on/off alerts.
- Online/offline alerts.

### Diagnostics And Audit

- Raw ingestion log list.
- Raw payload detail.
- Normalization result detail.
- Rejected payload detail.
- Replay payload confirmation.
- Audit log list.
- Audit log detail.

### Settings And Administration

- Organization settings.
- User management.
- Role/permission management.
- API/device credential management.
- Data retention settings.
- Billing and license-plan administration.

### Billing And Licensing

- License overview.
- Add license.
- Renew license.
- Active devices under license.
- Active/expired license state.
- Proceed payment action.
- Administrator payment approval flow.

## Reusable Page Templates

### List Template

Used for entities, devices, contracts, alerts, geofences, ingestion logs, and audit logs.

Required structure:

- Header with page title and primary action.
- Search/filter area when the dataset can grow.
- Rows/cards with primary label, status, last activity, and secondary metadata.
- Empty, loading, error, and no-permission states.

### Detail Template

Used for tracked entities, devices, contracts, alerts, raw payloads, normalized events, and audit records.

Required structure:

- Summary header.
- Current status.
- Key metadata.
- Related timeline or activity.
- Primary actions.
- Diagnostics or linked records where relevant.

### Form Template

Used for creating/editing entities, devices, contracts, alerts, geofences, users, and settings.

Required structure:

- Clear title and purpose.
- Grouped fields.
- Inline validation.
- Save/cancel actions.
- Confirmation for destructive or publishing actions.

### Map Template

Used for live tracking, location history, entity focus, geofence selection, and event inspection.

Required structure:

- Map surface.
- Current entity/location context.
- Status overlay or bottom sheet.
- Timeline/event access.
- Non-map textual fallback for critical data.

### Modal/Overlay Template

Used for confirmations, quick details, validation results, and destructive actions.

Required structure:

- Focused title.
- Short explanatory content.
- Primary and secondary actions.
- Clear dismissal behavior.

## Required State Model

Every major page family should define these states before implementation:

- Default populated state.
- Empty state.
- Loading state.
- Error state.
- No permission state.
- Offline or stale data state where relevant.
- Validation failure state for forms/contracts.
- Confirmation state for destructive or publishing actions.

## Navigation Model

The visible structure suggests a mobile-first app with a small number of primary navigation areas and many drill-down flows.

Recommended primary navigation:

- Dashboard.
- Tracking.
- Devices.
- Alerts.
- Admin or Settings.

Contract management and diagnostics may live under Admin for normal users, but should remain first-class enough for integration administrators.

## Implementation Rule

Build from this blueprint and the PRDs. Do not depend on the Figma file for implementation details after this point.
