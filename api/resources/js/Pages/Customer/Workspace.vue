<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Plus, RefreshCw, SlidersHorizontal, RadioTower, Copy, MessageSquare, ArrowUp, ArrowDown, Download, MapPin } from '@lucide/vue';
import CustomerShell from '../../Layouts/CustomerShell.vue';
import MTrackOpenStreetMap from '../../Components/MTrackOpenStreetMap.vue';
import PlaceMap, { type Geometry } from '../../Components/PlaceMap.vue';

type Tracker = { id: number; display_name: string; imei: string | null; status: string; last_seen_at: string | null; latitude: number | null; longitude: number | null; speed: number | null };
type Place = { id: number; name: string; shape_type: string; shape_geometry: Geometry; entrance_alert_enabled: boolean; exit_alert_enabled: boolean };
type Event = { id: number; device: string; place: string; type: string; occurred_at: string };
const props = defineProps<{
    activeModule: string; allowedModules: string[]; canManageDevices: boolean; canManageGeofences: boolean;
    trackers: Tracker[]; geofences: Place[]; events: { data: Event[]; total: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null };
    counts: Record<string, number>; filters: { from: string; to: string; tracker?: number; fence?: number; direction?: string };
    history: { id: number; label: string; latitude: number; longitude: number; speed: number }[];
    preferences: { widgets: string[] }; connection: { host: string; port: number }; discovery: { imei: string; last_seen_at: string } | null; refreshedAt: string;
}>();
const titles: Record<string, [string, string]> = {
    dashboard: ['Your overview', 'Your devices, familiar places, and the journeys between them.'],
    live: ['Live map', 'See the latest reported position of your devices.'],
    my_fleets: ['My devices', 'Connect a tracker, make it yours, and stay in the know.'],
    geofence: ['Your places', 'Draw a boundary. Know when your devices arrive and leave.'],
    events: ['Arrivals & departures', 'A clear record of movement through your places.'],
    analysis: ['Place reports', 'Filter arrivals and departures, then export the records you need.'],
    playback: ['Journey history', 'Follow the route your device reported over a selected period.'],
    routes: ['Journey history', 'Follow the route your device reported over a selected period.'],
};
const title = computed(() => titles[props.activeModule] ?? titles.dashboard!);
const search = ref('');
const setup = ref(false);
const customize = ref(false);
const phone = ref('');
const copyStatus = ref('');
const deviceForm = useForm({ imei: '', proof: '', name: '' });
const layoutForm = useForm({ widgets: [...props.preferences.widgets] });
const filters = useForm({ from: props.filters.from, to: props.filters.to, tracker: props.filters.tracker ?? '', fence: props.filters.fence ?? '', direction: props.filters.direction ?? '' });
const fenceForm = useForm<{ name: string; shape_type: string; shape_geometry: Geometry; entrance_alert_enabled: boolean; exit_alert_enabled: boolean }>({
    name: '', shape_type: 'circle', shape_geometry: { center: undefined, radius: 300 }, entrance_alert_enabled: true, exit_alert_enabled: true,
});
const editing = ref<number | null>(null);
const editorOpen = ref(false);
const deleteId = ref<number | null>(null);
const latitude = ref<number | null>(null);
const longitude = ref<number | null>(null);
const widgets = ['summary', 'map', 'devices', 'activity'];
const widgetNames: Record<string, string> = { summary: 'At a glance', map: 'Latest positions', devices: 'Device list', activity: 'Place activity' };
const visibleTrackers = computed(() => props.trackers.filter(t => `${t.display_name} ${t.imei ?? ''}`.toLowerCase().includes(search.value.toLowerCase())));
const markers = computed(() => visibleTrackers.value.map(t => ({ ...t, label: t.display_name, subtitle: t.last_seen_at ? `Last location ${time(t.last_seen_at)}` : 'Waiting for GPS' })));
const mapCenter = computed<[number, number] | undefined>(() => {
    const device = props.trackers.find(t => t.latitude !== null && t.longitude !== null);
    return device ? [device.latitude!, device.longitude!] : undefined;
});
const command = computed(() => `SERVER,${/^\d+\.\d+\.\d+\.\d+$/.test(props.connection.host) ? 0 : 1},${props.connection.host},${props.connection.port}#`);
const smsLink = computed(() => `sms:${phone.value.replace(/[^+\d]/g, '')}?body=${encodeURIComponent(command.value)}`);
const reportLink = computed(() => '/customer/reports/places.csv?' + new URLSearchParams(Object.entries(filters.data()).filter(([,v]) => v !== '').map(([k,v]) => [k, String(v)])).toString());
const online = computed(() => props.trackers.filter(t => !['offline', 'stale', 'no_data'].includes(t.status)).length);
const showDeviceList = computed(() => props.activeModule === 'my_fleets');
const reportView = computed(() => ['analysis', 'events'].includes(props.activeModule));
const historyView = computed(() => ['playback', 'routes'].includes(props.activeModule));
function time(value: string | null) { return value ? new Date(value).toLocaleString(undefined, { timeZone: 'UTC', dateStyle: 'medium', timeStyle: 'short' }) + ' UTC' : 'No location yet'; }
async function copy() { try { await navigator.clipboard.writeText(command.value); copyStatus.value = 'Command copied'; } catch { copyStatus.value = 'Select and copy the command below.'; } }
function discover() { deviceForm.post('/customer/devices/discover', { preserveScroll: true }); }
function claim() { deviceForm.post('/customer/devices/claim', { onSuccess: () => { deviceForm.reset(); setup.value = false; } }); }
function moveWidget(index: number, direction: number) { const target = index + direction; if (target < 0 || target >= layoutForm.widgets.length) return; [layoutForm.widgets[index], layoutForm.widgets[target]] = [layoutForm.widgets[target]!, layoutForm.widgets[index]!]; }
function saveLayout() { layoutForm.post('/customer/dashboard/preferences', { preserveScroll: true, onSuccess: () => { customize.value = false; } }); }
function applyFilters() { filters.get(`/customer/${props.activeModule === 'analysis' ? 'analysis' : props.activeModule}`, { preserveState: true }); }
function openFence(fence?: Place) {
    editing.value = fence?.id ?? null;
    fenceForm.clearErrors();
    fenceForm.name = fence?.name ?? '';
    fenceForm.shape_type = fence?.shape_type ?? 'circle';
    fenceForm.shape_geometry = fence ? JSON.parse(JSON.stringify(fence.shape_geometry)) : { center: mapCenter.value, radius: 300 };
    fenceForm.entrance_alert_enabled = fence?.entrance_alert_enabled ?? true;
    fenceForm.exit_alert_enabled = fence?.exit_alert_enabled ?? true;
    latitude.value = fenceForm.shape_geometry.center?.[0] ?? null; longitude.value = fenceForm.shape_geometry.center?.[1] ?? null;
    editorOpen.value = true;
}
function changeShape() { fenceForm.shape_geometry = fenceForm.shape_type === 'circle' ? { center: mapCenter.value, radius: 300 } : { points: [] }; }
function pick(point: [number, number]) {
    if (fenceForm.shape_type === 'circle') { fenceForm.shape_geometry.center = point; latitude.value = point[0]; longitude.value = point[1]; }
    else fenceForm.shape_geometry.points = [...(fenceForm.shape_geometry.points ?? []), point];
}
function setCoordinate() { if (latitude.value !== null && longitude.value !== null) pick([Number(latitude.value), Number(longitude.value)]); }
function saveFence() { const options = { preserveScroll: true, onSuccess: () => { editorOpen.value = false; } }; if (editing.value) fenceForm.put(`/customer/places/${editing.value}`, options); else fenceForm.post('/customer/places', options); }
let poll: ReturnType<typeof setInterval>;
onMounted(() => { poll = setInterval(() => { if (!document.hidden && !setup.value && !editorOpen.value && !customize.value) router.reload({ only: ['trackers', 'events', 'counts', 'refreshedAt'] }); }, 15000); });
onUnmounted(() => clearInterval(poll));
</script>

<template>
    <Head :title="title?.[0]" />
    <CustomerShell :active="activeModule" :allowed="allowedModules">
        <div class="page-heading"><div><div class="eyebrow">YOUR WORKSPACE / {{ activeModule === 'dashboard' ? 'OVERVIEW' : 'TRACKING' }}</div><h1>{{ title?.[0] }}</h1><p>{{ title?.[1] }}</p></div><div class="actions">
            <button v-if="activeModule === 'dashboard'" class="btn" :aria-expanded="customize" @click="customize = !customize"><SlidersHorizontal :size="14" /> Customize</button>
            <button v-if="activeModule === 'geofence' && canManageGeofences" class="btn-primary" @click="openFence()"><Plus :size="15" /> New place</button>
            <button v-else-if="canManageDevices" class="btn-primary" :aria-expanded="setup" @click="setup = !setup"><Plus :size="15" /> Connect device</button>
        </div></div>

        <section v-if="customize" class="panel customizer"><div class="panel-head"><h2>Make this dashboard yours</h2><button class="btn" @click="customize = false">Close</button></div><div class="panel-body"><p class="muted">Choose what you see and arrange it in your preferred order.</p><div v-for="(widget, index) in layoutForm.widgets" :key="widget" class="widget-row"><label>{{ widgetNames[widget] }}</label><button class="icon-button" :disabled="index === 0" :aria-label="`Move ${widgetNames[widget]} up`" @click="moveWidget(index, -1)"><ArrowUp :size="15" /></button><button class="icon-button" :disabled="index === layoutForm.widgets.length - 1" :aria-label="`Move ${widgetNames[widget]} down`" @click="moveWidget(index, 1)"><ArrowDown :size="15" /></button><button class="btn" @click="layoutForm.widgets.splice(index, 1)">Hide</button></div><div class="actions"><button v-for="widget in widgets.filter(w => !layoutForm.widgets.includes(w))" :key="widget" class="btn" @click="layoutForm.widgets.push(widget)">Add {{ widgetNames[widget] }}</button></div><p v-for="error in layoutForm.errors" :key="error" class="field-error">{{ error }}</p><button class="btn-primary" :disabled="layoutForm.processing" style="margin-top:16px" @click="saveLayout">{{ layoutForm.processing ? 'Saving…' : 'Save layout' }}</button></div></section>

        <section v-if="setup" class="setup-grid" aria-label="Connect your tracker">
            <div class="panel"><div class="panel-head"><h2><span class="step-number">1</span> Tell your tracker where to connect</h2></div><div class="panel-body"><p class="muted">Keep the tracker powered, with a working data SIM. Send this SMS to the phone number of the SIM inside it.</p><label class="field" style="margin-top:16px">Tracker SIM phone number<input v-model="phone" type="tel" placeholder="Include country code" autocomplete="off" /></label><code class="command">{{ command }}</code><div class="actions"><button class="btn" @click="copy"><Copy :size="14" /> Copy command</button><a v-if="phone.replace(/\D/g, '').length >= 7" :href="smsLink" class="btn-primary"><MessageSquare :size="14" /> Open SMS</a></div><p role="status" class="muted" style="margin-top:10px">{{ copyStatus }}</p><p class="muted" style="margin-top:14px;font-size:12px">You send the message using your phone; your carrier’s SMS charges apply. A successful SMS reply confirms the setting. The tracker must then connect over mobile data. If it stays offline, check the SIM’s APN with your carrier.</p></div></div>
            <div class="panel"><div class="panel-head"><h2><span class="step-number">2</span> Find and claim your device</h2><button class="btn" @click="setup = false">Close</button></div><form class="panel-body" @submit.prevent="discover"><label class="field">Device IMEI<input v-model="deviceForm.imei" inputmode="numeric" maxlength="15" required placeholder="15 digits on your tracker" /></label><label class="field">SIM ICCID or private claim code<input v-model="deviceForm.proof" type="password" autocomplete="off" required /><small>Use the full serial number printed on your SIM or its packaging. If your tracker does not report it, contact support for a claim code. These details keep other people from claiming your device.</small></label><p v-for="error in deviceForm.errors" :key="error" role="alert" class="field-error">{{ error }}</p><button class="btn" :disabled="deviceForm.processing">{{ deviceForm.processing ? 'Checking…' : 'Check connection' }}</button><template v-if="discovery && discovery.imei === deviceForm.imei"><div class="notice" style="margin-top:16px"><strong>Device found</strong><div class="mono">{{ discovery.imei }}</div><small>Last connected {{ time(discovery.last_seen_at) }}</small></div><label class="field">Give it a name<input v-model="deviceForm.name" maxlength="120" placeholder="For example, Family car" /></label><button class="btn-primary" type="button" :disabled="deviceForm.processing || !deviceForm.name.trim()" @click="claim">Claim this device</button></template></form></div>
        </section>

        <div v-if="!trackers.length && ['dashboard','my_fleets','live'].includes(activeModule) && !setup" class="panel empty"><RadioTower :size="29" class="empty-icon" /><h2>Your first device belongs here</h2><p>Connect your tracker with one SMS, verify it’s yours, and start seeing its locations and visits.</p><button v-if="canManageDevices" class="btn-primary" @click="setup = true"><Plus :size="14" /> Connect your first device</button><p v-else>Ask your workspace owner to connect a device.</p></div>

        <template v-if="activeModule === 'dashboard' && trackers.length">
            <template v-for="widget in preferences.widgets" :key="widget">
                <div v-if="widget === 'summary'" class="summary-grid"><div class="summary-card"><span>Your devices</span><strong>{{ trackers.length }}</strong><small>Claimed in this workspace</small></div><div class="summary-card"><span>Recently reporting</span><strong>{{ online }}</strong><small>Location in the last 10 minutes</small></div><div class="summary-card"><span>Arrivals</span><strong>{{ counts.geofence_entry ?? 0 }}</strong><small>Selected 7-day period, UTC</small></div><div class="summary-card"><span>Departures</span><strong>{{ counts.geofence_exit ?? 0 }}</strong><small>Selected 7-day period, UTC</small></div></div>
                <section v-if="widget === 'map'" class="panel"><div class="panel-head"><h2>Latest positions</h2><Link v-if="allowedModules.includes('live')" href="/customer/live" class="btn">Open live map</Link></div><MTrackOpenStreetMap :markers="markers" min-height="370px" :zoom="12" /></section>
                <section v-if="widget === 'devices'" class="panel"><div class="panel-head"><h2>Your devices</h2><span class="muted">{{ trackers.length }} connected to your workspace</span></div><div class="table-wrap"><table class="customer-table"><thead><tr><th>Device</th><th>Status</th><th>Speed</th><th>Last location</th></tr></thead><tbody><tr v-for="tracker in trackers" :key="tracker.id"><td><strong>{{ tracker.display_name }}</strong><small class="mono">{{ tracker.imei }}</small></td><td><span class="status" :class="{ online: !['offline','no_data','stale'].includes(tracker.status) }">{{ tracker.status.replace('_',' ') }}</span></td><td>{{ tracker.speed === null ? '—' : `${tracker.speed.toFixed(0)} km/h` }}</td><td>{{ time(tracker.last_seen_at) }}</td></tr></tbody></table></div></section>
                <section v-if="widget === 'activity'" class="panel"><div class="panel-head"><h2>Recent place activity</h2><Link v-if="allowedModules.includes('events')" href="/customer/events" class="btn">View all</Link></div><div v-if="!events.data.length" class="empty"><h2>No crossings recorded yet</h2><p>Create a place to start tracking arrivals and departures. The first position establishes a baseline.</p></div><div v-else class="table-wrap"><table class="customer-table"><thead><tr><th>Device</th><th>Movement</th><th>Place</th><th>Time</th></tr></thead><tbody><tr v-for="event in events.data.slice(0, 5)" :key="event.id"><td>{{ event.device }}</td><td>{{ event.type === 'geofence_entry' ? 'Arrived' : 'Departed' }}</td><td>{{ event.place }}</td><td>{{ time(event.occurred_at) }}</td></tr></tbody></table></div></section>
            </template><div v-if="!preferences.widgets.length" class="panel empty"><h2>A clear canvas</h2><p>Use Customize to add your map, devices, activity, or summary.</p></div>
        </template>

        <section v-if="activeModule === 'live' || (showDeviceList && trackers.length)" class="panel"><div class="panel-head"><h2>{{ showDeviceList ? 'Connected devices' : 'Latest reported locations' }}</h2><label class="field" style="margin:0"><input v-model="search" aria-label="Search your devices" placeholder="Search devices…" style="margin:0" /></label></div><MTrackOpenStreetMap v-if="activeModule === 'live'" :markers="markers" min-height="580px" :zoom="12" /><div v-else class="table-wrap"><table class="customer-table"><thead><tr><th>Device</th><th>Status</th><th>Last location</th><th>Actions</th></tr></thead><tbody><tr v-for="tracker in visibleTrackers" :key="tracker.id"><td><strong>{{ tracker.display_name }}</strong><small class="mono">{{ tracker.imei }}</small></td><td><span class="status" :class="{ online: tracker.status !== 'offline' && tracker.status !== 'no_data' }">{{ tracker.status.replace('_',' ') }}</span></td><td>{{ time(tracker.last_seen_at) }}</td><td><Link v-if="allowedModules.includes('playback')" :href="`/customer/playback?tracker=${tracker.id}`" class="btn">Journey history</Link></td></tr><tr v-if="!visibleTrackers.length"><td colspan="4">No devices match your search.</td></tr></tbody></table></div></section>

        <template v-if="activeModule === 'geofence'">
            <section v-if="editorOpen" class="panel"><div class="panel-head"><h2>{{ editing ? 'Edit place' : 'Create a place' }}</h2><button class="btn" @click="editorOpen = false">Cancel</button></div><form class="panel-body editor-grid" @submit.prevent="saveFence"><div><label class="field">Place name<input v-model="fenceForm.name" required maxlength="120" placeholder="Home, office, or a delivery zone" /></label><label class="field">Boundary shape<select v-model="fenceForm.shape_type" @change="changeShape"><option value="circle">Circle</option><option value="polygon">Polygon</option></select></label><template v-if="fenceForm.shape_type === 'circle'"><div class="field-row"><label class="field">Latitude<input v-model.number="latitude" type="number" step="any" min="-90" max="90" @change="setCoordinate" /></label><label class="field">Longitude<input v-model.number="longitude" type="number" step="any" min="-180" max="180" @change="setCoordinate" /></label></div><label class="field">Radius (meters)<input v-model.number="fenceForm.shape_geometry.radius" type="number" min="10" max="100000" required /></label></template><template v-else><p class="muted">Click at least three points on the map, or enter coordinates below.</p><div class="field-row"><label class="field">Point latitude<input v-model.number="latitude" type="number" step="any" min="-90" max="90" /></label><label class="field">Point longitude<input v-model.number="longitude" type="number" step="any" min="-180" max="180" /></label></div><div class="actions"><button class="btn" type="button" @click="setCoordinate">Add point</button><button class="btn" type="button" @click="fenceForm.shape_geometry.points?.pop()">Undo point</button><span class="muted">{{ fenceForm.shape_geometry.points?.length ?? 0 }} points</span></div></template><label class="check"><input v-model="fenceForm.entrance_alert_enabled" type="checkbox" /> Record arrivals</label><label class="check"><input v-model="fenceForm.exit_alert_enabled" type="checkbox" /> Record departures</label><p class="muted" style="font-size:12px">Applies to all devices in your workspace. Editing a boundary resets its crossing baseline.</p><p v-for="error in fenceForm.errors" :key="error" class="field-error">{{ error }}</p><button class="btn-primary" :disabled="fenceForm.processing" style="margin-top:15px">{{ fenceForm.processing ? 'Saving…' : 'Save place' }}</button></div><div><PlaceMap :key="editing ?? 'new'" :shape="fenceForm.shape_type" :geometry="fenceForm.shape_geometry" :center="mapCenter" editable @pick="pick" /><p class="muted" style="margin-top:10px;font-size:12px">{{ fenceForm.shape_type === 'circle' ? 'Click the map to choose the center, or enter coordinates.' : 'Click around the boundary in order. Close the shape by saving.' }}</p></div></form></section>
            <div v-if="!geofences.length && !editorOpen" class="panel empty"><MapPin :size="28" class="empty-icon" /><h2>Give your journeys familiar places</h2><p>Add home, work, or a delivery zone. We’ll record when your devices cross its boundary.</p><button v-if="canManageGeofences" class="btn-primary" @click="openFence()">Create your first place</button></div>
            <div class="place-grid"><section v-for="place in geofences" :key="place.id" class="panel place-card"><div class="panel-head"><h2>{{ place.name }}</h2><span class="muted">{{ place.shape_type }}</span></div><PlaceMap :shape="place.shape_type" :geometry="place.shape_geometry" /><div class="panel-body"><p class="muted">Arrivals {{ place.entrance_alert_enabled ? 'on' : 'off' }} · Departures {{ place.exit_alert_enabled ? 'on' : 'off' }}</p><div v-if="canManageGeofences" class="actions" style="margin-top:12px"><button class="btn" @click="openFence(place)">Edit place</button><button class="btn-danger" @click="deleteId = place.id">Delete</button></div><div v-if="deleteId === place.id" class="notice" style="margin-top:12px"><p>Delete this boundary? Historical events will remain.</p><div class="actions" style="margin-top:10px"><button class="btn" @click="deleteId = null">Cancel</button><button class="btn-danger" @click="router.delete(`/customer/places/${place.id}`, { preserveScroll: true, onSuccess: () => { deleteId = null; } })">Delete place</button></div></div></div></section></div>
        </template>

        <template v-if="reportView || historyView"><section class="panel"><div class="panel-body"><form class="filters" @submit.prevent="applyFilters"><label class="field">From (UTC)<input v-model="filters.from" type="date" required /></label><label class="field">Through (UTC)<input v-model="filters.to" type="date" required /></label><label class="field">Device<select v-model="filters.tracker"><option value="">{{ historyView ? 'Choose a device' : 'All devices' }}</option><option v-for="tracker in trackers" :key="tracker.id" :value="tracker.id">{{ tracker.display_name }}</option></select></label><template v-if="reportView"><label class="field">Place<select v-model="filters.fence"><option value="">All places</option><option v-for="place in geofences" :key="place.id" :value="place.id">{{ place.name }}</option></select></label><label class="field">Movement<select v-model="filters.direction"><option value="">Arrivals & departures</option><option value="geofence_entry">Arrivals</option><option value="geofence_exit">Departures</option></select></label></template><button class="btn-primary" :disabled="filters.processing">Apply</button></form><p v-for="error in filters.errors" :key="error" class="field-error">{{ error }}</p></div></section>
            <section v-if="historyView" class="panel"><div class="panel-head"><h2>Reported route</h2><span class="muted">{{ history.length }} points · maximum 2,000, earliest first</span></div><MTrackOpenStreetMap v-if="history.length" :path="history" min-height="530px" :zoom="13" /><div v-else class="empty"><h2>{{ filters.tracker ? 'No positions in this period' : 'Choose a device to see its journey' }}</h2><p>Routes use recorded GPS positions. Gaps between reports do not show the exact path travelled.</p></div></section>
            <template v-if="reportView"><div class="summary-grid"><div class="summary-card"><span>Arrivals</span><strong>{{ counts.geofence_entry ?? 0 }}</strong><small>Within the applied filters</small></div><div class="summary-card"><span>Departures</span><strong>{{ counts.geofence_exit ?? 0 }}</strong><small>Within the applied filters</small></div></div><section class="panel"><div class="panel-head"><h2>{{ events.total }} crossing records</h2><a v-if="allowedModules.includes('analysis')" :href="reportLink" class="btn"><Download :size="14" /> Export CSV</a></div><div v-if="!events.data.length" class="empty"><h2>No crossings match these filters</h2><p>Try another date range or device. Entry and exit records begin after a place has a position baseline.</p></div><div v-else class="table-wrap"><table class="customer-table"><thead><tr><th>Time (UTC)</th><th>Device</th><th>Place</th><th>Movement</th></tr></thead><tbody><tr v-for="event in events.data" :key="event.id"><td>{{ time(event.occurred_at) }}</td><td>{{ event.device }}</td><td>{{ event.place }}</td><td><span class="status" :class="{ online: event.type === 'geofence_entry' }">{{ event.type === 'geofence_entry' ? 'Arrived' : 'Departed' }}</span></td></tr></tbody></table></div><div class="pagination"><span>{{ events.from ?? 0 }}–{{ events.to ?? 0 }} of {{ events.total }}</span><div class="actions"><Link v-if="events.prev_page_url" :href="events.prev_page_url" class="btn">Previous</Link><Link v-if="events.next_page_url" :href="events.next_page_url" class="btn">Next</Link></div></div></section></template>
        </template>
        <div class="refresh-note actions"><RefreshCw :size="12" /><span>Updated {{ time(refreshedAt) }} · checks every 15 seconds</span><button class="btn" @click="router.reload()">Refresh now</button></div>
    </CustomerShell>
</template>
