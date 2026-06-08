<script setup lang="ts">
export type MTrackColumn = {
    key: string;
    label: string;
    align?: 'left' | 'right';
};

defineProps<{
    columns: MTrackColumn[];
    rows: Record<string, unknown>[];
    emptyLabel?: string;
}>();
</script>

<template>
    <div class="mtrack-panel overflow-hidden">
        <div class="overflow-x-auto">
            <table class="mtrack-table min-w-[720px]">
                <thead>
                    <tr>
                        <th v-for="column in columns" :key="column.key" :class="column.align === 'right' ? 'text-right' : 'text-left'">
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody v-if="rows.length">
                    <tr v-for="(row, index) in rows" :key="index" class="hover:bg-muted-surface/60">
                        <td v-for="column in columns" :key="column.key" :class="column.align === 'right' ? 'text-right' : 'text-left'">
                            <slot :name="column.key" :row="row">
                                {{ row[column.key] }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="!rows.length" class="p-6">
            <slot name="empty">
                <div class="mtrack-state min-h-32">{{ emptyLabel ?? 'No records found.' }}</div>
            </slot>
        </div>
    </div>
</template>
