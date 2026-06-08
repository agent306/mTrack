<script setup lang="ts">
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';

withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        widthClass?: string;
    }>(),
    {
        widthClass: 'max-w-lg',
    },
);

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <TransitionRoot as="template" :show="open">
        <Dialog as="div" class="relative z-50" @close="emit('close')">
            <TransitionChild
                as="template"
                enter="ease-out duration-150"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in duration-100"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-primary-dark/35" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto p-4">
                <div class="flex min-h-full items-center justify-center">
                    <TransitionChild
                        as="template"
                        enter="ease-out duration-150"
                        enter-from="translate-y-2 opacity-0"
                        enter-to="translate-y-0 opacity-100"
                        leave="ease-in duration-100"
                        leave-from="translate-y-0 opacity-100"
                        leave-to="translate-y-2 opacity-0"
                    >
                        <DialogPanel :class="['w-full rounded-mtrack-sheet border border-line bg-surface p-6 shadow-overlay', widthClass]">
                            <DialogTitle class="text-section-title text-body">{{ title }}</DialogTitle>
                            <div class="mt-4">
                                <slot />
                            </div>
                            <div class="mt-6 flex justify-end gap-2">
                                <slot name="actions" />
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
