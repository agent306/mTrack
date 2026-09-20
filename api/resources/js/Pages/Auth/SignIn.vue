<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import type { PageProps } from '../../types';
import '../../../css/customer.css';
const page = usePage<PageProps>();
const form = useForm({ email: '' });
</script>
<template>
    <Head title="Welcome to mTrack" />
    <main class="customer-app" style="display:grid;place-items:center;padding:28px">
        <section style="width:100%;max-width:420px">
            <div class="customer-brand" style="padding-left:0"><span class="brand-mark">m</span>mTrack</div>
            <div class="panel"><div class="panel-body" style="padding:30px"><MapPin :size="25" style="color:var(--c-accent);margin-bottom:18px" /><div class="eyebrow">YOUR DEVICES. YOUR PLACES.</div><h1 style="font-size:25px;font-weight:600;letter-spacing:-.7px;margin:8px 0">A little closer to what matters.</h1><p class="muted" style="margin-bottom:24px">Connect your tracker, follow its journeys, and know when it arrives. Sign in or create your workspace with Google.</p><div v-if="page.props.flash.error" class="notice error" role="alert">{{ page.props.flash.error }}</div><div v-if="page.props.flash.status" class="notice" role="status">{{ page.props.flash.status }}</div><a href="/auth/google/redirect" class="btn-primary" style="width:100%;min-height:40px">Continue with Google</a><details style="margin-top:22px"><summary class="muted" style="cursor:pointer;font-size:12px">Already have an account? Use an email link</summary><form style="margin-top:16px" @submit.prevent="form.post('/auth/magic-link', { onSuccess: () => form.reset() })"><label class="field">Email address<input v-model="form.email" type="email" autocomplete="email" required /></label><p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p><button class="btn" :disabled="form.processing" style="width:100%">{{ form.processing ? 'Sending…' : 'Send sign-in link' }}</button></form></details></div></div><p class="muted" style="font-size:12px;text-align:center">One workspace for your devices, places, and daily journeys.</p>
        </section>
    </main>
</template>
