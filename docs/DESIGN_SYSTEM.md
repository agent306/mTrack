# mTrack Design System

## Status

This design system is derived from the approved captured blueprint and supersedes the earlier placeholder design direction. It preserves the product structure and interaction patterns from that review while formalizing them into production-ready rules.

The design direction is operational SaaS: calm, high-density, map-first where needed, and optimized for monitoring fleets, trackers, events, billing, customers, and diagnostics.

## Product Surfaces

### Administrator Desktop

Administrator screens use a persistent left sidebar and top header. The observed administrator modules are:

- Dashboard.
- Live.
- Playback.
- Events.
- Devices.
- Geofence.
- Routes.
- Customers.
- Payments.
- Logs.

### Customer Desktop

Customer screens use the same desktop shell with customer-specific modules:

- Dashboard.
- Live.
- Playback.
- Events.
- My Fleets.
- Geofence.
- Analysis.
- Routes.
- Setting.
- Billing.
- Audit Log.

### Mobile App

Mobile screens use a top bar and persistent bottom navigation:

- Dashboard.
- Reports.
- Live.
- Playback.
- Setting.

Reports contains Geofence, Overspeed, Devices Status, Routes, Device Logs, Analysis, and Audit Log.

## Visual Tokens

Use the following modernized token baseline. These values preserve the captured teal/dark operational feel while making the system implementation-ready.

### Token Values

- Brand accent: `#14B8A6`.
- Brand accent hover: `#0F9F8F`.
- Primary dark: `#0B1026`.
- Page background: `#F4F6FA`.
- Surface: `#FFFFFF`.
- Muted surface: `#EEF2F7`.
- Border: `#D8DEE8`.
- Text primary: `#111827`.
- Text secondary: `#6B7280`.
- Success: `#22C55E`.
- Warning: `#F97316`.
- Danger: `#EF4444`.
- Info: `#67E8F9`.
- Neutral: `#6B7280`.

### Typography Tokens

- Font family: Inter for web; platform default plus matching Inter-style scale for Flutter.
- Display: 32px / 40px / 700.
- Page title: 24px / 32px / 700.
- Section title: 18px / 28px / 700.
- Body: 14px / 22px / 400.
- Small: 12px / 18px / 400.
- Metric number: 28px / 34px / 700 with tabular numerals.

### Spacing Tokens

- 4px, 8px, 12px, 16px, 24px, 32px, 48px.

### Radius Tokens

- Small: 6px.
- Medium: 10px.
- Large: 16px.
- Sheet/dialog: 24px on mobile.

### Shadow Tokens

- Card: `0 1px 2px rgba(15, 23, 42, 0.08)`.
- Overlay: `0 12px 32px rgba(15, 23, 42, 0.18)`.
- Map floating panel: `0 8px 24px rgba(15, 23, 42, 0.16)`.

### Color Roles

- Primary accent: teal for selected navigation, selected tabs, primary actions, links, active checkmarks, and live/online emphasis.
- Primary dark: near-black/navy for high-emphasis buttons such as Save, Show, Continue, Add to Dashboard, and Proceed Payment.
- Page background: very light gray for mobile and neutral white/gray for desktop content shells.
- Surface: white cards, panels, tables, map overlays, dialogs, and forms.
- Muted surface: pale gray for inactive tabs, list cards, disabled buttons, and filter panels.
- Border: light gray dividers around cards, rows, inputs, and panels.
- Text primary: dark navy/charcoal.
- Text secondary: gray for timestamps, subtitles, helper text, inactive labels, and metadata.
- Success/live: green or teal-green for Online, Active, Entrance, Moving, enabled toggles, and successful connection.
- Warning/motion/idle: orange for Idle, overspeed badges, and warning-like operational metrics.
- Danger/offline: red for Offline, Expired, rejected, exit, and destructive states.
- Neutral/battery: gray for On Battery, disabled, secondary state, or inactive controls.

### Typography

- Use one modern sans-serif family across the product.
- Headings must be compact and operational, not decorative.
- Numeric metrics should use strong weight and tabular numerals where supported.
- Table/list metadata should remain readable at dense sizes.
- Mobile titles should be larger and bolder than row text.

### Spacing And Density

- Desktop is dense and admin-oriented, with compact tables, cards, filters, and sidebars.
- Mobile is spacious enough for touch controls, with large tappable cards, bottom navigation, and modal sheets.
- Use consistent vertical rhythm between filter bars, tab rows, tables, and action footers.
- Preserve generous whitespace in empty desktop panels, but avoid leaving action-critical pages under-specified.

### Shape

- Cards, inputs, tabs, badges, and buttons use modest rounded corners.
- Map markers use pin shapes with status colors.
- Mobile sheets and dialogs use larger rounded corners and elevated shadows.

## Core Components

### App Shell

Desktop shell:

- Logo area.
- Left module sidebar.
- Top header showing product/customer context, visibility state such as Public, and account dropdown.
- Scrollable content region.

Mobile shell:

- Top bar with menu/search/filter/add controls depending on page.
- Full-screen content region.
- Persistent bottom tab bar.

### Navigation Item

States:

- Default.
- Selected teal.
- Hover/focus.
- Disabled/no-permission.

### Tabs And Segmented Controls

Used for event types, customer/payment filters, billing tabs, settings sections, fleet/device grouping, and tracker editor sections.

Required states:

- Selected.
- Unselected.
- Disabled.
- Count badge where applicable.

### Metric Cards

Used for customer counts, fleet status, speed, distance, trips, payments, and dashboard summaries.

Required content:

- Label.
- Large value.
- Optional helper text.
- Optional status color or icon.

### Status Badges

Observed status vocabulary:

- Online.
- Offline.
- Idle.
- Moving.
- On Battery.
- Active.
- Expired.
- Verified.
- Rejected.
- Entrance.
- Exit.
- On.
- Off.
- Recovered.
- Unknown.
- Stopped.
- Overspeed value such as 80km/h.

Badges must pair color with readable text.

### Data Tables And Lists

Used for customers, payments, events, routes, logs, devices, fleets, users, roles, and audit history.

Required patterns:

- Filter row above table.
- Repeated row/card items.
- Inline badges.
- Per-row action menus.
- View/action buttons.
- Empty state.
- Loading state.
- Error state.

### Filters

Common filter controls:

- Customer selector.
- Fleet selector.
- Group selector.
- Status selector.
- Type selector.
- Protocol selector.
- Identifier selector.
- Date range.
- Search field.
- Export action.
- Show/Search/Filter button.

### Forms

Observed forms include customer details, device assignment, tracker editing, geofence editing, billing license purchase/renewal, API token generation, dashboard preferences, users, roles, and fleet groups.

Required patterns:

- Label above or inside input.
- Select controls with chevrons.
- File upload button.
- Toggle switches.
- Primary save action.
- Secondary cancel action.
- Inline validation.
- Confirmation for destructive actions.

### Map Surfaces

Map surfaces appear in live tracking, playback, routes, and geofence editing.

Required patterns:

- Marker states for moving, offline, idle, selected, and current position.
- Floating labels showing fleet/entity name and speed or recency.
- Legend where multiple marker states are present.
- Search/filter overlay.
- Detail sheet/card for selected tracker.
- Route polyline for playback.
- Polygon/circle drawing for geofences.
- Map action controls for current location, notifications, and view tools where applicable.

### Charts

Observed charts:

- Moving hours bar chart.
- Speed graph line chart.

Required chart behavior:

- Clear axes and units.
- Fleet/entity legend.
- Time range indication.
- Non-visual text summary for accessibility.

### Modals And Side Panels

Observed overlays:

- Assign tracker.
- Edit tracker.
- Add fleets to dashboard.
- Add fleets to group.
- Expanded route/map row.
- Role permission editor.

Required behavior:

- Focus trapping.
- Clear close/cancel behavior.
- Primary action at bottom.
- Scroll inside panel when content is long.

## Responsive Rules

Desktop uses sidebar navigation and wide data tables. Mobile uses bottom navigation, stacked filters, cards, and full-screen map surfaces.

Do not simply shrink desktop tables into mobile. Mobile reports and logs should use card rows and drill-down patterns.

## Accessibility Rules

- All status colors require text labels.
- All map markers require list or detail equivalents.
- Toggles require visible labels and persisted state feedback.
- Tables must be keyboard navigable on desktop.
- Dialogs and side panels must manage focus.
- Date, speed, distance, coordinates, and timestamps must be copyable where operationally useful.

## Implementation Decisions

- Desktop web uses Laravel/Inertia/Vue/Tailwind/Headless UI/Lucide.
- Customer mobile uses Flutter.
- Web map styling is based on OpenStreetMap.
- Flutter mobile uses Google Maps on Android and Apple Maps on iOS where practical.
- Icon set is Lucide for web; Flutter should use the closest equivalent icon set.
- Dark mode is out of scope for v1 unless explicitly added later.
