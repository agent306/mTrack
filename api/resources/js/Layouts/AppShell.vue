<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Bell,
    Building2,
    CreditCard,
    FileText,
    Gauge,
    Map,
    MapPinned,
    PlayCircle,
    RadioTower,
    Route,
    Settings,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import type { Component } from 'vue';
import type { PageProps } from '../types';

defineProps<{
    title: string;
    description?: string;
}>();

const page = usePage<PageProps>();

const navItems: Array<{ label: string; icon: Component; active?: boolean }> = [
    { label: 'Dashboard', icon: Gauge, active: true },
    { label: 'Live', icon: MapPinned },
    { label: 'Playback', icon: PlayCircle },
    { label: 'Events', icon: Bell },
    { label: 'Devices', icon: RadioTower },
    { label: 'Geofence', icon: Map },
    { label: 'Routes', icon: Route },
    { label: 'Customers', icon: Building2 },
    { label: 'Payments', icon: CreditCard },
    { label: 'Logs', icon: FileText },
    { label: 'Users & Roles', icon: Users },
    { label: 'Settings', icon: Settings },
];

const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-screen bg-page text-body">
        <aside class="fixed inset-y-0 left-0 hidden w-64 border-r border-line bg-primary-dark text-white lg:flex lg:flex-col">
            <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
                <div class="grid size-9 place-items-center rounded-mtrack-md bg-brand font-bold text-primary-dark">m</div>
                <div>
                    <div class="text-sm font-bold leading-5">mTrack</div>
                    <div class="text-xs text-white/55">Fleet operations</div>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <button
                    v-for="item in navItems"
                    :key="item.label"
                    type="button"
                    :class="[
                        'flex h-10 w-full items-center gap-3 rounded-mtrack-sm px-3 text-left text-sm font-medium transition',
                        item.active ? 'bg-brand text-primary-dark' : 'text-white/72 hover:bg-white/8 hover:text-white',
                    ]"
                >
                    <component :is="item.icon" class="size-4 shrink-0" :stroke-width="2" />
                    <span class="truncate">{{ item.label }}</span>
                </button>
            </nav>

            <div class="border-t border-white/10 p-4">
                <div class="rounded-mtrack-md bg-white/8 p-3">
                    <div class="text-xs text-white/50">Signed in</div>
                    <div class="mt-1 truncate text-sm font-semibold">{{ page.props.auth.user?.name }}</div>
                    <button type="button" class="mt-3 text-xs font-semibold text-brand hover:text-info" @click="logout">
                        Sign out
                    </button>
                </div>
            </div>
        </aside>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-10 border-b border-line bg-surface/95 backdrop-blur">
                <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-xs font-semibold text-muted">
                            <ShieldCheck class="size-4 text-brand" />
                            <span>{{ page.props.auth.tenant?.name ?? 'Platform workspace' }}</span>
                        </div>
                        <h1 class="truncate text-[24px] font-bold leading-8 text-body">{{ title }}</h1>
                    </div>
                    <div class="hidden items-center gap-3 sm:flex">
                        <div class="rounded-full border border-line bg-muted-surface px-3 py-1 text-xs font-medium text-muted">
                            Foundation
                        </div>
                        <Link href="/dashboard" class="rounded-mtrack-sm bg-primary-dark px-4 py-2 text-sm font-semibold text-white">
                            Workspace
                        </Link>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <div v-if="description" class="mb-5 max-w-3xl text-sm leading-6 text-muted">
                    {{ description }}
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>
