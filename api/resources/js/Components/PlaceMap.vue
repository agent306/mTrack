<script setup lang="ts">
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
export type Geometry = { center?: [number, number]; radius?: number; points?: [number, number][] };
const props = defineProps<{ geometry: Geometry; shape: string; editable?: boolean; center?: [number, number] }>();
const emit = defineEmits<{ pick: [point: [number, number]] }>();
const element = ref<HTMLElement>();
let map: L.Map;
let layer: L.LayerGroup;
function draw() {
    if (!layer) return;
    layer.clearLayers();
    if (props.shape === 'circle' && props.geometry.center?.every(Number.isFinite)) {
        L.circle(props.geometry.center, { radius: Number(props.geometry.radius) || 100, color: 'var(--c-accent)', weight: 2 }).addTo(layer);
        if (!map.getBounds().contains(props.geometry.center)) map.panTo(props.geometry.center, { animate: false });
    } else if (props.geometry.points?.length) {
        L.polygon(props.geometry.points, { color: 'var(--c-accent)', weight: 2 }).addTo(layer);
        props.geometry.points.forEach(p => L.circleMarker(p, { radius: 4, color: 'var(--c-accent)' }).addTo(layer));
        const last = props.geometry.points.at(-1);
        if (last && !map.getBounds().contains(last)) map.panTo(last, { animate: false });
    }
}
onMounted(() => {
    map = L.map(element.value!).setView(props.geometry.center ?? props.geometry.points?.[0] ?? props.center ?? [3.2028, 73.2207], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
    layer = L.layerGroup().addTo(map);
    map.on('click', e => { if (props.editable) emit('pick', [Number(e.latlng.lat.toFixed(7)), Number(e.latlng.lng.toFixed(7))]); });
    draw();
    if (props.shape === 'circle' && props.geometry.center) map.fitBounds(L.circle(props.geometry.center, { radius: props.geometry.radius ?? 300 }).getBounds(), { padding: [30,30], maxZoom: 16 });
    else if ((props.geometry.points?.length ?? 0) >= 3) map.fitBounds(L.latLngBounds(props.geometry.points!), { padding: [30,30], maxZoom: 16 });
});
watch(() => props.geometry, draw, { deep: true });
watch(() => props.shape, draw);
onBeforeUnmount(() => map?.remove());
</script>
<template><div ref="element" class="place-map" aria-label="Place boundary map" /></template>
