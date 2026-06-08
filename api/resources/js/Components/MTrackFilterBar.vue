<script setup lang="ts">
import { Search, SlidersHorizontal } from '@lucide/vue';
</script>

<template>
    <section class="mtrack-panel flex flex-col gap-3 p-3 md:flex-row md:items-center">
        <label class="flex min-w-0 flex-1 items-center gap-2 rounded-mtrack-sm border border-line bg-surface px-3">
            <Search class="size-4 shrink-0 text-muted" />
            <input class="h-10 min-w-0 flex-1 text-sm outline-none" type="search" placeholder="Search" />
        </label>
        <div class="flex flex-wrap items-center gap-2">
            <slot />
            <button type="button" class="mtrack-button-secondary">
                <SlidersHorizontal class="size-4" />
                <span>Filter</span>
            </button>
        </div>
    </section>
</template>
