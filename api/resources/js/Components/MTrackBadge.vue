<script setup lang="ts">
const toneClass = {
    success: 'bg-success/12 text-success',
    warning: 'bg-warning/12 text-warning',
    danger: 'bg-danger/12 text-danger',
    info: 'bg-info/20 text-primary-dark',
    neutral: 'bg-muted-surface text-muted',
    brand: 'bg-brand/12 text-brand-hover',
} as const;

withDefaults(
    defineProps<{
        label: string;
        tone?: keyof typeof toneClass;
    }>(),
    {
        tone: 'neutral',
    },
);
</script>

<template>
    <span :class="['mtrack-badge', toneClass[tone]]">{{ label }}</span>
</template>
