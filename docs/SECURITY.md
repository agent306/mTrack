# mTrack Security Model

## Authentication

Users authenticate without passwords.

Supported v1 methods:

- Email magic link.
- Google sign-in.
- Mobile JWT-style access with refresh.

Passwords are out of scope for v1.

## Authorization

mTrack uses custom roles with module and fleet/group permissions.

Permission levels:

- Hide: user cannot see the module/resource.
- View: user can read but not modify.
- Edit: user can create, update, or delete according to module rules.

Every query and mutation must enforce tenant isolation and role permissions.

## Device Ingestion Security

V1 starts with unsigned ingestion and contract-defined identity.

Required safeguards:

- Payload size limits.
- Rate limits.
- Parser-level identity resolution.
- Raw payload diagnostics.
- Abuse monitoring through logs/Horizon/server visibility.
- Rejection records for invalid or unresolved payloads.

API tokens can be regenerated manually. Regeneration revokes old tokens immediately.

## Parser Contract Safety

Parser contracts are internal PHP classes deployed with the app.

Rules:

- No runtime user-uploaded parser code.
- Parser changes require code review.
- Parser versions are immutable once used for normalization.
- Fixtures must cover valid payloads, invalid payloads, missing identity, invalid coordinates, and malformed bodies.

## Data Privacy

V1 tracks business assets/fleets only.

Raw payload retention is tenant-configurable: 30, 90, 180, or 365 days.

Audit logs are retained indefinitely.

Raw payloads may contain vendor-specific or accidental sensitive data. Access must be limited to permitted users and platform operators.
