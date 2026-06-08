<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Battery,
    Building2,
    CalendarX,
    Clock,
    CreditCard,
    Gauge,
    Layers,
    MapPinned,
    Navigation,
    PauseCircle,
    Receipt,
    Route,
    Siren,
    TrendingUp,
    UserPlus,
    WifiOff,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import type { Component } from 'vue';
import MTrackBadge from '../../Components/MTrackBadge.vue';
import MTrackMetricCard from '../../Components/MTrackMetricCard.vue';
import MTrackOpenStreetMap from '../../Components/MTrackOpenStreetMap.vue';
import MTrackState from '../../Components/MTrackState.vue';
import MTrackTabs from '../../Components/MTrackTabs.vue';
import AppShell from '../../Layouts/AppShell.vue';
import type { PageProps } from '../../types';
import { route } from 'ziggy-js';

type Tone = 'brand' | 'success' | 'warning' | 'danger' | 'info' | 'neutral';

type ModuleTab = {
    id: string;
    label: string;
    count?: number;
};

type Metric = {
    label: string;
    value: string | number;
    helper: string;
    icon?: string;
    tone: Tone;
};

type Tracker = {
    id: number;
    display_name: string;
    business_label: string | null;
    tenant_id: number;
    customer: string;
    status: string;
    contract: string;
    last_seen_at: string | null;
    latitude: number | null;
    longitude: number | null;
    speed: number | null;
};

type Customer = {
    id: number;
    name: string;
    status: string;
    billing_status: string;
    users_count: number;
    trackers_count: number;
    license_requests_count: number;
    payment_slips_count: number;
    created_at: string | null;
    acknowledged_at: string | null;
};

type Payment = {
    id: number;
    customer: string;
    status: string;
    amount: string;
    filename: string;
    request: string;
    devices: number;
    reviewed_at: string | null;
    rejection_reason: string | null;
    created_at: string | null;
};

type DiscoveredPayload = {
    id: number;
    identity: string;
    contract: string;
    status: string;
    reason: string | null;
    received_at: string | null;
    body_preview: string;
};

type AlertRow = {
    id: number;
    type: string;
    customer: string;
    tracker: string;
    geofence: string | null;
    occurred_at: string | null;
    resolved_at: string | null;
    severity: string;
};

type GeofenceRow = {
    id: number;
    name: string;
    customer: string;
    fleet_group: string | null;
    shape_type: string;
    speed_limit: string | null;
    entrance_alert_enabled: boolean;
    exit_alert_enabled: boolean;
    trackers_count: number;
};

type FleetGroupRow = {
    id: number;
    name: string;
    customer: string;
    visibility: string;
    trackers_count: number;
};

type RouteEvent = {
    id: number;
    customer: string;
    tracker: string;
    event_timestamp: string | null;
    latitude: number;
    longitude: number;
    speed: number | null;
    heading: number | null;
};

type RawPayloadRow = {
    id: number;
    customer: string;
    tracker: string | null;
    identity: string;
    contract: string;
    status: string;
    reason: string | null;
    received_at: string | null;
    body_preview: string;
};

type AuditRow = {
    id: number;
    action: string;
    tenant: string;
    actor_id: number | null;
    subject_type: string;
    subject_id: number | null;
    created_at: string | null;
    metadata: Record<string, unknown>;
};

type UserRole = {
    id: number;
    name: string;
    slug: string;
    scope: string;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    tenant_id: number | null;
    tenant: string;
    status: string;
    auth_provider: string;
    last_login_at: string | null;
    roles: UserRole[];
};

type RoleRow = {
    id: number;
    tenant_id: number | null;
    tenant: string;
    name: string;
    slug: string;
    scope: string;
    users_count: number;
    permissions: {
        modules?: Record<string, string>;
    };
};

type SettingRow = {
    label: string;
    value: string;
};

const props = defineProps<{
    activeModule: string;
    modules: ModuleTab[];
    dashboardMetrics: Metric[];
    liveStats: Metric[];
    trackers: Tracker[];
    customers: Customer[];
    payments: Payment[];
    discoveredPayloads: DiscoveredPayload[];
    alertEvents: AlertRow[];
    geofences: GeofenceRow[];
    fleetGroups: FleetGroupRow[];
    routeEvents: RouteEvent[];
    rawPayloads: RawPayloadRow[];
    auditLogs: AuditRow[];
    users: UserRow[];
    roles: RoleRow[];
    settings: SettingRow[];
    adminModules: string[];
    customerModules: string[];
    permissionLevels: string[];
}>();

const page = usePage<PageProps>();

const iconByName: Record<string, Component> = {
    Battery,
    Building2,
    CalendarX,
    Clock,
    CreditCard,
    Gauge,
    Layers,
    MapPinned,
    Navigation,
    PauseCircle,
    Receipt,
    Route,
    Siren,
    TrendingUp,
    UserPlus,
    WifiOff,
};

const customerFilter = ref('all');
const paymentFilter = ref('new');
const logFilters = reactive({
    tracker: '',
    contract: '',
    identity: '',
});
const roleDraft = reactive({
    name: '',
    slug: '',
    scope: 'platform',
    tenant_id: '',
    permissions: Object.fromEntries(props.adminModules.map((module) => [module, 'hide'])) as Record<string, string>,
});
const userRoleForms = reactive<Record<number, string[]>>({});
const discoveredAssignments = reactive<Record<number, { tenant_id: string; display_name: string }>>({});
const trackerAssignments = reactive<Record<number, { tenant_id: string; display_name: string }>>({});
const geofenceDraft = reactive({
    tenant_id: '',
    name: '',
    shape_type: 'circle',
    speed_limit: '',
    entrance_alert_enabled: true,
    exit_alert_enabled: true,
});

const metricIcon = (metric: Metric) => (metric.icon ? iconByName[metric.icon] : undefined);

props.discoveredPayloads.forEach((payload) => {
    discoveredAssignments[payload.id] = { tenant_id: '', display_name: '' };
});

props.trackers.forEach((tracker) => {
    trackerAssignments[tracker.id] = { tenant_id: String(tracker.tenant_id), display_name: tracker.display_name };
});

props.users.forEach((user) => {
    userRoleForms[user.id] = user.roles.map((role) => String(role.id));
});

const roleModules = computed(() => (roleDraft.scope === 'platform' ? props.adminModules : props.customerModules));
const roleOptionsForUser = (user: UserRow) => props.roles.filter((role) => role.tenant_id === user.tenant_id);

watch(
    () => roleDraft.scope,
    () => {
        roleDraft.tenant_id = '';
        roleDraft.permissions = Object.fromEntries(roleModules.value.map((module) => [module, 'hide']));
    },
);

const customerTabs = computed(() => [
    { id: 'all', label: 'All', count: props.customers.length },
    { id: 'active', label: 'Active', count: props.customers.filter((customer) => customer.status === 'active').length },
    { id: 'expired', label: 'Expired', count: props.customers.filter((customer) => customer.billing_status === 'expired').length },
    { id: 'new', label: 'New', count: props.customers.filter((customer) => !customer.acknowledged_at).length },
]);

const paymentTabs = computed(() => [
    { id: 'new', label: 'New', count: props.payments.filter((payment) => payment.status === 'pending').length },
    { id: 'all', label: 'All', count: props.payments.length },
    { id: 'upcoming', label: 'Upcoming', count: props.payments.filter((payment) => payment.status === 'pending').length },
]);

const filteredCustomers = computed(() => {
    if (customerFilter.value === 'active') {
        return props.customers.filter((customer) => customer.status === 'active');
    }

    if (customerFilter.value === 'expired') {
        return props.customers.filter((customer) => customer.billing_status === 'expired');
    }

    if (customerFilter.value === 'new') {
        return props.customers.filter((customer) => !customer.acknowledged_at);
    }

    return props.customers;
});

const filteredPayments = computed(() => {
    if (paymentFilter.value === 'new' || paymentFilter.value === 'upcoming') {
        return props.payments.filter((payment) => payment.status === 'pending');
    }

    return props.payments;
});

const filteredRawPayloads = computed(() =>
    props.rawPayloads.filter((payload) => {
        const trackerMatch = !logFilters.tracker || (payload.tracker ?? '').toLowerCase().includes(logFilters.tracker.toLowerCase());
        const contractMatch = !logFilters.contract || payload.contract.toLowerCase().includes(logFilters.contract.toLowerCase());
        const identityMatch = !logFilters.identity || payload.identity.toLowerCase().includes(logFilters.identity.toLowerCase());

        return trackerMatch && contractMatch && identityMatch;
    }),
);
const activeUserCount = computed(() => props.users.filter((user) => user.status === 'active').length);
const platformAdminCount = computed(() => props.users.filter((user) => user.roles.some((role) => role.scope === 'platform')).length);
const tenantRoleCount = computed(() => props.roles.filter((role) => role.scope === 'tenant').length);
const moduleLabel = (module: string) => module.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
const roleEditableModuleCount = (role: RoleRow) => Object.values(role.permissions.modules ?? {}).filter((level) => level === 'edit').length;
const roleVisibleModuleCount = (role: RoleRow) => Object.values(role.permissions.modules ?? {}).filter((level) => level === 'view').length;

const mapPoints = computed(() => props.trackers.filter((tracker) => tracker.latitude !== null && tracker.longitude !== null));
const routeMapPoints = computed(() => props.routeEvents.filter((event) => event.latitude !== null && event.longitude !== null).slice(0, 35));
const liveMapMarkers = computed(() =>
    mapPoints.value.map((tracker) => ({
        id: tracker.id,
        label: tracker.display_name,
        subtitle: `${tracker.customer} · ${tracker.status}`,
        latitude: tracker.latitude,
        longitude: tracker.longitude,
        status: tracker.status,
        speed: tracker.speed,
    })),
);
const playbackMapPoints = computed(() =>
    routeMapPoints.value.map((event) => ({
        id: event.id,
        label: event.tracker,
        subtitle: `${event.customer} · ${formatDate(event.event_timestamp)}`,
        latitude: event.latitude,
        longitude: event.longitude,
        status: 'route',
        speed: event.speed,
    })),
);

const badgeTone = (status: string): Tone => {
    if (['active', 'approved', 'processed', 'moving', 'resolved'].includes(status)) {
        return 'success';
    }

    if (['pending', 'idle', 'received', 'upcoming'].includes(status)) {
        return 'warning';
    }

    if (['rejected', 'blocked', 'offline', 'failed', 'open'].includes(status)) {
        return 'danger';
    }

    return 'neutral';
};

const formatDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('en', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'None';

const approvePayment = (payment: Payment) => {
    router.post(route('admin.payments.approve', { paymentSlip: payment.id }));
};

const rejectPayment = (payment: Payment) => {
    const reason = window.prompt('Rejection reason');

    if (reason) {
        router.post(route('admin.payments.reject', { paymentSlip: payment.id }), { rejection_reason: reason });
    }
};

const exportCsv = (report: string) => {
    window.location.href = route('admin.exports.show', { report });
};

const approveCustomer = (customer: Customer) => {
    router.post(route('admin.customers.approve', { tenant: customer.id }));
};

const blockCustomer = (customer: Customer) => {
    const reason = window.prompt('Block reason');
    router.post(route('admin.customers.block', { tenant: customer.id }), { reason });
};

const assignDiscovered = (payload: DiscoveredPayload) => {
    const assignment = discoveredAssignments[payload.id];

    if (!assignment?.tenant_id) {
        window.alert('Select a customer before assignment.');
        return;
    }

    router.post(route('admin.devices.assign-discovered'), {
        raw_payload_id: payload.id,
        tenant_id: assignment.tenant_id,
        display_name: assignment.display_name,
    });
};

const assignTracker = (tracker: Tracker) => {
    const assignment = trackerAssignments[tracker.id];

    if (!assignment?.tenant_id) {
        window.alert('Select a customer before assignment.');
        return;
    }

    router.post(route('admin.devices.assign', { trackerDevice: tracker.id }), {
        tenant_id: assignment.tenant_id,
        display_name: assignment.display_name,
    });
};

const saveGeofence = () => {
    router.post(route('admin.geofence.store'), {
        ...geofenceDraft,
        tenant_id: geofenceDraft.tenant_id,
        speed_limit: geofenceDraft.speed_limit || null,
    });
};

const updateGeofence = (geofence: GeofenceRow) => {
    const name = window.prompt('Geofence name', geofence.name);

    if (!name) {
        return;
    }

    router.put(route('admin.geofence.update', { geofence: geofence.id }), {
        name,
        speed_limit: geofence.speed_limit,
        entrance_alert_enabled: geofence.entrance_alert_enabled,
        exit_alert_enabled: geofence.exit_alert_enabled,
    });
};

const createRole = () => {
    router.post(
        route('admin.roles.store'),
        {
            name: roleDraft.name,
            slug: roleDraft.slug || null,
            scope: roleDraft.scope,
            tenant_id: roleDraft.scope === 'tenant' ? roleDraft.tenant_id : null,
            permissions: { modules: roleDraft.permissions },
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                roleDraft.name = '';
                roleDraft.slug = '';
                roleDraft.permissions = Object.fromEntries(roleModules.value.map((module) => [module, 'hide']));
            },
        },
    );
};

const updateUserRoles = (user: UserRow) => {
    router.put(
        route('admin.users.roles.update', { user: user.id }),
        {
            role_ids: (userRoleForms[user.id] ?? []).map((id) => Number(id)),
        },
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Platform Operations" />

    <AppShell surface="admin" title="Platform Operations">
        <div v-if="page.props.flash.status" class="mb-4 rounded-mtrack-md border border-brand/30 bg-brand/10 px-4 py-3 text-sm font-semibold text-brand-hover">
            {{ page.props.flash.status }}
        </div>

        <section v-if="activeModule === 'dashboard'" class="space-y-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <MTrackMetricCard
                    v-for="metric in dashboardMetrics"
                    :key="metric.label"
                    :label="metric.label"
                    :value="metric.value"
                    :helper="metric.helper"
                    :icon="metricIcon(metric)"
                    :tone="metric.tone"
                />
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <MTrackMetricCard
                    v-for="metric in liveStats"
                    :key="metric.label"
                    :label="metric.label"
                    :value="metric.value"
                    :helper="metric.helper"
                    :icon="metricIcon(metric)"
                    :tone="metric.tone"
                />
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <article class="mtrack-panel overflow-hidden">
                    <div class="border-b border-line px-5 py-4">
                        <h2 class="text-section-title">Recent customer logs</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="mtrack-table">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Tenant</th>
                                    <th>When</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="audit in auditLogs.slice(0, 8)" :key="audit.id">
                                    <td>{{ audit.action }}</td>
                                    <td>{{ audit.tenant }}</td>
                                    <td>{{ formatDate(audit.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="mtrack-panel overflow-hidden">
                    <div class="border-b border-line px-5 py-4">
                        <h2 class="text-section-title">Recent events</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="mtrack-table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Tracker</th>
                                    <th>Status</th>
                                    <th>When</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="event in alertEvents.slice(0, 8)" :key="event.id">
                                    <td>{{ event.type }}</td>
                                    <td>{{ event.tracker }}</td>
                                    <td><MTrackBadge :label="event.severity" :tone="badgeTone(event.severity)" /></td>
                                    <td>{{ formatDate(event.occurred_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </section>

        <section v-else-if="activeModule === 'live'" class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <article class="mtrack-map-panel overflow-hidden p-2">
                <MTrackOpenStreetMap :markers="liveMapMarkers" min-height="560px" :zoom="12" />
            </article>

            <aside class="mtrack-panel overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <h2 class="text-section-title">Fleet panel</h2>
                    <button type="button" class="mtrack-button-secondary" @click="exportCsv('device_status')">Export status</button>
                </div>
                <div class="max-h-[560px] overflow-y-auto">
                    <div v-for="tracker in trackers" :key="tracker.id" class="border-b border-line p-4 last:border-b-0">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-bold text-body">{{ tracker.display_name }}</div>
                                <div class="text-sm text-muted">{{ tracker.customer }}</div>
                            </div>
                            <MTrackBadge :label="tracker.status" :tone="badgeTone(tracker.status)" />
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-muted">
                            <span>Speed {{ tracker.speed ?? 0 }} km/h</span>
                            <span>Seen {{ formatDate(tracker.last_seen_at) }}</span>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <section v-else-if="activeModule === 'playback'" class="space-y-6">
            <div class="mtrack-panel grid gap-3 p-4 md:grid-cols-3">
                <select class="mtrack-select">
                    <option>All customers</option>
                    <option v-for="customer in customers" :key="customer.id">{{ customer.name }}</option>
                </select>
                <select class="mtrack-select">
                    <option>All trackers</option>
                    <option v-for="tracker in trackers" :key="tracker.id">{{ tracker.display_name }}</option>
                </select>
                <input class="mtrack-input" type="date" />
            </div>

            <div class="grid gap-6 xl:grid-cols-[1fr_420px]">
                <article class="mtrack-map-panel overflow-hidden">
                    <MTrackOpenStreetMap :markers="playbackMapPoints" :path="playbackMapPoints" min-height="520px" :zoom="12" />
                </article>
                <article class="mtrack-panel overflow-hidden">
                    <div class="border-b border-line px-5 py-4">
                        <h2 class="text-section-title">Route samples</h2>
                    </div>
                    <div class="max-h-[520px] overflow-y-auto">
                        <div v-for="event in routeEvents.slice(0, 20)" :key="event.id" class="grid grid-cols-[1fr_auto] gap-3 border-b border-line p-4 last:border-b-0">
                            <div>
                                <div class="font-semibold">{{ event.tracker }}</div>
                                <div class="text-sm text-muted">{{ event.customer }}</div>
                            </div>
                            <div class="text-right text-xs text-muted">
                                <div>{{ event.speed ?? 0 }} km/h</div>
                                <div>{{ formatDate(event.event_timestamp) }}</div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section v-else-if="activeModule === 'events'" class="mtrack-panel overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                <h2 class="text-section-title">Geofence, overspeed, and device events</h2>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="mtrack-button-secondary" @click="exportCsv('events')">Export events</button>
                    <button type="button" class="mtrack-button-secondary" @click="exportCsv('overspeed')">Export overspeed</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="mtrack-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Customer</th>
                            <th>Tracker</th>
                            <th>Geofence</th>
                            <th>Status</th>
                            <th>Occurred</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="event in alertEvents" :key="event.id">
                            <td>{{ event.type }}</td>
                            <td>{{ event.customer }}</td>
                            <td>{{ event.tracker }}</td>
                            <td>{{ event.geofence ?? 'None' }}</td>
                            <td><MTrackBadge :label="event.severity" :tone="badgeTone(event.severity)" /></td>
                            <td>{{ formatDate(event.occurred_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-else-if="activeModule === 'devices'" class="space-y-6">
            <article class="mtrack-panel overflow-hidden">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-section-title">New discovery</h2>
                </div>
                <div v-if="discoveredPayloads.length" class="overflow-x-auto">
                    <table class="mtrack-table">
                        <thead>
                            <tr>
                                <th>Identity</th>
                                <th>Contract</th>
                                <th>Reason</th>
                                <th>Assign</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="payload in discoveredPayloads" :key="payload.id">
                                <td>
                                    <div class="font-semibold">{{ payload.identity }}</div>
                                    <div class="text-xs text-muted">{{ formatDate(payload.received_at) }}</div>
                                </td>
                                <td>{{ payload.contract }}</td>
                                <td class="max-w-sm">{{ payload.reason ?? payload.body_preview }}</td>
                                <td>
                                    <div class="grid min-w-[320px] gap-2 md:grid-cols-[1fr_1fr_auto]">
                                        <select v-model="discoveredAssignments[payload.id].tenant_id" class="mtrack-select">
                                            <option value="">Customer</option>
                                            <option v-for="customer in customers" :key="customer.id" :value="String(customer.id)">{{ customer.name }}</option>
                                        </select>
                                        <input v-model="discoveredAssignments[payload.id].display_name" class="mtrack-input" placeholder="Display name" />
                                        <button type="button" class="mtrack-button-primary" @click="assignDiscovered(payload)">Assign</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <MTrackState v-else title="No discovered devices" message="Unmatched raw payloads will appear here for assignment." />
            </article>

            <article class="mtrack-panel overflow-hidden">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-section-title">Assigned trackers</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="mtrack-table">
                        <thead>
                            <tr>
                                <th>Tracker</th>
                                <th>Customer</th>
                                <th>Contract</th>
                                <th>Status</th>
                                <th>Reassign</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="tracker in trackers" :key="tracker.id">
                                <td>{{ tracker.display_name }}</td>
                                <td>{{ tracker.customer }}</td>
                                <td>{{ tracker.contract }}</td>
                                <td><MTrackBadge :label="tracker.status" :tone="badgeTone(tracker.status)" /></td>
                                <td>
                                    <div class="grid min-w-[300px] gap-2 md:grid-cols-[1fr_1fr_auto]">
                                        <select v-model="trackerAssignments[tracker.id].tenant_id" class="mtrack-select">
                                            <option v-for="customer in customers" :key="customer.id" :value="String(customer.id)">{{ customer.name }}</option>
                                        </select>
                                        <input v-model="trackerAssignments[tracker.id].display_name" class="mtrack-input" />
                                        <button type="button" class="mtrack-button-secondary" @click="assignTracker(tracker)">Save</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section v-else-if="activeModule === 'geofence'" class="grid gap-6 xl:grid-cols-[380px_1fr]">
            <aside class="mtrack-panel p-5">
                <h2 class="text-section-title">Add geofence</h2>
                <div class="mt-4 space-y-3">
                    <select v-model="geofenceDraft.tenant_id" class="mtrack-select">
                        <option value="">Customer</option>
                        <option v-for="customer in customers" :key="customer.id" :value="String(customer.id)">{{ customer.name }}</option>
                    </select>
                    <input v-model="geofenceDraft.name" class="mtrack-input" placeholder="Name" />
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            :class="[geofenceDraft.shape_type === 'circle' ? 'mtrack-button-primary' : 'mtrack-button-secondary']"
                            @click="geofenceDraft.shape_type = 'circle'"
                        >
                            Circle
                        </button>
                        <button
                            type="button"
                            :class="[geofenceDraft.shape_type === 'polygon' ? 'mtrack-button-primary' : 'mtrack-button-secondary']"
                            @click="geofenceDraft.shape_type = 'polygon'"
                        >
                            Polygon
                        </button>
                    </div>
                    <input v-model="geofenceDraft.speed_limit" class="mtrack-input" placeholder="Speed limit km/h" />
                    <label class="flex items-center gap-2 text-sm font-semibold text-body">
                        <input v-model="geofenceDraft.entrance_alert_enabled" type="checkbox" />
                        Entrance alert
                    </label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-body">
                        <input v-model="geofenceDraft.exit_alert_enabled" type="checkbox" />
                        Exit alert
                    </label>
                    <button type="button" class="mtrack-button-primary w-full" @click="saveGeofence">Save geofence</button>
                    <button type="button" class="mtrack-button-secondary w-full" @click="exportCsv('geofence')">Export geofence</button>
                </div>
            </aside>

            <article class="mtrack-panel overflow-hidden">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-section-title">Geofence registry</h2>
                </div>
                <div class="grid gap-px bg-line md:grid-cols-2">
                    <div v-for="geofence in geofences" :key="geofence.id" class="bg-surface p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-bold">{{ geofence.name }}</div>
                                <div class="text-sm text-muted">{{ geofence.customer }}</div>
                            </div>
                            <MTrackBadge :label="geofence.shape_type" tone="info" />
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-2 text-sm text-muted">
                            <span>Fleet {{ geofence.fleet_group ?? 'All' }}</span>
                            <span>Trackers {{ geofence.trackers_count }}</span>
                            <span>Entrance {{ geofence.entrance_alert_enabled ? 'on' : 'off' }}</span>
                            <span>Exit {{ geofence.exit_alert_enabled ? 'on' : 'off' }}</span>
                        </div>
                        <button type="button" class="mtrack-button-secondary mt-4" @click="updateGeofence(geofence)">Modify</button>
                    </div>
                </div>
            </article>
        </section>

        <section v-else-if="activeModule === 'routes'" class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <article class="mtrack-panel overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <h2 class="text-section-title">Route table</h2>
                    <button type="button" class="mtrack-button-secondary" @click="exportCsv('routes')">Export routes</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="mtrack-table">
                        <thead>
                            <tr>
                                <th>Tracker</th>
                                <th>Customer</th>
                                <th>Coordinates</th>
                                <th>Speed</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="event in routeEvents" :key="event.id">
                                <td>{{ event.tracker }}</td>
                                <td>{{ event.customer }}</td>
                                <td>{{ event.latitude }}, {{ event.longitude }}</td>
                                <td>{{ event.speed ?? 0 }} km/h</td>
                                <td>{{ formatDate(event.event_timestamp) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
            <aside class="mtrack-panel overflow-hidden">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-section-title">Fleet routes</h2>
                </div>
                <div v-for="group in fleetGroups" :key="group.id" class="border-b border-line p-4 last:border-b-0">
                    <div class="font-semibold">{{ group.name }}</div>
                    <div class="text-sm text-muted">{{ group.customer }} · {{ group.trackers_count }} trackers</div>
                </div>
            </aside>
        </section>

        <section v-else-if="activeModule === 'customers'" class="space-y-4">
            <MTrackTabs :tabs="customerTabs" :active-id="customerFilter" @select="(id) => (customerFilter = id)" />
            <article class="mtrack-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="mtrack-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Billing</th>
                                <th>Users</th>
                                <th>Trackers</th>
                                <th>Requests</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="customer in filteredCustomers" :key="customer.id">
                                <td>
                                    <div class="font-semibold">{{ customer.name }}</div>
                                    <div class="text-xs text-muted">{{ formatDate(customer.created_at) }}</div>
                                </td>
                                <td><MTrackBadge :label="customer.status" :tone="badgeTone(customer.status)" /></td>
                                <td><MTrackBadge :label="customer.billing_status" :tone="badgeTone(customer.billing_status)" /></td>
                                <td>{{ customer.users_count }}</td>
                                <td>{{ customer.trackers_count }}</td>
                                <td>{{ customer.license_requests_count }}</td>
                                <td>
                                    <div class="flex gap-2">
                                        <button type="button" class="mtrack-button-secondary" @click="approveCustomer(customer)">Approve</button>
                                        <button type="button" class="mtrack-button-secondary text-danger" @click="blockCustomer(customer)">Block</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section v-else-if="activeModule === 'payments'" class="space-y-4">
            <MTrackTabs :tabs="paymentTabs" :active-id="paymentFilter" @select="(id) => (paymentFilter = id)" />
            <article class="mtrack-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="mtrack-table">
                        <thead>
                            <tr>
                                <th>Slip</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Request</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="payment in filteredPayments" :key="payment.id">
                                <td>
                                    <div class="font-semibold">{{ payment.filename }}</div>
                                    <div class="text-xs text-muted">{{ formatDate(payment.created_at) }}</div>
                                </td>
                                <td>{{ payment.customer }}</td>
                                <td>{{ payment.amount }}</td>
                                <td>{{ payment.request }} · {{ payment.devices }} devices</td>
                                <td><MTrackBadge :label="payment.status" :tone="badgeTone(payment.status)" /></td>
                                <td>
                                    <div class="flex gap-2">
                                        <button type="button" class="mtrack-button-primary" :disabled="payment.status !== 'pending'" @click="approvePayment(payment)">Approve</button>
                                        <button type="button" class="mtrack-button-secondary" :disabled="payment.status !== 'pending'" @click="rejectPayment(payment)">Reject</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section v-else-if="activeModule === 'logs'" class="space-y-4">
            <div class="mtrack-panel grid gap-3 p-4 md:grid-cols-[1fr_1fr_1fr_auto]">
                <input v-model="logFilters.tracker" class="mtrack-input" placeholder="Tracker" />
                <input v-model="logFilters.contract" class="mtrack-input" placeholder="Protocol or contract" />
                <input v-model="logFilters.identity" class="mtrack-input" placeholder="Identifier" />
                <button type="button" class="mtrack-button-secondary" @click="exportCsv('logs')">Export logs</button>
            </div>

            <article class="mtrack-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="mtrack-table">
                        <thead>
                            <tr>
                                <th>Received</th>
                                <th>Customer</th>
                                <th>Tracker</th>
                                <th>Contract</th>
                                <th>Status</th>
                                <th>Payload</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="payload in filteredRawPayloads" :key="payload.id">
                                <td>{{ formatDate(payload.received_at) }}</td>
                                <td>{{ payload.customer }}</td>
                                <td>{{ payload.tracker ?? payload.identity }}</td>
                                <td>{{ payload.contract }}</td>
                                <td><MTrackBadge :label="payload.status" :tone="badgeTone(payload.status)" /></td>
                                <td class="max-w-xl font-mono text-xs text-muted">{{ payload.body_preview }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section v-else-if="activeModule === 'users-roles'" class="mtrack-page-section">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <MTrackMetricCard label="Active users" :value="activeUserCount" helper="People who can sign in" :icon="UserPlus" tone="brand" />
                <MTrackMetricCard label="Platform admins" :value="platformAdminCount" helper="Can manage all customers" :icon="UserPlus" tone="success" />
                <MTrackMetricCard label="Access profiles" :value="roles.length" helper="Reusable permission sets" :icon="Layers" tone="info" />
                <MTrackMetricCard label="Customer profiles" :value="tenantRoleCount" helper="Tenant-level access profiles" :icon="Building2" tone="neutral" />
            </div>

            <div class="grid gap-6 xl:grid-cols-[400px_1fr]">
                <aside class="mtrack-panel overflow-hidden">
                    <div class="mtrack-section-header">
                        <div>
                            <div class="mtrack-section-kicker">New access profile</div>
                            <h2 class="text-section-title">Create role</h2>
                        </div>
                    </div>
                    <div class="space-y-4 p-5">
                        <div class="grid gap-3">
                            <input v-model="roleDraft.name" class="mtrack-input" placeholder="Role name" />
                            <input v-model="roleDraft.slug" class="mtrack-input" placeholder="Short code, optional" />
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" :class="[roleDraft.scope === 'platform' ? 'mtrack-button-primary' : 'mtrack-button-secondary']" @click="roleDraft.scope = 'platform'">
                                Platform
                            </button>
                            <button type="button" :class="[roleDraft.scope === 'tenant' ? 'mtrack-button-primary' : 'mtrack-button-secondary']" @click="roleDraft.scope = 'tenant'">
                                Customer
                            </button>
                        </div>

                        <select v-if="roleDraft.scope === 'tenant'" v-model="roleDraft.tenant_id" class="mtrack-select">
                            <option value="">Choose customer</option>
                            <option v-for="customer in customers" :key="customer.id" :value="String(customer.id)">{{ customer.name }}</option>
                        </select>

                        <div class="rounded-mtrack-md border border-line">
                            <div class="grid grid-cols-[1fr_116px] border-b border-line bg-muted-surface px-3 py-2 text-xs font-bold uppercase text-muted">
                                <span>Area</span>
                                <span>Access</span>
                            </div>
                            <div class="max-h-80 overflow-y-auto p-3">
                                <div v-for="module in roleModules" :key="module" class="grid grid-cols-[1fr_116px] items-center gap-3 border-b border-line/70 py-2 last:border-b-0">
                                    <span class="truncate text-sm font-semibold text-body">{{ moduleLabel(module) }}</span>
                                    <select v-model="roleDraft.permissions[module]" class="mtrack-select h-9 text-xs">
                                        <option v-for="level in permissionLevels" :key="level" :value="level">{{ moduleLabel(level) }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="mtrack-button-primary w-full" @click="createRole">Save role</button>
                    </div>
                </aside>

                <article class="mtrack-panel overflow-hidden">
                    <div class="mtrack-section-header">
                        <div>
                            <div class="mtrack-section-kicker">People</div>
                            <h2 class="text-section-title">Assign roles</h2>
                        </div>
                    </div>
                    <div v-if="users.length" class="divide-y divide-line">
                        <div v-for="user in users" :key="user.id" class="grid gap-4 p-5 lg:grid-cols-[minmax(240px,1fr)_minmax(240px,360px)_auto] lg:items-center">
                            <div class="min-w-0">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-10 shrink-0 place-items-center rounded-full bg-primary-dark text-sm font-bold text-white">
                                        {{ user.name.slice(0, 1) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="truncate font-bold text-body">{{ user.name }}</div>
                                        <div class="truncate text-sm text-muted">{{ user.email }}</div>
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <MTrackBadge :label="user.tenant" tone="neutral" />
                                    <MTrackBadge :label="user.status" :tone="badgeTone(user.status)" />
                                    <span class="text-xs font-semibold text-muted">{{ user.auth_provider }} · {{ formatDate(user.last_login_at) }}</span>
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div class="mb-2 flex flex-wrap gap-1">
                                    <MTrackBadge v-for="role in user.roles" :key="role.id" :label="role.name" tone="info" />
                                    <span v-if="!user.roles.length" class="text-sm font-semibold text-muted">No roles assigned</span>
                                </div>
                                <select v-model="userRoleForms[user.id]" class="mtrack-select min-h-24" multiple>
                                    <option v-for="role in roleOptionsForUser(user)" :key="role.id" :value="String(role.id)">
                                        {{ role.name }}
                                    </option>
                                </select>
                            </div>

                            <button type="button" class="mtrack-button-secondary" @click="updateUserRoles(user)">Update</button>
                        </div>
                    </div>
                    <MTrackState v-else title="No registered users" message="Users appear here after sign-in." />
                </article>
            </div>

            <article class="mtrack-panel overflow-hidden">
                <div class="mtrack-section-header">
                    <div>
                        <div class="mtrack-section-kicker">Profiles</div>
                        <h2 class="text-section-title">Role coverage</h2>
                    </div>
                </div>
                <div v-if="roles.length" class="grid gap-px bg-line md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="role in roles" :key="role.id" class="bg-surface p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold text-body">{{ role.name }}</div>
                                <div class="mt-1 text-xs font-semibold text-muted">{{ role.tenant }} · {{ role.scope }}</div>
                            </div>
                            <MTrackBadge :label="`${role.users_count} users`" tone="neutral" />
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                            <div class="rounded-mtrack-sm bg-muted-surface p-3">
                                <div class="text-xs font-semibold text-muted">Can edit</div>
                                <div class="mt-1 text-lg font-bold text-body">{{ roleEditableModuleCount(role) }}</div>
                            </div>
                            <div class="rounded-mtrack-sm bg-muted-surface p-3">
                                <div class="text-xs font-semibold text-muted">Can view</div>
                                <div class="mt-1 text-lg font-bold text-body">{{ roleVisibleModuleCount(role) }}</div>
                            </div>
                        </div>
                        <div class="mt-4 flex max-h-20 flex-wrap gap-1 overflow-hidden">
                            <MTrackBadge
                                v-for="(level, module) in role.permissions.modules ?? {}"
                                :key="`${role.id}-${module}`"
                                :label="`${moduleLabel(String(module))}: ${moduleLabel(String(level))}`"
                                :tone="level === 'edit' ? 'success' : level === 'view' ? 'info' : 'neutral'"
                            />
                        </div>
                    </div>
                </div>
                <MTrackState v-else title="No roles yet" message="Create a role to begin assigning access." />
            </article>
        </section>

        <section v-else-if="activeModule === 'settings'" class="mtrack-page-section">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <MTrackMetricCard label="Platform admins" :value="platformAdminCount" helper="People who can manage the platform" :icon="UserPlus" tone="brand" />
                <MTrackMetricCard label="Customers" :value="customers.length" helper="Organizations on mTrack" :icon="Building2" tone="success" />
                <MTrackMetricCard label="Access profiles" :value="roles.length" helper="Reusable role templates" :icon="Layers" tone="info" />
            </div>

            <article class="mtrack-panel overflow-hidden">
                <div class="mtrack-section-header">
                    <div>
                        <div class="mtrack-section-kicker">Platform defaults</div>
                        <h2 class="text-section-title">Operational settings</h2>
                    </div>
                </div>
                <div class="grid gap-px bg-line md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="setting in settings" :key="setting.label" class="bg-surface p-5">
                        <div class="text-xs font-bold uppercase text-muted">{{ setting.label }}</div>
                        <div class="mt-2 break-words text-base font-bold text-body">{{ setting.value }}</div>
                    </div>
                </div>
            </article>

            <div class="grid gap-6 xl:grid-cols-2">
                <article class="mtrack-panel overflow-hidden">
                    <div class="mtrack-section-header">
                        <div>
                            <div class="mtrack-section-kicker">Platform team</div>
                            <h2 class="text-section-title">Admin workspace areas</h2>
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="flex flex-wrap gap-2">
                            <MTrackBadge v-for="module in adminModules" :key="module" :label="moduleLabel(module)" tone="brand" />
                        </div>
                    </div>
                </article>

                <article class="mtrack-panel overflow-hidden">
                    <div class="mtrack-section-header">
                        <div>
                            <div class="mtrack-section-kicker">Customer teams</div>
                            <h2 class="text-section-title">Customer workspace areas</h2>
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="flex flex-wrap gap-2">
                            <MTrackBadge v-for="module in customerModules" :key="module" :label="moduleLabel(module)" tone="success" />
                        </div>
                    </div>
                </article>
            </div>
        </section>
    </AppShell>
</template>
