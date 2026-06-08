<script setup lang="ts">
import type { Component } from 'vue';

export type MTrackBottomNavItem = {
    label: string;
    icon: Component;
    active?: boolean;
    disabled?: boolean;
};

defineProps<{
    items: MTrackBottomNavItem[];
}>();
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-surface px-2 py-2 shadow-overlay lg:hidden">
        <div class="grid grid-cols-5 gap-1">
            <button
                v-for="item in items"
                :key="item.label"
                type="button"
                :disabled="item.disabled"
                :class="[
                    'flex min-h-14 flex-col items-center justify-center gap-1 rounded-mtrack-sm text-xs font-semibold transition',
                    item.active ? 'bg-brand/12 text-brand-hover' : 'text-muted hover:bg-muted-surface hover:text-body',
                    item.disabled ? 'cursor-not-allowed opacity-50' : '',
                ]"
            >
                <component :is="item.icon" class="size-5" />
                <span class="max-w-full truncate">{{ item.label }}</span>
            </button>
        </div>
    </nav>
</template>
