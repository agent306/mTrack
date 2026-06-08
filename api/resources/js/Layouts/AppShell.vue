<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
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
    Wallet,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import MTrackBottomNav from '../Components/MTrackBottomNav.vue';
import MTrackSideNav from '../Components/MTrackSideNav.vue';
import type { PageProps } from '../types';

const props = withDefaults(
    defineProps<{
    title: string;
    description?: string;
    surface?: 'admin' | 'customer' | 'foundation';
    allowedModules?: string[];
}>(),
    {
        surface: 'foundation',
        allowedModules: () => [],
    },
);

const page = usePage<PageProps>();

const isActive = (href: string) => page.url === href || page.url.startsWith(`${href}/`);

const adminNavItems: Array<{ label: string; module?: string; icon: Component; href?: string; active?: boolean; disabled?: boolean }> = [
    { label: 'Dashboard', icon: Gauge, href: '/admin/dashboard', active: isActive('/admin/dashboard') || page.url === '/dashboard' },
    { label: 'Live', icon: MapPinned, href: '/admin/live', active: isActive('/admin/live') },
    { label: 'Playback', icon: PlayCircle, href: '/admin/playback', active: isActive('/admin/playback') },
    { label: 'Events', icon: Bell, href: '/admin/events', active: isActive('/admin/events') },
    { label: 'Devices', icon: RadioTower, href: '/admin/devices', active: isActive('/admin/devices') },
    { label: 'Geofence', icon: Map, href: '/admin/geofence', active: isActive('/admin/geofence') },
    { label: 'Routes', icon: Route, href: '/admin/routes', active: isActive('/admin/routes') },
    { label: 'Customers', icon: Building2, href: '/admin/customers', active: isActive('/admin/customers') },
    { label: 'Payments', icon: CreditCard, href: '/admin/payments', active: isActive('/admin/payments') },
    { label: 'Logs', icon: FileText, href: '/admin/logs', active: isActive('/admin/logs') },
    { label: 'Users & Roles', icon: Users, disabled: true },
    { label: 'Settings', icon: Settings, disabled: true },
];

const customerNavItems: Array<{ label: string; module?: string; icon: Component; href?: string; active?: boolean; disabled?: boolean }> = [
    { label: 'Dashboard', module: 'dashboard', icon: Gauge, href: '/customer/dashboard', active: isActive('/customer/dashboard') || page.url === '/dashboard' },
    { label: 'Live', module: 'live', icon: MapPinned, href: '/customer/live', active: isActive('/customer/live') },
    { label: 'Playback', module: 'playback', icon: PlayCircle, href: '/customer/playback', active: isActive('/customer/playback') },
    { label: 'Events', module: 'events', icon: Bell, href: '/customer/events', active: isActive('/customer/events') },
    { label: 'My Fleets', module: 'my_fleets', icon: RadioTower, href: '/customer/my-fleets', active: isActive('/customer/my-fleets') },
    { label: 'Geofence', module: 'geofence', icon: Map, href: '/customer/geofence', active: isActive('/customer/geofence') },
    { label: 'Analysis', module: 'analysis', icon: BarChart3, href: '/customer/analysis', active: isActive('/customer/analysis') },
    { label: 'Routes', module: 'routes', icon: Route, href: '/customer/routes', active: isActive('/customer/routes') },
    { label: 'Setting', module: 'settings', icon: Settings, href: '/customer/setting', active: isActive('/customer/setting') },
    { label: 'Billing', module: 'billing', icon: Wallet, href: '/customer/billing', active: isActive('/customer/billing') },
    { label: 'Audit Log', module: 'audit_log', icon: FileText, href: '/customer/audit-log', active: isActive('/customer/audit-log') },
];

const navItems = computed(() => {
    if (props.surface === 'customer') {
        const allowed = new Set(props.allowedModules);

        return customerNavItems.filter((item) => !item.module || allowed.has(item.module));
    }

    return adminNavItems;
});

const adminMobileNavItems: Array<{ label: string; module?: string; icon: Component; href?: string; active?: boolean; disabled?: boolean }> = [
    { label: 'Dashboard', icon: Gauge, href: '/admin/dashboard', active: isActive('/admin/dashboard') || page.url === '/dashboard' },
    { label: 'Logs', icon: FileText, href: '/admin/logs', active: isActive('/admin/logs') },
    { label: 'Live', icon: MapPinned, href: '/admin/live', active: isActive('/admin/live') },
    { label: 'Playback', icon: PlayCircle, href: '/admin/playback', active: isActive('/admin/playback') },
    { label: 'Payments', icon: CreditCard, href: '/admin/payments', active: isActive('/admin/payments') },
];

const customerMobileNavItems = computed(() => {
    const allowed = new Set(props.allowedModules);

    return [
        { label: 'Dashboard', module: 'dashboard', icon: Gauge, href: '/customer/dashboard', active: isActive('/customer/dashboard') || page.url === '/dashboard' },
        { label: 'Events', module: 'events', icon: Bell, href: '/customer/events', active: isActive('/customer/events') },
        { label: 'Live', module: 'live', icon: MapPinned, href: '/customer/live', active: isActive('/customer/live') },
        { label: 'Playback', module: 'playback', icon: PlayCircle, href: '/customer/playback', active: isActive('/customer/playback') },
        { label: 'Setting', module: 'settings', icon: Settings, href: '/customer/setting', active: isActive('/customer/setting') },
    ].filter((item) => allowed.has(item.module));
});

const mobileNavItems = computed(() => (props.surface === 'customer' ? customerMobileNavItems.value : adminMobileNavItems));
const workspaceLabel = computed(() => (props.surface === 'customer' ? 'Customer web' : props.surface === 'admin' ? 'Admin web' : 'Foundation'));
const workspaceHref = computed(() => (props.surface === 'customer' ? '/customer/dashboard' : '/admin/dashboard'));

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

            <MTrackSideNav :items="navItems" />

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
                            {{ workspaceLabel }}
                        </div>
                        <Link :href="workspaceHref" class="rounded-mtrack-sm bg-primary-dark px-4 py-2 text-sm font-semibold text-white">
                            Workspace
                        </Link>
                    </div>
                </div>
            </header>

            <main class="px-4 pb-24 pt-6 sm:px-6 lg:px-8 lg:pb-6">
                <div v-if="description" class="mb-5 max-w-3xl text-sm leading-6 text-muted">
                    {{ description }}
                </div>

                <slot />
            </main>
        </div>

        <MTrackBottomNav :items="mobileNavItems" />
    </div>
</template>
