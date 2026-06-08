<script setup lang="ts">
defineProps<{
    label: string;
    error?: string;
    helper?: string;
}>();
</script>

<template>
    <label class="block">
        <span class="mtrack-label">{{ label }}</span>
        <span class="mt-2 block">
            <slot />
        </span>
        <span v-if="error" class="mt-2 block text-sm text-danger">{{ error }}</span>
        <span v-else-if="helper" class="mt-2 block text-sm text-muted">{{ helper }}</span>
    </label>
</template>
