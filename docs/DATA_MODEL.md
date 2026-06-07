# mTrack Data Model

This document defines the conceptual data model. It is framework-agnostic but intended for Laravel with PostgreSQL and Redis.

## Core Tenancy

### Tenant

Represents a customer organization or deployment boundary.

Key fields:

- id.
- name.
- status.
- billing status.
- raw payload retention preset: 30, 90, 180, or 365 days.
- created timestamps.

### User

Represents a passwordless user account.

Key fields:

- id.
- tenant id when customer-scoped.
- name.
- email.
- auth provider: magic link or Google.
- last login timestamp.
- status.

### Role

Custom tenant role with module and fleet/group permissions.

Key fields:

- id.
- tenant id.
- name.
- permissions matrix.
- created timestamps.

Permission values:

- hide.
- view.
- edit.

Permission scopes:

- module.
- fleet group.
- individual tracker/device where needed.

## Tracking Domain

### Tracker Device

The first-class tracked entity in v1.

Key fields:

- id.
- tenant id.
- display name.
- business label/type.
- contract key/version.
- status.
- last event id.
- last seen timestamp.
- metadata.

### Fleet Group

Groups tracker devices for dashboards, filtering, permissions, and public/private discovery.

Key fields:

- id.
- tenant id.
- name.
- visibility: public or private.
- tracker membership.

### Normalized Location Event

Canonical location event produced by a parser contract.

Key fields:

- id.
- tenant id.
- tracker device id.
- raw payload id.
- parser contract key/version.
- event timestamp.
- received timestamp.
- latitude.
- longitude.
- speed.
- heading.
- altitude.
- accuracy.
- status metadata.
- normalized metadata.

### Raw Payload

Stored raw ingestion input.

Key fields:

- id.
- tenant id when resolved.
- tracker device id when resolved.
- parser contract key/version when resolved.
- received timestamp.
- headers selected for diagnostics.
- body content.
- body content type.
- processing status.
- rejection reason when failed.

Raw payload retention follows tenant preset.

## Geofencing And Alerts

### Geofence

Key fields:

- id.
- tenant id.
- group id.
- name.
- shape type: polygon or circle.
- shape geometry.
- entrance alert enabled.
- exit alert enabled.
- optional speed limit.

### Alert Event

V1 alert types:

- geofence entrance.
- geofence exit.
- overspeed.
- online.
- offline.
- stale/no-data.

## Billing

### License Plan

Defines base features and device license rules.

### License Allocation

Tracks active device license count for a tenant.

### Invoice Or License Request

Represents add/renew license intent and amount.

### Payment Slip

Stores uploaded customer proof for manual approval.

Statuses:

- pending.
- approved.
- rejected.

Rejected payments require a reason.

## Audit

Audit logs are retained indefinitely.

Audit all operational changes, including:

- login/logout.
- user and role changes.
- tracker/device changes.
- fleet group changes.
- geofence changes.
- alert setting changes.
- payment and license changes.
- parser contract deployment references.
- replay actions.
- exports.
- token generation or rotation.
