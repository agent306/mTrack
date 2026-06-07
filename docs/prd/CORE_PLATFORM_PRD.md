# PRD: Core Platform

## Objective

Build the foundational mTrack platform for organizations, users, tracked entities, device management, dashboards, and auditability.

## Users

- Organization owners.
- Operations managers.
- Field operators.
- Integration administrators.
- Support and audit users.

## Requirements

### App Blueprint

- The implementation must follow `../APP_BLUEPRINT.md` for page families, templates, navigation, and required states.
- The implementation must not depend on the Figma file after documentation capture.

### Organizations And Access

- Users can belong to an organization.
- Roles control access to admin, monitoring, integration, and audit features.
- Organization data must be tenant-isolated.
- The data model must support hybrid deployment: single-tenant or multi-tenant SaaS.

### Tracked Entities

- Users can create and manage tracked entities.
- Entities can be assigned to devices.
- Entity detail pages show current status and recent location history.

### Dashboard

- Users can view operational summary, active entities, stale entities, alerts, and ingestion health.
- Dashboard cards should be derived from normalized events and system health metrics.

### Billing And Licenses

- Licenses control how many trackers/devices a customer can activate.
- Customers can add or renew licenses.
- Administrators can approve, verify, or reject payments.
- Payment processing starts with manual slip approval and should be designed for payment gateway integration later.

### Audit

- Important admin actions must be recorded.
- Users with permission can inspect device, contract, and ingestion history.
- All operational changes must be audit logged.

## Acceptance Criteria

- A tenant cannot access another tenant's data.
- A tracked entity can be connected to a device.
- A user can inspect the latest normalized location for an entity.
- Admin actions have audit records.

## Scope Closure

No open v1 scope questions remain. Implementation details are defined in `../DECISIONS.md`, `../DATA_MODEL.md`, `../SECURITY.md`, and `../OPERATIONS.md`.
