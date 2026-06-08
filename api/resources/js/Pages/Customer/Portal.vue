<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Bell,
    Clock,
    Gauge,
    Map,
    Pause,
    Play,
    RadioTower,
    Route,
} from '@lucide/vue';
import { computed, onUnmounted, reactive, ref } from 'vue';
import type { Component } from 'vue';
import MTrackBadge from '../../Components/MTrackBadge.vue';
import MTrackMetricCard from '../../Components/MTrackMetricCard.vue';
import MTrackState from '../../Components/MTrackState.vue';
import MTrackTabs from '../../Components/MTrackTabs.vue';
import AppShell from '../../Layouts/AppShell.vue';
import type { PageProps } from '../../types';
import { route } from 'ziggy-js';

type Tone = 'brand' | 'success' | 'warning' | 'danger' | 'info' | 'neutral';
type PermissionLevel = 'hide' | 'view' | 'edit';

type ModuleTab = {
    id: string;
    label: string;
    slug: string;
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
    status: string;
    contract: string;
    last_seen_at: string | null;
    latitude: number | null;
    longitude: number | null;
    speed: number | null;
    groups: Array<{ id: number; name: string }>;
    can_edit: boolean;
};

type FleetGroup = {
    id: number;
    name: string;
    visibility: string;
    trackers_count: number;
};

type GeofenceRow = {
    id: number;
    name: string;
    fleet_group_id: number | null;
    fleet_group: string | null;
    shape_type: string;
    speed_limit: string | null;
    entrance_alert_enabled: boolean;
    exit_alert_enabled: boolean;
    trackers_count: number;
};

type AlertRow = {
    id: number;
    type: string;
    tracker: string;
    geofence: string | null;
    occurred_at: string | null;
    resolved_at: string | null;
    severity: string;
};

type RouteEvent = {
    id: number;
    tracker_id: number;
    tracker: string;
    event_timestamp: string | null;
    latitude: number;
    longitude: number;
    speed: number | null;
    heading: number | null;
    distance_km: number;
};

type RawPayloadRow = {
    id: number;
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
    actor_id: number | null;
    subject_type: string;
    subject_id: number | null;
    created_at: string | null;
    metadata: Record<string, unknown>;
};

type LicenseAllocation = {
    id: number;
    plan: string;
    active_device_count: number;
    starts_at: string | null;
    expires_at: string | null;
    status: string;
};

type LicenseRequest = {
    id: number;
    plan: string;
    request_type: string;
    requested_device_count: number;
    amount: string;
    status: string;
    created_at: string | null;
};

type PaymentSlip = {
    id: number;
    license_request_id: number;
    filename: string;
    amount: string;
    status: string;
    rejection_reason: string | null;
    reviewed_at: string | null;
};

type LicensePlan = {
    id: number;
    name: string;
    included_device_count: number;
    price_amount: string;
    currency: string;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    status: string;
    roles: string[];
};

type RoleRow = {
    id: number;
    name: string;
    users_count: number;
    permissions: Record<string, unknown>;
};

type Settings = {
    dashboard_density: string;
    default_map_zoom: number;
    raw_payload_retention_days: number;
    api_token_preview: string;
    api_token_rotated_at: string | null;
};

type TripAnalytics = {
    summary: {
        events: number;
        distance_km: number;
        moving_time_minutes: number;
        idle_time_minutes: number;
        max_speed: number;
        average_speed: number;
    };
    stops: RouteEvent[];
    segments: Array<{ type: string; start_at: string | null; end_at: string | null; events: number }>;
    speedGraph: Array<{ label: string | null; speed: number }>;
};

const props = defineProps<{
    activeModule: string;
    modules: ModuleTab[];
    allowedModules: string[];
    permissions: Record<string, PermissionLevel>;
    dashboardMetrics: Metric[];
    trackers: Tracker[];
    fleetGroups: FleetGroup[];
    geofences: GeofenceRow[];
    alertEvents: AlertRow[];
    routeEvents: RouteEvent[];
    tripAnalytics: TripAnalytics;
    rawPayloads: RawPayloadRow[];
    auditLogs: AuditRow[];
    licenseAllocations: LicenseAllocation[];
    licenseRequests: LicenseRequest[];
    paymentSlips: PaymentSlip[];
    licensePlans: LicensePlan[];
    users: UserRow[];
    roles: RoleRow[];
    settings: Settings;
    exportColumns: Record<string, string[]>;
}>();

const page = usePage<PageProps>();
const iconByName: Record<string, Component> = { Activity, Bell, Clock, Gauge, Map, RadioTower, Route };

const activeEventType = ref('all');
const playbackIndex = ref(0);
const isPlaying = ref(false);
const speedMultiplier = ref(1);
const playbackTimer = ref<number | null>(null);
const selectedExport = reactive<Record<string, string[]>>({});
Object.entries(props.exportColumns).forEach(([report, columns]) => {
    selectedExport[report] = [...columns];
});

const fleetForm = reactive({ name: '', visibility: 'private' });
const trackerForms = reactive<Record<number, { display_name: string; business_label: string }>>({});
const geofenceForm = reactive({
    name: '',
    fleet_group_id: '',
    shape_type: 'circle',
    speed_limit: '',
    entrance_alert_enabled: true,
    exit_alert_enabled: true,
});
const billingForm = reactive({
    license_plan_id: props.licensePlans[0]?.id ? String(props.licensePlans[0].id) : '',
    request_type: 'add',
    requested_device_count: 1,
});
const slipForm = reactive({
    license_request_id: props.licenseRequests[0]?.id ? String(props.licenseRequests[0].id) : '',
    original_filename: '',
    amount: '',
});
const settingsForm = reactive({
    dashboard_density: props.settings.dashboard_density,
    default_map_zoom: props.settings.default_map_zoom,
    raw_payload_retention_days: props.settings.raw_payload_retention_days,
});

props.trackers.forEach((tracker) => {
    trackerForms[tracker.id] = {
        display_name: tracker.display_name,
        business_label: tracker.business_label ?? '',
    };
});

const metricIcon = (metric: Metric) => (metric.icon ? iconByName[metric.icon] : undefined);
const moduleSlug = (id: string) => props.modules.find((module) => module.id === id)?.slug ?? id.replaceAll('_', '-');
const selectModule = (id: string) => router.visit(route('customer.show', { module: moduleSlug(id) }));
const canEdit = (module: string) => props.permissions[module] === 'edit';

const eventTabs = computed(() => [
    { id: 'all', label: 'All', count: props.alertEvents.length },
    { id: 'geofence', label: 'Geofence', count: props.alertEvents.filter((event) => event.type.includes('geofence')).length },
    { id: 'overspeed', label: 'Overspeed', count: props.alertEvents.filter((event) => event.type === 'overspeed').length },
    { id: 'device', label: 'Device', count: props.alertEvents.filter((event) => ['online', 'offline', 'stale'].includes(event.type)).length },
]);

const filteredEvents = computed(() => {
    if (activeEventType.value === 'geofence') {
        return props.alertEvents.filter((event) => event.type.includes('geofence'));
    }

    if (activeEventType.value === 'overspeed') {
        return props.alertEvents.filter((event) => event.type === 'overspeed');
    }

    if (activeEventType.value === 'device') {
        return props.alertEvents.filter((event) => ['online', 'offline', 'stale'].includes(event.type));
    }

    return props.alertEvents;
});

const playbackEvent = computed(() => props.routeEvents[Math.min(playbackIndex.value, Math.max(props.routeEvents.length - 1, 0))]);
const mapPoints = computed(() => props.trackers.filter((tracker) => tracker.latitude !== null && tracker.longitude !== null));
const routePoints = computed(() => props.routeEvents);
const maxSpeed = computed(() => Math.max(...props.tripAnalytics.speedGraph.map((point) => point.speed), 1));

const markerStyle = (latitude: number | null, longitude: number | null, source: Array<{ latitude: number | null; longitude: number | null }>) => {
    if (latitude === null || longitude === null) {
        return {};
    }

    const latitudes = source.map((point) => point.latitude).filter((value): value is number => value !== null);
    const longitudes = source.map((point) => point.longitude).filter((value): value is number => value !== null);
    const minLat = Math.min(...latitudes, latitude);
    const maxLat = Math.max(...latitudes, latitude);
    const minLng = Math.min(...longitudes, longitude);
    const maxLng = Math.max(...longitudes, longitude);
    const latRange = Math.max(maxLat - minLat, 0.01);
    const lngRange = Math.max(maxLng - minLng, 0.01);

    return {
        left: `${10 + ((longitude - minLng) / lngRange) * 80}%`,
        top: `${90 - ((latitude - minLat) / latRange) * 80}%`,
    };
};

const badgeTone = (status: string): Tone => {
    if (['active', 'approved', 'processed', 'moving', 'resolved', 'online'].includes(status)) return 'success';
    if (['pending', 'idle', 'received', 'add', 'renew'].includes(status)) return 'warning';
    if (['rejected', 'blocked', 'offline', 'failed', 'open', 'expired', 'stale'].includes(status)) return 'danger';

    return 'neutral';
};

const formatDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('en', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'None';

const stopPlayback = () => {
    if (playbackTimer.value !== null) {
        window.clearInterval(playbackTimer.value);
        playbackTimer.value = null;
    }

    isPlaying.value = false;
};

const togglePlayback = () => {
    if (isPlaying.value) {
        stopPlayback();
        return;
    }

    isPlaying.value = true;
    playbackTimer.value = window.setInterval(() => {
        if (playbackIndex.value >= props.routeEvents.length - 1) {
            playbackIndex.value = 0;
            return;
        }

        playbackIndex.value += 1;
    }, Math.max(200, 900 / speedMultiplier.value));
};

onUnmounted(stopPlayback);

const createFleetGroup = () => router.post(route('customer.fleet-groups.store'), fleetForm);
const updateTracker = (tracker: Tracker) => router.put(route('customer.trackers.update', { trackerDevice: tracker.id }), trackerForms[tracker.id]);
const createGeofence = () => router.post(route('customer.geofence.store'), geofenceForm);
const modifyGeofence = (geofence: GeofenceRow) => {
    const name = window.prompt('Geofence name', geofence.name);
    if (!name) return;

    router.put(route('customer.geofence.update', { geofence: geofence.id }), {
        name,
        speed_limit: geofence.speed_limit,
        entrance_alert_enabled: geofence.entrance_alert_enabled,
        exit_alert_enabled: geofence.exit_alert_enabled,
    });
};
const createLicenseRequest = () => router.post(route('customer.billing.license-requests.store'), billingForm);
const uploadPaymentSlip = () => router.post(route('customer.billing.payment-slips.store'), slipForm);
const saveSettings = () => router.post(route('customer.settings.store'), settingsForm);
const regenerateApiToken = () => router.post(route('customer.settings.api-token'));
const exportCsv = (report: string) => {
    const columns = selectedExport[report] ?? props.exportColumns[report] ?? [];
    window.location.href = route('customer.exports.show', {
        report,
        _query: { columns: columns.join(',') },
    });
};
</script>

<template>
    <Head title="Customer web" />

    <AppShell
        surface="customer"
        :allowed-modules="allowedModules"
        title="Customer web"
        description="Tenant-scoped fleet monitoring, playback, reporting, billing, settings, and audit workflows."
    >
        <div v-if="page.props.flash.status" class="mb-4 rounded-mtrack-md border border-brand/30 bg-brand/10 px-4 py-3 text-sm font-semibold text-brand-hover">
            {{ page.props.flash.status }}
        </div>

        <div class="mb-6 overflow-x-auto">
            <MTrackTabs :tabs="modules" :active-id="activeModule" @select="selectModule" />
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

            <div class="grid gap-6 xl:grid-cols-[1fr_420px]">
                <article class="mtrack-panel overflow-hidden">
                    <div class="border-b border-line px-5 py-4">
                        <h2 class="text-section-title">Speed graph</h2>
                    </div>
                    <div class="flex h-72 items-end gap-2 p-5">
                        <div
                            v-for="point in tripAnalytics.speedGraph.slice(-32)"
                            :key="`${point.label}-${point.speed}`"
                            class="min-h-2 flex-1 rounded-t-mtrack-sm bg-brand"
                            :style="{ height: `${Math.max(6, (point.speed / maxSpeed) * 240)}px` }"
                            :title="`${point.speed} km/h`"
                        />
                    </div>
                </article>

                <article class="mtrack-panel overflow-hidden">
                    <div class="border-b border-line px-5 py-4">
                        <h2 class="text-section-title">Events</h2>
                    </div>
                    <div class="max-h-72 overflow-y-auto">
                        <div v-for="event in alertEvents.slice(0, 8)" :key="event.id" class="flex items-center justify-between gap-3 border-b border-line p-4 last:border-b-0">
                            <div>
                                <div class="font-semibold">{{ event.type }}</div>
                                <div class="text-sm text-muted">{{ event.tracker }} · {{ formatDate(event.occurred_at) }}</div>
                            </div>
                            <MTrackBadge :label="event.severity" :tone="badgeTone(event.severity)" />
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section v-else-if="activeModule === 'live'" class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <article class="mtrack-map-panel overflow-hidden">
                <div class="osm-map relative min-h-[560px]">
                    <div
                        v-for="tracker in mapPoints"
                        :key="tracker.id"
                        class="absolute z-10 -translate-x-1/2 -translate-y-1/2"
                        :style="markerStyle(tracker.latitude, tracker.longitude, mapPoints)"
                    >
                        <div class="grid size-8 place-items-center rounded-full border-2 border-white bg-brand text-xs font-bold text-primary-dark shadow-overlay">
                            {{ tracker.display_name.slice(0, 1) }}
                        </div>
                    </div>
                    <div class="absolute bottom-3 right-3 rounded bg-white/90 px-2 py-1 text-xs font-semibold text-muted">OpenStreetMap</div>
                </div>
            </article>

            <aside class="mtrack-panel overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <h2 class="text-section-title">Fleet and event tabs</h2>
                    <button type="button" class="mtrack-button-secondary" @click="exportCsv('device_status')">Export status</button>
                </div>
                <div class="max-h-[560px] overflow-y-auto">
                    <div v-for="tracker in trackers" :key="tracker.id" class="border-b border-line p-4 last:border-b-0">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-bold text-body">{{ tracker.display_name }}</div>
                                <div class="text-sm text-muted">{{ tracker.groups.map((group) => group.name).join(', ') || 'Ungrouped' }}</div>
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
            <div class="mtrack-panel grid gap-3 p-4 lg:grid-cols-[1fr_1fr_1fr_auto_auto]">
                <select class="mtrack-select">
                    <option v-for="tracker in trackers" :key="tracker.id">{{ tracker.display_name }}</option>
                </select>
                <input class="mtrack-input" type="date" />
                <select v-model.number="speedMultiplier" class="mtrack-select">
                    <option :value="1">1x speed</option>
                    <option :value="2">2x speed</option>
                    <option :value="4">4x speed</option>
                </select>
                <button type="button" class="mtrack-button-primary" @click="togglePlayback">
                    <Pause v-if="isPlaying" class="size-4" />
                    <Play v-else class="size-4" />
                    {{ isPlaying ? 'Pause' : 'Play' }}
                </button>
                <button type="button" class="mtrack-button-secondary" @click="exportCsv('routes')">CSV</button>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1fr_420px]">
                <article class="mtrack-map-panel overflow-hidden">
                    <div class="osm-map relative min-h-[520px]">
                        <div
                            v-for="event in routePoints"
                            :key="event.id"
                            class="absolute size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary-dark/50"
                            :style="markerStyle(event.latitude, event.longitude, routePoints)"
                        />
                        <div
                            v-if="playbackEvent"
                            class="absolute z-10 -translate-x-1/2 -translate-y-1/2"
                            :style="markerStyle(playbackEvent.latitude, playbackEvent.longitude, routePoints)"
                        >
                            <div class="grid size-10 place-items-center rounded-full border-2 border-white bg-brand text-xs font-bold text-primary-dark shadow-overlay">
                                {{ playbackEvent.tracker.slice(0, 1) }}
                            </div>
                        </div>
                        <div class="absolute bottom-3 right-3 rounded bg-white/90 px-2 py-1 text-xs font-semibold text-muted">OpenStreetMap playback</div>
                    </div>
                    <div class="border-t border-line bg-surface p-4">
                        <input v-model.number="playbackIndex" class="w-full accent-brand" type="range" min="0" :max="Math.max(routeEvents.length - 1, 0)" />
                    </div>
                </article>

                <article class="mtrack-panel overflow-hidden">
                    <div class="border-b border-line px-5 py-4">
                        <h2 class="text-section-title">Trip summary</h2>
                    </div>
                    <div class="grid grid-cols-2 gap-px bg-line">
                        <div class="bg-surface p-4">
                            <div class="text-xs font-semibold text-muted">Distance</div>
                            <div class="text-metric">{{ tripAnalytics.summary.distance_km }} km</div>
                        </div>
                        <div class="bg-surface p-4">
                            <div class="text-xs font-semibold text-muted">Max speed</div>
                            <div class="text-metric">{{ tripAnalytics.summary.max_speed }}</div>
                        </div>
                        <div class="bg-surface p-4">
                            <div class="text-xs font-semibold text-muted">Moving</div>
                            <div class="text-metric">{{ tripAnalytics.summary.moving_time_minutes }}m</div>
                        </div>
                        <div class="bg-surface p-4">
                            <div class="text-xs font-semibold text-muted">Idle</div>
                            <div class="text-metric">{{ tripAnalytics.summary.idle_time_minutes }}m</div>
                        </div>
                    </div>
                    <div class="border-t border-line px-5 py-4">
                        <h3 class="text-sm font-bold">Stops</h3>
                        <div v-for="stop in tripAnalytics.stops.slice(0, 5)" :key="stop.id" class="mt-3 text-sm text-muted">
                            {{ stop.tracker }} · {{ formatDate(stop.event_timestamp) }}
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section v-else-if="activeModule === 'events'" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <MTrackTabs :tabs="eventTabs" :active-id="activeEventType" @select="(id) => (activeEventType = id)" />
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="mtrack-button-secondary" @click="exportCsv('events')">Export events</button>
                    <button type="button" class="mtrack-button-secondary" @click="exportCsv('overspeed')">Export overspeed</button>
                </div>
            </div>
            <article class="mtrack-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="mtrack-table">
                        <thead>
                            <tr><th>Type</th><th>Tracker</th><th>Geofence</th><th>Status</th><th>Occurred</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="event in filteredEvents" :key="event.id">
                                <td>{{ event.type }}</td>
                                <td>{{ event.tracker }}</td>
                                <td>{{ event.geofence ?? 'None' }}</td>
                                <td><MTrackBadge :label="event.severity" :tone="badgeTone(event.severity)" /></td>
                                <td>{{ formatDate(event.occurred_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section v-else-if="activeModule === 'my_fleets'" class="grid gap-6 xl:grid-cols-[360px_1fr]">
            <aside class="mtrack-panel p-5">
                <h2 class="text-section-title">Group management</h2>
                <div class="mt-4 space-y-3">
                    <input v-model="fleetForm.name" class="mtrack-input" placeholder="Group name" :disabled="!canEdit('my_fleets')" />
                    <select v-model="fleetForm.visibility" class="mtrack-select" :disabled="!canEdit('my_fleets')">
                        <option value="private">Private discovery</option>
                        <option value="public">Public discovery</option>
                    </select>
                    <button type="button" class="mtrack-button-primary w-full" :disabled="!canEdit('my_fleets')" @click="createFleetGroup">Create group</button>
                </div>
                <div class="mt-6 space-y-2">
                    <div v-for="group in fleetGroups" :key="group.id" class="rounded-mtrack-sm bg-muted-surface p-3">
                        <div class="font-semibold">{{ group.name }}</div>
                        <div class="text-sm text-muted">{{ group.visibility }} · {{ group.trackers_count }} trackers</div>
                    </div>
                </div>
            </aside>

            <article class="mtrack-panel overflow-hidden">
                <div class="border-b border-line px-5 py-4">
                    <h2 class="text-section-title">Fleet list and tracker editor</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="mtrack-table">
                        <thead>
                            <tr><th>Tracker</th><th>Groups</th><th>Status</th><th>Basic info</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="tracker in trackers" :key="tracker.id">
                                <td>{{ tracker.display_name }}</td>
                                <td>{{ tracker.groups.map((group) => group.name).join(', ') || 'Ungrouped' }}</td>
                                <td><MTrackBadge :label="tracker.status" :tone="badgeTone(tracker.status)" /></td>
                                <td>
                                    <div class="grid min-w-[360px] gap-2 md:grid-cols-[1fr_1fr_auto]">
                                        <input v-model="trackerForms[tracker.id].display_name" class="mtrack-input" :disabled="!tracker.can_edit" />
                                        <input v-model="trackerForms[tracker.id].business_label" class="mtrack-input" placeholder="Business label" :disabled="!tracker.can_edit" />
                                        <button type="button" class="mtrack-button-secondary" :disabled="!tracker.can_edit" @click="updateTracker(tracker)">Save</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section v-else-if="activeModule === 'geofence'" class="grid gap-6 xl:grid-cols-[360px_1fr]">
            <aside class="mtrack-panel p-5">
                <h2 class="text-section-title">Add geofence</h2>
                <div class="mt-4 space-y-3">
                    <input v-model="geofenceForm.name" class="mtrack-input" placeholder="Name" :disabled="!canEdit('geofence')" />
                    <select v-model="geofenceForm.fleet_group_id" class="mtrack-select" :disabled="!canEdit('geofence')">
                        <option value="">All groups</option>
                        <option v-for="group in fleetGroups" :key="group.id" :value="String(group.id)">{{ group.name }}</option>
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" :class="[geofenceForm.shape_type === 'circle' ? 'mtrack-button-primary' : 'mtrack-button-secondary']" :disabled="!canEdit('geofence')" @click="geofenceForm.shape_type = 'circle'">Circle</button>
                        <button type="button" :class="[geofenceForm.shape_type === 'polygon' ? 'mtrack-button-primary' : 'mtrack-button-secondary']" :disabled="!canEdit('geofence')" @click="geofenceForm.shape_type = 'polygon'">Polygon</button>
                    </div>
                    <input v-model="geofenceForm.speed_limit" class="mtrack-input" placeholder="Max speed km/h" :disabled="!canEdit('geofence')" />
                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="geofenceForm.entrance_alert_enabled" type="checkbox" :disabled="!canEdit('geofence')" /> Entrance alert</label>
                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="geofenceForm.exit_alert_enabled" type="checkbox" :disabled="!canEdit('geofence')" /> Exit alert</label>
                    <button type="button" class="mtrack-button-primary w-full" :disabled="!canEdit('geofence')" @click="createGeofence">Save geofence</button>
                    <button type="button" class="mtrack-button-secondary w-full" @click="exportCsv('geofence')">Export geofence</button>
                </div>
            </aside>
            <article class="mtrack-panel overflow-hidden">
                <div class="grid gap-px bg-line md:grid-cols-2">
                    <div v-for="geofence in geofences" :key="geofence.id" class="bg-surface p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-bold">{{ geofence.name }}</div>
                                <div class="text-sm text-muted">{{ geofence.fleet_group ?? 'All groups' }}</div>
                            </div>
                            <MTrackBadge :label="geofence.shape_type" tone="info" />
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-2 text-sm text-muted">
                            <span>Entrance {{ geofence.entrance_alert_enabled ? 'on' : 'off' }}</span>
                            <span>Exit {{ geofence.exit_alert_enabled ? 'on' : 'off' }}</span>
                            <span>Limit {{ geofence.speed_limit ?? 'None' }}</span>
                            <span>Trackers {{ geofence.trackers_count }}</span>
                        </div>
                        <button type="button" class="mtrack-button-secondary mt-4" :disabled="!canEdit('geofence')" @click="modifyGeofence(geofence)">Modify</button>
                    </div>
                </div>
            </article>
        </section>

        <section v-else-if="activeModule === 'analysis'" class="space-y-6">
            <div class="mtrack-panel grid gap-3 p-4 md:grid-cols-[1fr_1fr_1fr_auto]">
                <select class="mtrack-select"><option>All groups</option><option v-for="group in fleetGroups" :key="group.id">{{ group.name }}</option></select>
                <select class="mtrack-select"><option>All fleets</option><option v-for="tracker in trackers" :key="tracker.id">{{ tracker.display_name }}</option></select>
                <input class="mtrack-input" type="date" />
                <button type="button" class="mtrack-button-secondary" @click="exportCsv('analysis')">Export analysis</button>
            </div>
            <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                <MTrackMetricCard label="Distance" :value="`${tripAnalytics.summary.distance_km} km`" helper="Selected period" tone="brand" :icon="Route" />
                <MTrackMetricCard label="Moving" :value="`${tripAnalytics.summary.moving_time_minutes}m`" helper="Moving segments" tone="success" :icon="Activity" />
                <MTrackMetricCard label="Idle" :value="`${tripAnalytics.summary.idle_time_minutes}m`" helper="Idle segments" tone="warning" :icon="Clock" />
                <MTrackMetricCard label="Max speed" :value="`${tripAnalytics.summary.max_speed}`" helper="km/h" tone="danger" :icon="Gauge" />
                <MTrackMetricCard label="Average speed" :value="`${tripAnalytics.summary.average_speed}`" helper="km/h" tone="info" :icon="Gauge" />
                <MTrackMetricCard label="Events" :value="tripAnalytics.summary.events" helper="Route points" tone="neutral" :icon="Bell" />
            </div>
            <article class="mtrack-panel overflow-hidden">
                <div class="border-b border-line px-5 py-4"><h2 class="text-section-title">Moving and idle segments</h2></div>
                <div class="overflow-x-auto">
                    <table class="mtrack-table">
                        <thead><tr><th>Type</th><th>Start</th><th>End</th><th>Events</th></tr></thead>
                        <tbody>
                            <tr v-for="(segment, index) in tripAnalytics.segments" :key="index">
                                <td><MTrackBadge :label="segment.type" :tone="badgeTone(segment.type)" /></td>
                                <td>{{ formatDate(segment.start_at) }}</td>
                                <td>{{ formatDate(segment.end_at) }}</td>
                                <td>{{ segment.events }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section v-else-if="activeModule === 'routes'" class="space-y-4">
            <div class="mtrack-panel flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="flex flex-wrap gap-2">
                    <label v-for="column in exportColumns.routes" :key="column" class="flex items-center gap-2 text-sm font-semibold text-muted">
                        <input v-model="selectedExport.routes" type="checkbox" :value="column" />
                        {{ column }}
                    </label>
                </div>
                <button type="button" class="mtrack-button-secondary" @click="exportCsv('routes')">Export routes</button>
            </div>
            <article class="mtrack-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="mtrack-table">
                        <thead><tr><th>Tracker</th><th>Coordinates</th><th>Speed</th><th>Heading</th><th>Time</th></tr></thead>
                        <tbody>
                            <tr v-for="event in routeEvents" :key="event.id">
                                <td>{{ event.tracker }}</td>
                                <td>{{ event.latitude }}, {{ event.longitude }}</td>
                                <td>{{ event.speed ?? 0 }} km/h</td>
                                <td>{{ event.heading ?? 0 }}</td>
                                <td>{{ formatDate(event.event_timestamp) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section v-else-if="activeModule === 'settings'" class="grid gap-6 xl:grid-cols-[360px_1fr]">
            <aside class="mtrack-panel p-5">
                <h2 class="text-section-title">Preferences and API token</h2>
                <div class="mt-4 space-y-3">
                    <select v-model="settingsForm.dashboard_density" class="mtrack-select" :disabled="!canEdit('settings')">
                        <option value="compact">Compact dashboard</option>
                        <option value="comfortable">Comfortable dashboard</option>
                    </select>
                    <input v-model.number="settingsForm.default_map_zoom" class="mtrack-input" type="number" min="4" max="18" :disabled="!canEdit('settings')" />
                    <select v-model.number="settingsForm.raw_payload_retention_days" class="mtrack-select" :disabled="!canEdit('settings')">
                        <option :value="30">30 days</option>
                        <option :value="90">90 days</option>
                        <option :value="180">180 days</option>
                        <option :value="365">365 days</option>
                    </select>
                    <button type="button" class="mtrack-button-primary w-full" :disabled="!canEdit('settings')" @click="saveSettings">Save settings</button>
                    <button type="button" class="mtrack-button-secondary w-full" :disabled="!canEdit('settings')" @click="regenerateApiToken">Regenerate API token</button>
                    <button type="button" class="mtrack-button-secondary w-full" @click="exportCsv('logs')">Export logs</button>
                    <div class="text-sm text-muted">Token {{ settings.api_token_preview }} · {{ formatDate(settings.api_token_rotated_at) }}</div>
                </div>
            </aside>
            <article class="mtrack-panel overflow-hidden">
                <div class="border-b border-line px-5 py-4"><h2 class="text-section-title">Users and roles</h2></div>
                <div class="grid gap-px bg-line md:grid-cols-2">
                    <div v-for="user in users" :key="user.id" class="bg-surface p-4">
                        <div class="font-semibold">{{ user.name }}</div>
                        <div class="text-sm text-muted">{{ user.email }}</div>
                        <div class="mt-2 text-xs text-muted">{{ user.roles.join(', ') || 'No role' }}</div>
                    </div>
                    <div v-for="role in roles" :key="`role-${role.id}`" class="bg-surface p-4">
                        <div class="font-semibold">{{ role.name }}</div>
                        <div class="text-sm text-muted">{{ role.users_count }} users</div>
                    </div>
                </div>
            </article>
        </section>

        <section v-else-if="activeModule === 'billing'" class="grid gap-6 xl:grid-cols-[360px_1fr]">
            <aside class="mtrack-panel p-5">
                <h2 class="text-section-title">Add or renew license</h2>
                <div class="mt-4 space-y-3">
                    <select v-model="billingForm.license_plan_id" class="mtrack-select" :disabled="!canEdit('billing')">
                        <option v-for="plan in licensePlans" :key="plan.id" :value="String(plan.id)">{{ plan.name }} · {{ plan.price_amount }}</option>
                    </select>
                    <select v-model="billingForm.request_type" class="mtrack-select" :disabled="!canEdit('billing')">
                        <option value="add">Add license</option>
                        <option value="renew">Renew license</option>
                    </select>
                    <input v-model.number="billingForm.requested_device_count" class="mtrack-input" type="number" min="1" :disabled="!canEdit('billing')" />
                    <button type="button" class="mtrack-button-primary w-full" :disabled="!canEdit('billing')" @click="createLicenseRequest">Proceed payment</button>
                </div>
                <div class="mt-6 border-t border-line pt-5">
                    <h3 class="text-sm font-bold">Payment slip</h3>
                    <div class="mt-3 space-y-3">
                        <select v-model="slipForm.license_request_id" class="mtrack-select" :disabled="!canEdit('billing')">
                            <option v-for="request in licenseRequests" :key="request.id" :value="String(request.id)">#{{ request.id }} · {{ request.amount }}</option>
                        </select>
                        <input v-model="slipForm.original_filename" class="mtrack-input" placeholder="slip.pdf" :disabled="!canEdit('billing')" />
                        <input v-model="slipForm.amount" class="mtrack-input" placeholder="Amount" :disabled="!canEdit('billing')" />
                        <button type="button" class="mtrack-button-secondary w-full" :disabled="!canEdit('billing')" @click="uploadPaymentSlip">Submit slip</button>
                    </div>
                </div>
            </aside>
            <article class="mtrack-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="mtrack-table">
                        <thead><tr><th>License</th><th>Devices</th><th>Status</th><th>Expires</th></tr></thead>
                        <tbody>
                            <tr v-for="allocation in licenseAllocations" :key="allocation.id">
                                <td>{{ allocation.plan }}</td>
                                <td>{{ allocation.active_device_count }}</td>
                                <td><MTrackBadge :label="allocation.status" :tone="badgeTone(allocation.status)" /></td>
                                <td>{{ formatDate(allocation.expires_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-5 py-4">
                    <h2 class="text-section-title">Requests and slips</h2>
                    <div class="mt-3 grid gap-3 md:grid-cols-2">
                        <div v-for="request in licenseRequests" :key="request.id" class="rounded-mtrack-sm bg-muted-surface p-3">
                            <div class="font-semibold">{{ request.plan }} · {{ request.request_type }}</div>
                            <div class="text-sm text-muted">{{ request.amount }} · {{ request.requested_device_count }} devices</div>
                            <MTrackBadge class="mt-2" :label="request.status" :tone="badgeTone(request.status)" />
                        </div>
                        <div v-for="slip in paymentSlips" :key="`slip-${slip.id}`" class="rounded-mtrack-sm bg-muted-surface p-3">
                            <div class="font-semibold">{{ slip.filename }}</div>
                            <div class="text-sm text-muted">{{ slip.amount }}</div>
                            <MTrackBadge class="mt-2" :label="slip.status" :tone="badgeTone(slip.status)" />
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section v-else-if="activeModule === 'audit_log'" class="space-y-4">
            <div class="mtrack-panel flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="flex flex-wrap gap-2">
                    <label v-for="column in exportColumns.audit_log" :key="column" class="flex items-center gap-2 text-sm font-semibold text-muted">
                        <input v-model="selectedExport.audit_log" type="checkbox" :value="column" />
                        {{ column }}
                    </label>
                </div>
                <button type="button" class="mtrack-button-secondary" @click="exportCsv('audit_log')">Export audit log</button>
            </div>
            <article class="mtrack-panel overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="mtrack-table">
                        <thead><tr><th>Action</th><th>Actor</th><th>Subject</th><th>Date</th></tr></thead>
                        <tbody>
                            <tr v-for="audit in auditLogs" :key="audit.id">
                                <td>{{ audit.action }}</td>
                                <td>{{ audit.actor_id ?? 'System' }}</td>
                                <td>{{ audit.subject_type }} #{{ audit.subject_id }}</td>
                                <td>{{ formatDate(audit.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <MTrackState v-else title="No module available" message="This module is hidden by your role permissions." />
    </AppShell>
</template>

<style scoped>
.osm-map {
    background-color: #e8eef3;
    background-image:
        linear-gradient(90deg, rgba(11, 16, 38, 0.08) 1px, transparent 1px),
        linear-gradient(rgba(11, 16, 38, 0.08) 1px, transparent 1px),
        radial-gradient(circle at 22% 70%, rgba(20, 184, 166, 0.18), transparent 22%),
        radial-gradient(circle at 72% 28%, rgba(103, 232, 249, 0.18), transparent 26%);
    background-size:
        64px 64px,
        64px 64px,
        auto,
        auto;
}
</style>
