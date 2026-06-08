<script setup lang="ts">
import L, { type LayerGroup, type Map as LeafletMap } from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

export type MTrackMapPoint = {
    id: number | string;
    label: string;
    subtitle?: string | null;
    latitude: number | null;
    longitude: number | null;
    status?: string | null;
    speed?: number | null;
};

const props = withDefaults(
    defineProps<{
        markers?: MTrackMapPoint[];
        path?: MTrackMapPoint[];
        minHeight?: string;
        zoom?: number;
    }>(),
    {
        markers: () => [],
        path: () => [],
        minHeight: '560px',
        zoom: 7,
    },
);

const mapElement = ref<HTMLDivElement | null>(null);
let map: LeafletMap | null = null;
let overlayLayer: LayerGroup | null = null;

const validMarkers = computed(() => props.markers.filter(hasCoordinates));
const validPath = computed(() => props.path.filter(hasCoordinates));

function hasCoordinates(point: MTrackMapPoint): point is MTrackMapPoint & { latitude: number; longitude: number } {
    return point.latitude !== null && point.longitude !== null && Number.isFinite(point.latitude) && Number.isFinite(point.longitude);
}

function escapeHtml(value: string): string {
    return value.replace(/[&<>"']/g, (character) => {
        const entities: Record<string, string> = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        };

        return entities[character] ?? character;
    });
}

function markerTone(status?: string | null): string {
    if (['moving', 'active', 'online', 'approved', 'processed'].includes(status ?? '')) {
        return '#14b8a6';
    }

    if (['idle', 'pending', 'received'].includes(status ?? '')) {
        return '#f97316';
    }

    if (['offline', 'rejected', 'expired', 'stale', 'open'].includes(status ?? '')) {
        return '#ef4444';
    }

    return '#0b1026';
}

function markerIcon(point: MTrackMapPoint) {
    const initial = escapeHtml(point.label.trim().slice(0, 1).toUpperCase() || 'M');
    const color = markerTone(point.status);

    return L.divIcon({
        className: 'mtrack-leaflet-marker',
        html: `<span style="background:${color}">${initial}</span>`,
        iconSize: [34, 34],
        iconAnchor: [17, 17],
        popupAnchor: [0, -18],
    });
}

function renderMap(): void {
    if (!map || !overlayLayer) {
        return;
    }

    const layer = overlayLayer;

    layer.clearLayers();

    const bounds: [number, number][] = [];
    const pathCoordinates = validPath.value.map((point) => [point.latitude, point.longitude] as [number, number]);

    if (pathCoordinates.length > 1) {
        L.polyline(pathCoordinates, {
            color: '#0b1026',
            opacity: 0.74,
            weight: 4,
        }).addTo(layer);

        pathCoordinates.forEach((coordinate) => bounds.push(coordinate));
    }

    validMarkers.value.forEach((point) => {
        bounds.push([point.latitude, point.longitude]);

        const popup = [
            `<strong>${escapeHtml(point.label)}</strong>`,
            point.subtitle ? `<span>${escapeHtml(point.subtitle)}</span>` : null,
            point.speed !== null && point.speed !== undefined ? `<span>${Number(point.speed).toFixed(1)} km/h</span>` : null,
        ]
            .filter(Boolean)
            .join('<br>');

        L.marker([point.latitude, point.longitude], { icon: markerIcon(point) }).bindPopup(popup).addTo(layer);
    });

    if (bounds.length > 1) {
        map.fitBounds(bounds, {
            maxZoom: Math.max(props.zoom, 13),
            padding: [48, 48],
        });
    } else if (bounds.length === 1) {
        map.setView(bounds[0], props.zoom);
    } else {
        map.setView([3.2028, 73.2207], props.zoom);
    }

    nextTick(() => map?.invalidateSize());
}

onMounted(() => {
    if (!mapElement.value) {
        return;
    }

    map = L.map(mapElement.value, {
        attributionControl: true,
        zoomControl: true,
        scrollWheelZoom: true,
    }).setView([3.2028, 73.2207], props.zoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    overlayLayer = L.layerGroup().addTo(map);
    renderMap();
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    overlayLayer = null;
});

watch([validMarkers, validPath], renderMap, { deep: true });
</script>

<template>
    <div class="relative overflow-hidden rounded-mtrack-md bg-muted-surface" :style="{ minHeight }">
        <div ref="mapElement" class="absolute inset-0" />
        <div v-if="validMarkers.length === 0 && validPath.length === 0" class="pointer-events-none absolute inset-x-4 top-4 rounded-mtrack-sm border border-line bg-white/95 px-4 py-3 text-sm font-semibold text-body shadow-card">
            No live coordinates yet
        </div>
    </div>
</template>
