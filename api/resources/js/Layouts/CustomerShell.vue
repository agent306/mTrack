<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { LayoutDashboard, MapPinned, RadioTower, MapPin, ArrowRightLeft, FileText, Route, Settings, SunMoon, LogOut, Menu, CreditCard, ScrollText } from '@lucide/vue';
import type { PageProps } from '../types';
import '../../css/customer.css';
const props = defineProps<{ active: string; allowed: string[] }>();
const page = usePage<PageProps>();
const open = ref(false);
const theme = ref('system');
const items = computed(() => [
    { key: 'dashboard', label: 'Overview', icon: LayoutDashboard, path: 'dashboard' },
    { key: 'live', label: 'Live map', icon: MapPinned, path: 'live' },
    { key: 'my_fleets', label: 'My devices', icon: RadioTower, path: 'my-fleets' },
    { key: 'geofence', label: 'Places', icon: MapPin, path: 'geofence' },
    { key: 'events', label: 'Arrivals & departures', icon: ArrowRightLeft, path: 'events' },
    { key: 'analysis', label: 'Reports', icon: FileText, path: 'analysis' },
    { key: 'playback', label: 'Journey history', icon: Route, path: 'playback' },
    { key: 'settings', label: 'Settings', icon: Settings, path: 'setting' },
    { key: 'billing', label: 'Billing', icon: CreditCard, path: 'billing' },
    { key: 'audit_log', label: 'Audit history', icon: ScrollText, path: 'audit-log' },
].filter(i => props.allowed.includes(i.key)));
function applyTheme() {
    const dark = theme.value === 'dark' || (theme.value === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    try { localStorage.setItem('mtrack-theme', theme.value); } catch {}
}
onMounted(() => { try { theme.value = localStorage.getItem('mtrack-theme') ?? 'system'; } catch {} applyTheme(); });
</script>
<template>
    <div class="customer-app">
        <aside class="customer-sidebar" :class="{ 'is-open': open }">
            <Link href="/dashboard" class="customer-brand"><span class="brand-mark">m</span>mTrack <span class="brand-caption">Personal tracking</span></Link>
            <div class="workspace-label">YOUR WORKSPACE</div>
            <nav aria-label="Main navigation"><Link v-for="item in items" :key="item.key" :href="`/customer/${item.path}`" :class="{ selected: active === item.key }" :aria-current="active === item.key ? 'page' : undefined" @click="open = false"><component :is="item.icon" :size="17" />{{ item.label }}</Link></nav>
            <div class="sidebar-account"><span class="avatar">{{ page.props.auth.user?.name?.slice(0, 1) }}</span><div><strong>{{ page.props.auth.user?.name }}</strong><small>{{ page.props.auth.user?.email }}</small></div><button aria-label="Sign out" class="icon-button" @click="router.post('/logout')"><LogOut :size="16" /></button></div>
        </aside>
        <div class="customer-main">
            <header class="customer-topbar"><button class="icon-button mobile-menu" aria-label="Toggle navigation" :aria-expanded="open" @click="open = !open"><Menu :size="19" /></button><span>{{ page.props.auth.tenant?.name }}</span><label class="theme-control"><SunMoon :size="15" /><select v-model="theme" aria-label="Color theme" @change="applyTheme"><option value="system">System theme</option><option value="light">Light</option><option value="dark">Dark</option></select></label></header>
            <main class="customer-content"><div v-if="page.props.flash.status" role="status" class="notice">{{ page.props.flash.status }}</div><div v-if="page.props.flash.error" role="alert" class="notice error">{{ page.props.flash.error }}</div><slot /></main>
        </div>
    </div>
</template>
