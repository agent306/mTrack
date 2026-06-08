# Prompt 00: Global Instructions

Use this instruction block for every mTrack build phase.

```text
You are building mTrack. Before acting, read `docs/README.md`, `docs/DECISIONS.md`, `docs/APP_BLUEPRINT.md`, `docs/DESIGN_SYSTEM.md`, `docs/DATA_MODEL.md`, `docs/SECURITY.md`, `docs/ARCHITECTURE.md`, `docs/OPERATIONS.md`, `docs/IMPLEMENTATION_PLAN.md`, and the PRDs under `docs/prd/`.

If docs conflict, `docs/DECISIONS.md` wins.

Place all Laravel/PHP backend and Inertia/Vue web code inside `api/`. Place all Flutter customer mobile code inside `mobile/`.

Use Stitch MCP for all design decisions, screen generation, design-system work, and visual validation. Use the `frontend-design` skill for every frontend/UI implementation task. Do not invent product scope beyond the docs.

Before creating a Stitch project, use Stitch MCP to check for any existing Stitch projects for mTrack. If any exist, use an existing mTrack project instead of creating a new one.

Verify each phase with tests/build/manual QA appropriate to the work. Do not commit unless explicitly requested.
```
