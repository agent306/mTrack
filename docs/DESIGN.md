# mTrack Design Direction

## Design Status

The original Figma export has been reviewed, and the relevant product structure and design-system evidence have been captured in `APP_BLUEPRINT.md` and `DESIGN_SYSTEM.md`.

Use `DESIGN_SYSTEM.md` as the current design-system working source. Final token values still require approval.

## Design System Source

The design system has a production draft in `DESIGN_SYSTEM.md`, including exact baseline tokens for typography, color, spacing, radius, shadows, map styling, and iconography.

## Product Experience Goals

mTrack should feel operational, trustworthy, and precise. Users will rely on it for real-world tracking decisions, so the interface must emphasize clarity, confidence, status visibility, and fast investigation.

## Visual Direction

- Clean operational interface with strong information hierarchy.
- Neutral base palette with restrained status colors.
- Clear distinction between live, stale, invalid, and historical data.
- Map surfaces should be functional first, not decorative.
- Forms should be dense enough for admin efficiency but not visually cramped.
- Desktop should support dense operational administration.
- Mobile should prioritize quick fleet lookup, live map usage, playback, reports, and settings.

## Core Screen Types

- Dashboard summary.
- Tracked entity list.
- Tracked entity detail.
- Map/tracking view.
- Device list and detail.
- Contract builder/tester.
- Alert/geofence setup.
- Ingestion diagnostics.
- Settings and access control.

For full screen inventory, use `APP_BLUEPRINT.md`.
For component and token rules, use `DESIGN_SYSTEM.md`.

## Component System Needs

- Status badges for device, entity, event, and contract health.
- Event timeline rows.
- Data cards and metric cards.
- Map marker and cluster states.
- Contract field mapping rows.
- JSON/raw payload viewer.
- Validation result panels.
- Confirmation and diagnostic modals.
- Empty, loading, error, stale, and no-permission states.

## Accessibility Requirements

- Keyboard-accessible admin workflows.
- Color must not be the only status indicator.
- Map data must have non-map textual equivalents where practical.
- Timestamps and coordinates must be readable and copyable.
- Error states must explain what failed and what action is available.

## Design Risks

- Tracking products can become visually noisy if every event is emphasized equally.
- Map screens can hide critical details if the list/timeline view is weak.
- Contract builders can become too technical for operators; separate admin and monitoring experiences clearly.
