<script setup lang="ts">
export type MTrackTab = {
    id: string;
    label: string;
    count?: number;
    disabled?: boolean;
};

defineProps<{
    tabs: MTrackTab[];
    activeId: string;
}>();

const emit = defineEmits<{
    select: [id: string];
}>();
</script>

<template>
    <div class="inline-flex rounded-mtrack-md border border-line bg-muted-surface p-1">
        <button
            v-for="tab in tabs"
            :key="tab.id"
            type="button"
            :disabled="tab.disabled"
            :class="[
                'flex h-8 items-center gap-2 rounded-mtrack-sm px-3 text-sm font-semibold transition',
                activeId === tab.id ? 'bg-surface text-brand shadow-card' : 'text-muted hover:text-body',
                tab.disabled ? 'cursor-not-allowed opacity-50' : '',
            ]"
            @click="emit('select', tab.id)"
        >
            <span>{{ tab.label }}</span>
            <span v-if="tab.count !== undefined" class="rounded-full bg-muted-surface px-2 text-xs text-muted">{{ tab.count }}</span>
        </button>
    </div>
</template>
