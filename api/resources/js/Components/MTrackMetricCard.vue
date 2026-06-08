<script setup lang="ts">
import type { Component } from 'vue';

const toneClass = {
    brand: 'text-brand',
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
    info: 'text-info',
    neutral: 'text-muted',
} as const;

withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        helper?: string;
        icon?: Component;
        tone?: keyof typeof toneClass;
    }>(),
    {
        tone: 'brand',
    },
);
</script>

<template>
    <article class="mtrack-panel p-5">
        <component v-if="icon" :is="icon" :class="['size-5', toneClass[tone]]" />
        <div class="mt-5 text-sm font-semibold text-muted">{{ label }}</div>
        <div class="mt-1 text-metric tabular-nums text-body">{{ value }}</div>
        <div v-if="helper" class="mt-2 text-sm leading-6 text-muted">{{ helper }}</div>
    </article>
</template>
