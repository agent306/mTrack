# mTrack Documentation

mTrack v1 is a fleet tracking SaaS and customer fleet portal for admin web, customer web, and customer mobile surfaces.

This directory defines the product before implementation starts.

## Repository Layout

- `api/` - Laravel/PHP backend and Inertia/Vue web app.
- `mobile/` - Flutter customer mobile app.
- `docs/` - product, design, architecture, PRD, prompt, and operations documentation.
- `api/nixpacks.toml` - Coolify/Nixpacks deployment config for the Laravel app.

## Documents

- `PRODUCT.md` - full product document and system overview.
- `DECISIONS.md` - canonical product, technical, security, and operational decisions.
- `APP_BLUEPRINT.md` - standalone app structure captured from the approved blueprint.
- `DESIGN.md` - product design direction to replace the unapproved Figma styling.
- `DESIGN_SYSTEM.md` - production design system extracted from the approved captured blueprint.
- `DATA_MODEL.md` - conceptual domain and persistence model.
- `SECURITY.md` - authentication, authorization, ingestion, and parser safety model.
- `OPERATIONS.md` - deployment, runtime, observability, runbooks, and backups.
- `IMPLEMENTATION_PLAN.md` - phased build sequence.
- `AGENTS.md` - engineering and product agent roles for building mTrack.
- `SKILL.md` - working instructions for contributors and AI agents.
- `ARCHITECTURE.md` - system architecture and major services.
- `ROADMAP.md` - staged delivery plan.
- `PRODUCTION_READINESS.md` - production checklist, tradeoffs, and pre-build conversion tasks.
- `PRODUCTION_QUESTIONNAIRE.md` - completed interview decisions that shaped v1 scope.
- `api/DEVICE_CONTRACTS.md` - device payload contract and normalization rules.
- `prd/CORE_PLATFORM_PRD.md` - core platform PRD.
- `prd/DEVICE_INGESTION_PRD.md` - HTTP ingestion and contract pipeline PRD.
- `prd/TRACKING_APP_PRD.md` - mobile tracking experience PRD.

## Source Of Truth

The source of truth for implementation is this documentation set, especially `DECISIONS.md`, `APP_BLUEPRINT.md`, `DESIGN_SYSTEM.md`, `DATA_MODEL.md`, `SECURITY.md`, `ARCHITECTURE.md`, and the PRDs. The relevant product structure and design-system evidence have been captured here, so implementation should not depend on reopening Figma.
