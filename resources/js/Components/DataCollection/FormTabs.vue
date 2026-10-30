<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { filterQuery } from './answers';

// Tabs across a form's pages. Filters carry over between the data, summary,
// map and dashboard tabs so they always describe the same submissions.
const props = defineProps({
    form: Object,
    can: Object,
    active: String,
    filters: { type: Object, default: () => ({}) },
});

const query = computed(() => {
    const q = new URLSearchParams(filterQuery(props.filters)).toString();
    return q ? `?${q}` : '';
});

const tabs = computed(() => {
    const p = props.can?.permissions ?? [];
    const base = `/data-collection/forms/${props.form.id}`;
    return [
        { key: 'overview', label: 'Form', href: base, show: true },
        { key: 'data', label: 'Data', href: `${base}/data${query.value}`, show: p.includes('view') },
        { key: 'summary', label: 'Summary', href: `${base}/summary${query.value}`, show: p.includes('view') },
        { key: 'map', label: 'Map', href: `${base}/map${query.value}`, show: p.includes('view') },
        { key: 'dashboard', label: 'Dashboard', href: `${base}/dashboard${query.value}`, show: p.includes('view') },
        { key: 'mapping', label: 'CRM mapping', href: `${base}/mapping`, show: p.includes('map') },
    ].filter(t => t.show);
});
</script>

<template>
    <div class="mb-4">
        <p class="text-xs text-gray-500 mb-1">
            <Link href="/data-collection" class="hover:underline">Data Collection</Link>
            <template v-if="form.project">
                / <Link :href="`/data-collection/projects/${form.project.id}`" class="hover:underline">{{ form.project.name }}</Link>
            </template>
        </p>
        <nav class="flex gap-1 border-b border-gray-200 overflow-x-auto">
            <Link
                v-for="tab in tabs" :key="tab.key" :href="tab.href"
                :class="['px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2 -mb-px',
                    tab.key === active ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-800']"
            >{{ tab.label }}</Link>
        </nav>
    </div>
</template>
