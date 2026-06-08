<script setup lang="ts">
import { AlertTriangle, Loader2, SearchX, ShieldAlert } from '@lucide/vue';

const iconByKind = {
    empty: SearchX,
    loading: Loader2,
    error: AlertTriangle,
    noPermission: ShieldAlert,
} as const;

const toneByKind = {
    empty: 'text-muted',
    loading: 'text-brand',
    error: 'text-danger',
    noPermission: 'text-warning',
} as const;

const props = withDefaults(
    defineProps<{
        kind?: keyof typeof iconByKind;
        title: string;
        message?: string;
    }>(),
    {
        kind: 'empty',
    },
);
</script>

<template>
    <div class="mtrack-state">
        <component :is="iconByKind[props.kind]" :class="['size-6', toneByKind[props.kind], props.kind === 'loading' ? 'animate-spin' : '']" />
        <div class="mt-3 text-sm font-bold text-body">{{ title }}</div>
        <p v-if="message" class="mt-1 max-w-md text-sm leading-6 text-muted">{{ message }}</p>
        <div class="mt-4">
            <slot />
        </div>
    </div>
</template>
