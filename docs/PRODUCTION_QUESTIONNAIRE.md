# Production Readiness Questionnaire

This file records the completed interview queue and the decisions incorporated into the docs.

## Question Queue

1. What is the exact v1 product positioning?
2. Which app surfaces are in v1: administrator desktop, customer desktop, mobile app, or all three?
3. Should mTrack be multi-tenant SaaS from day one?
4. What are the required user roles and permission levels?
5. What tracked entities are first-class in v1?
6. What is the device identity model?
7. What payload content types must v1 support?
8. What contract format should be used for payload normalization?
9. Who can create, edit, publish, and roll back contracts?
10. What is the raw payload retention policy?
11. What map provider should be used?
12. What location update latency is acceptable?
13. What events and alerts are required in v1?
14. What geofence behavior is required in v1?
15. What playback/history behavior is required in v1?
16. What billing/licensing model should v1 implement?
17. What customer onboarding and approval flow is required?
18. What payment approval flow is required?
19. What audit log events are mandatory?
20. What reports and exports are required?
21. What compliance/privacy constraints apply to tracked people or assets?
22. What authentication methods are required for users and devices?
23. What production stack constraints or preferences exist?
24. What launch environment and operational SLOs are required?

## Answered Decisions

1. V1 positioning: combination of fleet tracking SaaS and customer fleet portal.
2. V1 surfaces: customer web, admin web, and customer mobile.
3. Tenancy: hybrid; data model must support tenant isolation from day one, while deployment can be single-tenant or multi-tenant.
4. Roles: admin plus customer roles.
5. First-class tracked entity: device/tracker itself, with optional business labels such as boat, buggy, vessel, or vehicle.
6. Device identity: contract-defined identity extraction.
7. Payload content types: any HTTP body.
8. Contract format: code/plugin contracts.
9. Contract management: developers only.
10. Raw payload retention: configurable per tenant.
11. Map providers: OpenStreetMap for web; Google Maps or Apple Maps for Flutter/mobile depending on platform.
12. Live latency: process and publish updates immediately after payload processing; actual update frequency depends on tracker/device cadence. Use WebSockets/gRPC where applicable.
13. V1 alerts: core tracking alerts: geofence entrance/exit, overspeed, online/offline, and stale/no-data.
14. Geofence scope: polygon, circle, groups, per-tracker assignments, entrance/exit alerts, and optional speed limits.
15. Playback/history: full trip analytics, including animated replay, route table, stops, moving/idle segments, speed graph, trip summary, and export.
16. Billing/licensing: hybrid license plans with base features plus per-device license counts and add/renew flows.
17. Customer onboarding: hybrid plus invite; admins can create/approve customers, and users can be invited by email or direct link.
18. Payment flow: manual slip approval first, payment gateway later.
19. Audit logs: all operational changes.
20. Reports/exports: PDF-visible report set plus CSV export for tables/logs.
21. Privacy/compliance: business asset/fleet tracking only for v1.
22. Authentication: SSO-ready user auth; mobile app uses long-lived JWT-style authentication with refresh. Device/integration auth remains compatible with contract-defined identity.
23. Stack: Laravel/PHP, Inertia, Vue, TypeScript, Tailwind, Headless UI, Lucide icons for web; Flutter for mobile.
24. Launch environment: Ubuntu Coolify server via Nixpacks using the `api/nixpacks.toml` config.
