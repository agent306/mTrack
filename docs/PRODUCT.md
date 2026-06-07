# mTrack Product Document

## Product Summary

mTrack v1 is a production-grade fleet tracking SaaS and customer fleet portal for organizations that need live location, playback, trip analytics, events, reports, geofences, billing, customer administration, and raw device diagnostics. The system accepts any HTTP body from any device capable of sending data to an endpoint, normalizes those payloads through developer-managed code/plugin contracts, and turns them into reliable geolocation events usable by the application.

The product is not tied to one hardware vendor, protocol shape, or payload schema. Device compatibility is achieved through contracts that describe how to authenticate, parse, validate, normalize, and enrich incoming payloads.

## App Structure Source

The app structure has been captured in `APP_BLUEPRINT.md` as the implementation source of truth. Implementation should not require the Figma file. The blueprint defines administrator desktop, customer desktop, mobile app, canonical page families, navigation, reusable templates, and required states.

## V1 Decisions

The canonical decision source is `DECISIONS.md`.

- Surfaces: admin web, customer web, and customer mobile.
- Tenancy: hybrid tenant-aware data model with flexible single-tenant or SaaS deployment.
- First-class tracked entity: tracker/device, with optional fleet/business labels.
- Maps: OpenStreetMap for web; Google Maps or Apple Maps for Flutter/mobile depending on platform.
- Billing: hybrid license plans with base features plus per-device license counts.
- Payments: manual slip approval first, gateway integration later.
- Privacy: business asset/fleet tracking only.
- Stack: Laravel/PHP, Inertia, Vue, TypeScript, Tailwind, Headless UI, Lucide icons, Flutter mobile, PostgreSQL, Redis, Horizon, and Reverb.

## Product Principles

1. Device-agnostic ingestion: any HTTP-capable device can be integrated.
2. Code/plugin contract-first normalization: raw payloads are never assumed to be system-ready.
3. Operational trust: every location event should be traceable back to raw input, device identity, contract version, and processing result.
4. Structure before style: `APP_BLUEPRINT.md` defines structure and `DESIGN_SYSTEM.md` defines the modernized visual system.
5. Production readiness from the start: auditability, rate limits, replay, diagnostics, and failure handling are core requirements.

## Primary Users

- Organization owner: configures workspace, users, billing, and top-level policies.
- Operations manager: monitors tracked entities, status, alerts, and history.
- Field operator: views assigned tracked entities and responds to operational events.
- Integration/admin user: configures devices, endpoint credentials, and payload contracts.
- Support/audit user: investigates ingestion failures and historical event trails.

## Core Entities

- Organization: tenant boundary for users, devices, entities, contracts, and events.
- User: authenticated person with role-based access.
- Tracking device: external sender that posts arbitrary payloads to mTrack.
- Device contract: developer-managed versioned code/plugin parser that transforms raw payloads into normalized events.
- Tracked entity: the business object being monitored, such as a vehicle, asset, shipment, person, or tool.
- Location event: normalized geolocation point with timestamp, coordinates, accuracy, and metadata.
- Alert: condition triggered by location, device health, missing data, or business rules.
- Geofence: defined area used for entry, exit, dwell, or compliance logic.
- Fleet group: customer-defined grouping of fleets, optionally public or private.
- License: paid entitlement controlling the number of active devices/trackers.
- Payment: customer billing/payment record requiring approval or verification.

## Core Product Areas

### Dashboard

The dashboard summarizes fleet/entity status, recent activity, alerts, ingestion health, and operational metrics. It should be structurally aligned with the Figma blueprint but visually redesigned.

### Tracking Views

Tracking views show current position, history, route/timeline, event metadata, and device status. Map-heavy screens are a core pattern but their current Figma visual style is not final.

### Device Management

Admins register devices, assign them to tracked entities, configure credentials, attach contracts, and inspect ingestion status.

### Parser Contract Management

Developers define parser contracts as internal PHP classes. Contracts identify devices, parse arbitrary HTTP bodies, normalize fields, validate output, and emit diagnostics. Customers and admins inspect results and logs but do not author contracts in v1.

### Alerts And Geofences

Users define geofences and alert rules for entry/exit, stale devices, invalid coordinates, overspeed, route deviation, and business-specific events.

### Audit And Diagnostics

Every received payload should be inspectable through raw payload, parsed output, contract version, validation result, normalized event, and downstream side effects.

## Canonical Page Families

- Access and setup.
- Dashboard and monitoring.
- Tracked entities.
- Map and location.
- Devices.
- Contracts and normalization.
- Alerts and geofences.
- Diagnostics and audit.
- Settings and administration.
- Billing and licensing.

## High-Level User Flows

1. Organization setup: create organization, invite users, define roles.
2. Device onboarding: create device, generate endpoint credentials, select or create contract.
3. Contract setup: define mappings, test with sample payloads, publish contract version.
4. Live ingestion: device posts payload, system validates and normalizes event.
5. Monitoring: user views dashboard, map, event timeline, and alerts.
6. Investigation: user traces bad or missing location data through ingestion logs.

## Non-Goals For Initial Build

- Hardware-specific SDKs.
- Native device firmware management.
- Visual implementation based directly on the current Figma styling.
- Complex route optimization unless explicitly prioritized later.

## Deferred Product Scope

- Payment gateway integration after manual slip approval.
- Password authentication.
- Personal tracking consent workflows.
- Runtime user-authored parser contracts.
- Complex route optimization unless explicitly prioritized later.
