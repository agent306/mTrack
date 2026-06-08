<script setup lang="ts">
import type { Component } from 'vue';

export type MTrackSideNavItem = {
    label: string;
    icon: Component;
    active?: boolean;
    disabled?: boolean;
};

defineProps<{
    items: MTrackSideNavItem[];
}>();
</script>

<template>
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <button
            v-for="item in items"
            :key="item.label"
            type="button"
            :disabled="item.disabled"
            :class="[
                'flex h-10 w-full items-center gap-3 rounded-mtrack-sm px-3 text-left text-sm font-medium transition',
                item.active ? 'bg-brand text-primary-dark' : 'text-white/72 hover:bg-white/8 hover:text-white',
                item.disabled ? 'cursor-not-allowed opacity-45' : '',
            ]"
        >
            <component :is="item.icon" class="size-4 shrink-0" :stroke-width="2" />
            <span class="truncate">{{ item.label }}</span>
        </button>
    </nav>
</template>
