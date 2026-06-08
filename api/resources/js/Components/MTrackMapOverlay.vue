<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    subtitle?: string;
    icon?: Component;
}>();
</script>

<template>
    <aside class="mtrack-map-panel w-full max-w-sm p-4">
        <div class="flex items-start gap-3">
            <div v-if="icon" class="grid size-10 shrink-0 place-items-center rounded-mtrack-sm bg-brand/12 text-brand">
                <component :is="icon" class="size-5" />
            </div>
            <div class="min-w-0">
                <h3 class="truncate text-sm font-bold text-body">{{ title }}</h3>
                <p v-if="subtitle" class="mt-1 text-sm leading-5 text-muted">{{ subtitle }}</p>
            </div>
        </div>
        <div class="mt-4">
            <slot />
        </div>
    </aside>
</template>
