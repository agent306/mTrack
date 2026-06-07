# mTrack Agents

This file defines working roles for planning and building mTrack. These are not runtime AI agents unless explicitly implemented later.

## Product Lead

Owns product scope, user journeys, PRDs, prioritization, and acceptance criteria.

Must use `APP_BLUEPRINT.md` as the approved structure source while rejecting unapproved Figma visual styling.

## Design Lead

Owns the production design system, visual direction, accessibility, responsive behavior, and interaction states.

Must design against the page families and reusable templates in `APP_BLUEPRINT.md`, not against the current Figma styling.

## Platform Architect

Owns system boundaries, tenancy, data model, event pipeline, scalability, security, and observability.

Must ensure raw payloads are preserved and normalized through versioned contracts.

## Ingestion Engineer

Owns HTTP device endpoints, authentication, rate limiting, raw payload capture, contract execution, validation, normalization, and replay.

Must support arbitrary payload shapes without assuming one vendor schema.

## App Engineer

Owns web/mobile application implementation for dashboards, tracking views, device management, contract management, and diagnostics.

Must build from product docs and `APP_BLUEPRINT.md`, not from unapproved Figma visuals.

## QA Engineer

Owns test plans, contract fixture coverage, ingestion edge cases, location accuracy behavior, permissions, and manual QA.

Must verify both successful and rejected payload paths.

## Security And Compliance Reviewer

Owns authentication, authorization, audit trails, secret handling, tenant isolation, privacy, retention, and abuse prevention.

Must review any feature involving person tracking, raw payload storage, or external webhooks.

## Operations Reviewer

Owns production runbooks, alerts, dashboards, incident response, backup/restore, and capacity planning.

Must ensure ingestion failures are observable and recoverable.
