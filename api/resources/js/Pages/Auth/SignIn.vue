<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Mail, MapPinned, RadioTower, ShieldCheck } from '@lucide/vue';
import type { PageProps } from '../../types';
import { route } from 'ziggy-js';

const page = usePage<PageProps>();
const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('auth.magic-link.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Sign in" />

    <main class="grid min-h-screen bg-page text-body lg:grid-cols-[1.05fr_0.95fr]">
        <section class="flex min-h-screen flex-col justify-between bg-primary-dark p-6 text-white sm:p-8 lg:p-10">
            <div class="flex items-center gap-3">
                <div class="grid size-10 place-items-center rounded-mtrack-md bg-brand font-bold text-primary-dark">m</div>
                <div>
                    <div class="text-sm font-bold">mTrack</div>
                    <div class="text-xs text-white/55">Fleet operations</div>
                </div>
            </div>

            <div class="max-w-xl py-12">
                <div class="mb-8 inline-flex items-center gap-2 rounded-full bg-white/8 px-3 py-1 text-xs font-semibold text-brand">
                    <ShieldCheck class="size-4" />
                    Passwordless access
                </div>
                <h1 class="text-[32px] font-bold leading-10">Monitor fleets, devices, and operational events from one tenant-aware workspace.</h1>
                <p class="mt-4 max-w-lg text-sm leading-6 text-white/68">
                    Foundation access is limited to provisioned mTrack users. Request a secure email link or continue with Google.
                </p>

                <div class="mt-10 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-mtrack-md border border-white/10 bg-white/6 p-4">
                        <MapPinned class="size-5 text-brand" />
                        <div class="mt-4 text-sm font-semibold">Map-first shell</div>
                        <div class="mt-1 text-xs leading-5 text-white/55">Ready for live tracking phases.</div>
                    </div>
                    <div class="rounded-mtrack-md border border-white/10 bg-white/6 p-4">
                        <RadioTower class="size-5 text-info" />
                        <div class="mt-4 text-sm font-semibold">Realtime-ready</div>
                        <div class="mt-1 text-xs leading-5 text-white/55">Redis, Horizon, and Reverb prepared.</div>
                    </div>
                    <div class="rounded-mtrack-md border border-white/10 bg-white/6 p-4">
                        <ShieldCheck class="size-5 text-success" />
                        <div class="mt-4 text-sm font-semibold">Tenant-aware</div>
                        <div class="mt-1 text-xs leading-5 text-white/55">Roles and isolation from day one.</div>
                    </div>
                </div>
            </div>

            <div class="text-xs text-white/45">mTrack v1 foundation</div>
        </section>

        <section class="flex min-h-screen items-center justify-center p-6 sm:p-8">
            <div class="w-full max-w-md rounded-mtrack-lg border border-line bg-surface p-6 shadow-card sm:p-8">
                <div>
                    <h2 class="text-2xl font-bold leading-8">Sign in</h2>
                    <p class="mt-2 text-sm leading-6 text-muted">Use your work email to receive a magic link.</p>
                </div>

                <div v-if="page.props.flash.status" class="mt-5 rounded-mtrack-md border border-brand/30 bg-brand/10 px-4 py-3 text-sm text-primary-dark">
                    {{ page.props.flash.status }}
                </div>

                <div v-if="page.props.flash.error" class="mt-5 rounded-mtrack-md border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                    {{ page.props.flash.error }}
                </div>

                <form class="mt-6 space-y-5" @submit.prevent="submit">
                    <label class="block">
                        <span class="text-sm font-semibold">Email</span>
                        <span class="mt-2 flex h-11 items-center gap-3 rounded-mtrack-md border border-line bg-white px-3 focus-within:border-brand">
                            <Mail class="size-4 text-muted" />
                            <input
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                class="h-full min-w-0 flex-1 text-sm outline-none"
                                placeholder="name@company.com"
                            />
                        </span>
                        <span v-if="form.errors.email" class="mt-2 block text-sm text-danger">{{ form.errors.email }}</span>
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="h-11 w-full rounded-mtrack-md bg-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-hover disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Send magic link
                    </button>
                </form>

                <div class="mt-5">
                    <Link
                        :href="route('auth.google.redirect')"
                        class="flex h-11 items-center justify-center rounded-mtrack-md border border-line bg-muted-surface px-4 text-sm font-semibold text-body transition hover:bg-white"
                    >
                        Continue with Google
                    </Link>
                </div>
            </div>
        </section>
    </main>
</template>
