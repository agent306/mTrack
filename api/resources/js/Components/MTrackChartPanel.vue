<script setup lang="ts">
defineProps<{
    title: string;
    summary: string;
}>();
</script>

<template>
    <section class="mtrack-panel p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-section-title text-body">{{ title }}</h3>
                <p class="mt-1 text-sm leading-6 text-muted">{{ summary }}</p>
            </div>
            <slot name="actions" />
        </div>
        <div class="mt-5 min-h-48">
            <slot />
        </div>
    </section>
</template>
