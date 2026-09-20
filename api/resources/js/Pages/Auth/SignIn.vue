<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import type { PageProps } from '../../types';
import '../../../css/customer.css';
const page = usePage<PageProps>();
const form = useForm({ email: '' });
const theme = ref('system');
let media: MediaQueryList;
function applyTheme() { document.documentElement.dataset.theme = theme.value === 'dark' || (theme.value === 'system' && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light'; try { localStorage.setItem('mtrack-theme', theme.value); } catch {} }
onMounted(() => { try { theme.value = localStorage.getItem('mtrack-theme') ?? 'system'; } catch {} applyTheme(); media = matchMedia('(prefers-color-scheme: dark)'); media.addEventListener('change', applyTheme); });
onUnmounted(() => media?.removeEventListener('change', applyTheme));
</script>
<template>
    <Head title="Sign in" />
    <main class="customer-app login-page"><section class="login-card">
        <div class="login-theme"><label class="theme-control">Theme<select v-model="theme" @change="applyTheme"><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select></label></div>
        <div class="panel"><div class="panel-body"><div class="actions"><span class="brand-mark">m</span><strong>mTrack</strong></div><div class="eyebrow space-top">YOUR TRACKING WORKSPACE</div><h1>Sign in to mTrack</h1><p class="muted">Manage your devices, define geofences, and review the events that matter.</p><div v-if="page.props.flash.error" class="notice error space-top" role="alert">{{ page.props.flash.error }}</div><div v-if="page.props.flash.status" class="notice space-top" role="status">{{ page.props.flash.status }}</div><a href="/auth/google/redirect" class="btn-primary space-top">Continue with Google</a><p class="field-hint">New here? Your first Google sign-in creates a private customer workspace.</p><details><summary class="muted">Use an email sign-in link</summary><form @submit.prevent="form.post('/auth/magic-link', { onSuccess: () => form.reset() })"><label class="field">Email address<input v-model="form.email" type="email" autocomplete="email" required aria-describedby="email-error" /><small>For existing accounts.</small><span id="email-error" class="field-error">{{ form.errors.email }}</span></label><button class="btn" :disabled="form.processing">{{ form.processing ? 'Sending…' : 'Send sign-in link' }}</button></form></details></div></div>
    </section></main>
</template>
