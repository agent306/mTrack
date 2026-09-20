<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Pagination } from './TrackingTypes';
defineProps<{ records: Pagination<unknown> }>();
</script>
<template><footer class="pagination"><span>{{ records.from ?? 0 }}–{{ records.to ?? 0 }} of {{ records.total }} records</span><div class="actions"><Link v-if="records.prev_page_url" :href="records.prev_page_url" class="btn" preserve-scroll>Previous</Link><Link v-if="records.next_page_url" :href="records.next_page_url" class="btn" preserve-scroll>Next</Link></div></footer></template>
