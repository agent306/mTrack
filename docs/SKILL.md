# mTrack Build Skill

Use this skill when planning, designing, or implementing mTrack.

## Fixed Context

- mTrack is a tracking and monitoring platform.
- `docs/DECISIONS.md` is the canonical decision source.
- `docs/DESIGN_SYSTEM.md` is the design-system source.
- Laravel backend/web code belongs in `api/`.
- Flutter mobile code belongs in `mobile/`.
- The captured app structure now lives in `docs/APP_BLUEPRINT.md`; do not depend on the Figma file during implementation.
- Any HTTP-capable tracking device may send any HTTP body.
- Payloads must be normalized through internal PHP parser contracts before becoming system events.
- Raw payloads, contract versions, validation decisions, and normalized events must be auditable.

## Working Rules

1. Follow `DECISIONS.md` when any document conflicts.
2. Use `APP_BLUEPRINT.md` as the implementation structure source.
3. Treat device payloads as untrusted external input.
4. Never assume a universal device schema.
5. Store raw ingestion records before normalization.
6. Normalize only through an explicit internal parser contract version.
7. Reject invalid payloads with inspectable diagnostics.
8. Keep tenant isolation central to every model and query.
9. Prefer explicit operational states over hidden behavior.

## Implementation Bias

- Contract-first ingestion.
- Strong audit trails.
- Clear admin diagnostics.
- Minimal vendor assumptions.
- Production observability from the first release.
- Passwordless user authentication.
- Tenant-aware data model from day one.

## Avoid

- Runtime user-uploaded parser code.
- Hardware-specific shortcuts in the core pipeline.
- Silent payload coercion that cannot be audited.
- Backward compatibility paths before a versioned contract model exists.
