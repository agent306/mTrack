<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Activity, Bell, Database, RadioTower, ShieldCheck, Wifi } from '@lucide/vue';
import MTrackMetricCard from '../../Components/MTrackMetricCard.vue';
import MTrackState from '../../Components/MTrackState.vue';
import AppShell from '../../Layouts/AppShell.vue';

defineProps<{
    surface: 'platform' | 'customer';
}>();

const foundations = [
    { label: 'Tenancy', value: 'Ready', helper: 'Tenant, user, roles, and audit primitives', icon: ShieldCheck, tone: 'success' },
    { label: 'Queues', value: 'Redis', helper: 'Horizon installed for workers and monitoring', icon: Activity, tone: 'brand' },
    { label: 'Realtime', value: 'Reverb', helper: 'Broadcasting config prepared for live tracking', icon: Wifi, tone: 'info' },
    { label: 'Auth', value: 'Passwordless', helper: 'Magic links, Google, Sanctum mobile token base', icon: RadioTower, tone: 'warning' },
] as const;

const upcoming = [
    'Tracker/device registry',
    'Fleet groups and permissions',
    'Raw payload ingestion endpoint',
    'Parser contract interface',
    'Normalized location events',
];
</script>

<template>
    <Head title="Foundation" />

    <AppShell
        title="Foundation"
        description="The mTrack base application is wired for tenant-aware operations, passwordless access, queues, and realtime delivery. Feature pages start in later phases."
    >
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <MTrackMetricCard
                v-for="item in foundations"
                :key="item.label"
                :label="item.label"
                :value="item.value"
                :helper="item.helper"
                :icon="item.icon"
                :tone="item.tone"
            />
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
            <div class="rounded-mtrack-md border border-line bg-surface shadow-card">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <div>
                        <h2 class="text-lg font-bold leading-7">Routing structure</h2>
                        <p class="text-sm text-muted">Shell slots follow the approved admin and customer modules.</p>
                    </div>
                    <span class="rounded-full bg-muted-surface px-3 py-1 text-xs font-semibold text-muted">{{ surface }}</span>
                </div>
                <div class="grid gap-px bg-line md:grid-cols-2">
                    <div class="bg-surface p-5">
                        <div class="flex items-center gap-2 text-sm font-bold">
                            <Database class="size-4 text-brand" />
                            Admin web
                        </div>
                        <p class="mt-2 text-sm leading-6 text-muted">
                            Dashboard, live, playback, events, devices, geofence, routes, customers, payments, and logs.
                        </p>
                    </div>
                    <div class="bg-surface p-5">
                        <div class="flex items-center gap-2 text-sm font-bold">
                            <Bell class="size-4 text-warning" />
                            Customer web
                        </div>
                        <p class="mt-2 text-sm leading-6 text-muted">
                            Dashboard, live, playback, events, fleets, geofence, analysis, routes, settings, billing, and audit log.
                        </p>
                    </div>
                </div>
            </div>

            <aside class="rounded-mtrack-md border border-line bg-surface p-5 shadow-card">
                <h2 class="text-lg font-bold leading-7">Next phase hooks</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="item in upcoming" :key="item" class="flex items-start gap-3 rounded-mtrack-sm bg-muted-surface p-3">
                        <span class="mt-1 size-2 rounded-full bg-brand" />
                        <span class="text-sm leading-6 text-body">{{ item }}</span>
                    </div>
                </div>
            </aside>
        </section>

        <section class="mt-6 grid gap-4 lg:grid-cols-3">
            <MTrackState title="Empty state" message="Use this pattern when a fleet, report, or diagnostics table has no records yet." />
            <MTrackState kind="loading" title="Loading state" message="Use compact loading states for maps, tables, and analytics panels." />
            <MTrackState kind="error" title="Error state" message="Pair errors with concrete text and a retry action when the workflow supports it." />
        </section>
    </AppShell>
</template>
