# mTrack Design System Implementation

This file maps `docs/DESIGN_SYSTEM.md` into implementation primitives for web and mobile.

## Web Tokens

Web tokens live in `api/resources/css/app.css` as Tailwind v4 `@theme` variables.

- Colors: `brand`, `brand-hover`, `primary-dark`, `page`, `surface`, `muted-surface`, `line`, `body`, `muted`, `success`, `warning`, `danger`, `info`, `neutral`.
- Typography: `display`, `page-title`, `section-title`, `mtrack-body`, `mtrack-small`, and `metric`.
- Spacing aliases: `mtrack-1`, `mtrack-2`, `mtrack-3`, `mtrack-4`, `mtrack-6`, `mtrack-8`, `mtrack-12`.
- Radius: `mtrack-sm`, `mtrack-md`, `mtrack-lg`, `mtrack-sheet`.
- Shadows: `card`, `overlay`, `map-panel`.

## Web Components

Reusable Vue components live in `api/resources/js/Components`.

- Shell and navigation: `AppShell`, `MTrackSideNav`, `MTrackBottomNav`.
- Tabs and filters: `MTrackTabs`, `MTrackFilterBar`.
- Cards and badges: `MTrackMetricCard`, `MTrackBadge`.
- Tables and forms: `MTrackDataTable`, `MTrackFormField`.
- Overlays: `MTrackModal`, `MTrackMapOverlay`.
- Charts and states: `MTrackChartPanel`, `MTrackState`.

## Component Rules

- Desktop admin/customer screens use `AppShell` with dense tables, compact filters, metric cards, and side navigation.
- Mobile web and Flutter use bottom navigation, stacked filters, list/card rows, and sheet-style overlays.
- All status colors must pair with visible text labels through `MTrackBadge` or equivalent Flutter text.
- Map screens must pair marker visuals with `MTrackMapOverlay` details or list equivalents.
- Empty, loading, error, and no-permission states use `MTrackState`.
- Tables remain desktop-first. Mobile report/log screens should use cards and drill-down states instead of squeezed tables.

## Flutter Tokens

Flutter tokens and guidance live in `mobile/lib/theme/mtrack_theme.dart`.

- Use `MTrackTheme.light()` as the app theme.
- Use `MTrackTokens` for color, spacing, and radius constants.
- Use `MTrackTheme.panelDecoration()` for cards/panels.
- Use `MTrackTheme.statusColor(status)` for status badge/icon color mapping.

Dark mode remains out of scope for v1.
