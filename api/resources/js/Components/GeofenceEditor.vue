<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PlaceMap, { type Geometry } from './PlaceMap.vue';
import type { Fence, Tracker } from './TrackingTypes';
const props = defineProps<{ fence: Fence | null; trackers: Tracker[]; basePath: string; isAdmin: boolean; tenants: { id: number; name: string }[]; selectedTenant: number | null }>();
const emit = defineEmits<{ close: [] }>();
const first = props.trackers.find(t => t.latitude !== null && t.longitude !== null);
const center: [number, number] | undefined = first ? [first.latitude!, first.longitude!] : undefined;
const form = useForm({ name: props.fence?.name ?? '', tenant_id: props.fence?.tenant_id ?? props.selectedTenant ?? '', shape_type: props.fence?.shape_type ?? 'circle', shape_geometry: (props.fence ? JSON.parse(JSON.stringify(props.fence.shape_geometry)) : { center, radius: 300 }) as Geometry, entrance_alert_enabled: props.fence?.entrance_alert_enabled ?? true, exit_alert_enabled: props.fence?.exit_alert_enabled ?? true, tracker_ids: [...(props.fence?.tracker_ids ?? [])] });
const latitude = ref<number | null>(form.shape_geometry.center?.[0] ?? null);
const longitude = ref<number | null>(form.shape_geometry.center?.[1] ?? null);
const devices = computed(() => props.trackers.filter(t => !props.isAdmin || t.tenant_id === Number(form.tenant_id)));
const ready = computed(() => form.name.trim() && (form.shape_type === 'circle' ? form.shape_geometry.center?.every(Number.isFinite) : (form.shape_geometry.points?.length ?? 0) >= 3));
function pick(point: [number, number]) { if (form.shape_type === 'circle') { form.shape_geometry.center = point; latitude.value = point[0]; longitude.value = point[1]; } else form.shape_geometry.points = [...(form.shape_geometry.points ?? []), point]; }
function addCoordinate() { if (latitude.value !== null && longitude.value !== null && Math.abs(latitude.value) <= 90 && Math.abs(longitude.value) <= 180) pick([Number(latitude.value), Number(longitude.value)]); }
function shapeChanged() { form.shape_geometry = form.shape_type === 'circle' ? { center, radius: 300 } : { points: [] }; latitude.value = center?.[0] ?? null; longitude.value = center?.[1] ?? null; }
function save() { const options = { preserveScroll: true, onSuccess: () => emit('close') }; if (props.fence) form.put(`${props.basePath}/places/${props.fence.id}`, options); else form.post(`${props.basePath}/places`, options); }
</script>
<template>
    <section class="panel" aria-labelledby="fence-editor-title">
        <div class="panel-head"><div><h2 id="fence-editor-title">{{ fence ? 'Edit geofence' : 'Create geofence' }}</h2><p class="muted">Define the boundary and choose which devices to monitor.</p></div><button class="btn" :disabled="form.processing" @click="emit('close')">Cancel</button></div>
        <form @submit.prevent="save"><div class="editor-grid panel-body"><div class="editor-controls">
            <div class="section-label">01 · Identity and scope</div>
            <label v-if="isAdmin" class="field">Customer<select v-model="form.tenant_id" required :disabled="!!fence" @change="form.tracker_ids = []"><option value="">Choose customer</option><option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">{{ tenant.name }}</option></select><span class="field-error">{{ form.errors.tenant_id }}</span></label>
            <label class="field">Geofence name<input v-model="form.name" required maxlength="120" placeholder="Home, office, or delivery zone" /><span class="field-error">{{ form.errors.name }}</span></label>
            <div class="section-label">02 · Boundary</div><label class="field">Shape<select v-model="form.shape_type" @change="shapeChanged"><option value="circle">Circle · center and radius</option><option value="polygon">Polygon · custom boundary</option></select></label>
            <div class="field-row"><label class="field">Latitude<input v-model.number="latitude" type="number" min="-90" max="90" step="any" /></label><label class="field">Longitude<input v-model.number="longitude" type="number" min="-180" max="180" step="any" /></label></div>
            <div class="actions"><button class="btn" type="button" @click="addCoordinate">{{ form.shape_type === 'circle' ? 'Set center' : 'Add boundary point' }}</button><button v-if="form.shape_type === 'polygon'" class="btn" type="button" :disabled="!form.shape_geometry.points?.length" @click="form.shape_geometry.points?.pop()">Undo last point</button></div>
            <label v-if="form.shape_type === 'circle'" class="field space-top">Radius in meters<input v-model.number="form.shape_geometry.radius" required type="number" min="10" max="100000" /><small>10–100,000 meters from the center.</small></label>
            <div v-else class="point-list"><p class="field-hint">{{ form.shape_geometry.points?.length ?? 0 }} points · at least 3 required</p><ol><li v-for="(point, index) in form.shape_geometry.points" :key="index"><span class="mono">{{ index + 1 }}. {{ point[0] }}, {{ point[1] }}</span><button class="icon-button" type="button" :aria-label="`Remove point ${index + 1}`" @click="form.shape_geometry.points?.splice(index, 1)">×</button></li></ol></div>
            <div class="section-label space-top">03 · Monitoring</div><fieldset class="device-checks"><legend>Devices</legend><p class="field-hint">Leave all unchecked to monitor every device belonging to this customer, including future devices.</p><label v-for="device in devices" :key="device.id" class="check"><input v-model="form.tracker_ids" type="checkbox" :value="device.id" />{{ device.display_name }}</label><p v-if="!devices.length" class="muted">No devices in this scope yet.</p></fieldset>
            <label class="check"><input v-model="form.entrance_alert_enabled" type="checkbox" /> Record entries</label><label class="check"><input v-model="form.exit_alert_enabled" type="checkbox" /> Record exits</label>
        </div><div class="editor-preview"><div class="preview-label">Boundary preview · unsaved</div><PlaceMap :shape="form.shape_type" :geometry="form.shape_geometry" :center="center" editable @pick="pick" /><p class="field-hint">{{ form.shape_type === 'circle' ? 'Click the map to position the center. Adjust coverage using the radius.' : 'Click around the boundary in order. Use Undo to correct the last point.' }} Coordinates can also be entered using the fields.</p><div class="notice">Saving starts a new inside/outside baseline with the next location. Only later crossings generate events. Existing event history is retained.</div></div></div>
        <footer class="save-bar"><div><p v-for="(error, key) in form.errors" :key="key" class="field-error">{{ error }}</p><span class="muted">{{ form.tracker_ids.length ? `${form.tracker_ids.length} selected devices` : 'All devices in the customer workspace' }}</span></div><div class="actions"><button class="btn" type="button" :disabled="form.processing" @click="emit('close')">Cancel</button><button class="btn-primary" :disabled="!ready || form.processing">{{ form.processing ? 'Saving…' : 'Save geofence' }}</button></div></footer></form>
    </section>
</template>
