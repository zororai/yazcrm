<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormTabs from '@/Components/DataCollection/FormTabs.vue';
import FilterBar from '@/Components/DataCollection/FilterBar.vue';
import {
    STATUS_COLORS, STATUS_LABELS, currentQuery, filterQuery, formatAnswer, formatDateTime,
} from '@/Components/DataCollection/answers';
import { ArrowDownTrayIcon, ChevronUpDownIcon, ViewColumnsIcon, PhotoIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    form: Object, can: Object, fields: Array, repeats: Array, enumerators: Array, filters: Object, statuses: Array,
    rows: Object, sort: Object, exports: Array,
});

const canEdit = computed(() => props.can.permissions.includes('edit'));
const canExport = computed(() => props.can.permissions.includes('export'));
const topFields = computed(() => props.fields.filter(f => !f.repeat));
const byPath = computed(() => Object.fromEntries(props.fields.map(f => [f.path, f])));

// ── Per-viewer preferences (labels toggle, hidden columns) ─────────────────
const storageKey = `dc-table-${props.form.id}`;
function loadPrefs() {
    try { return JSON.parse(localStorage.getItem(storageKey) ?? '{}'); } catch { return {}; }
}
const prefs = loadPrefs();
const useLabels = ref(prefs.useLabels ?? true);
const hiddenColumns = ref(new Set(prefs.hidden ?? []));
watch([useLabels, hiddenColumns], () => {
    try { localStorage.setItem(storageKey, JSON.stringify({ useLabels: useLabels.value, hidden: [...hiddenColumns.value] })); } catch { /* private mode */ }
}, { deep: true });

const showColumnPicker = ref(false);
function toggleColumn(path) {
    const next = new Set(hiddenColumns.value);
    next.has(path) ? next.delete(path) : next.add(path);
    hiddenColumns.value = next;
}
const visibleFields = computed(() => topFields.value.filter(f => !hiddenColumns.value.has(f.path)));
const topRepeats = computed(() => props.repeats.filter(r => !r.parent));

// ── Sorting and paging ──────────────────────────────────────────────────────
function sortBy(field) {
    const dir = props.sort.field === field && props.sort.dir === 'desc' ? 'asc' : 'desc';
    router.get(window.location.pathname, { ...currentQuery(), sort: field, dir, page: 1 }, { preserveScroll: true });
}
function perPage(n) {
    router.get(window.location.pathname, { ...currentQuery(), per_page: n, page: 1 }, { preserveScroll: true });
}

// ── Bulk validation status ──────────────────────────────────────────────────
const selected = ref(new Set());
const allOnPage = computed(() => props.rows.data.length > 0 && props.rows.data.every(r => selected.value.has(r.id)));
function toggleAll() {
    selected.value = allOnPage.value ? new Set() : new Set(props.rows.data.map(r => r.id));
}
function toggleRow(id) {
    const next = new Set(selected.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selected.value = next;
}
const bulk = useForm({ ids: [], status: 'approved', note: '' });
function applyBulk() {
    bulk.ids = [...selected.value];
    bulk.post(`/data-collection/forms/${props.form.id}/data/status`, {
        preserveScroll: true,
        onSuccess: () => { selected.value = new Set(); bulk.reset('note'); },
    });
}

// ── Exports ─────────────────────────────────────────────────────────────────
const exportFormat = ref('xlsx');
function requestExport() {
    router.post(`/data-collection/forms/${props.form.id}/exports`,
        { format: exportFormat.value, labels: useLabels.value, ...filterQuery(props.filters) },
        { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <template #title>{{ form.name }}: data</template>

        <FormTabs :form="form" :can="can" active="data" :filters="filters" />
        <FilterBar :filters="filters" :fields="fields" :enumerators="enumerators" :statuses="statuses" :show-deleted="can.design" />

        <div class="flex flex-wrap items-center gap-3 mb-3">
            <p class="text-sm text-gray-600">{{ rows.total.toLocaleString() }} submissions</p>

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input v-model="useLabels" type="checkbox" /> Show labels
            </label>

            <div class="relative">
                <button class="btn-secondary btn-sm" @click="showColumnPicker = !showColumnPicker"><ViewColumnsIcon class="h-4 w-4" /> Columns</button>
                <div v-if="showColumnPicker" class="absolute z-20 mt-1 w-72 max-h-80 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg p-3 space-y-1">
                    <label v-for="f in topFields" :key="f.path" class="flex items-center gap-2 text-sm">
                        <input type="checkbox" :checked="!hiddenColumns.has(f.path)" @change="toggleColumn(f.path)" />
                        <span class="truncate">{{ f.label }}</span>
                    </label>
                </div>
            </div>

            <div v-if="canExport" class="ml-auto flex items-center gap-2">
                <select v-model="exportFormat" class="input py-1.5 w-32">
                    <option value="xlsx">Excel (XLSX)</option>
                    <option value="csv">CSV</option>
                    <option value="geojson">GeoJSON</option>
                    <option value="pdf">PDF summary</option>
                </select>
                <button class="btn-secondary btn-sm" @click="requestExport"><ArrowDownTrayIcon class="h-4 w-4" /> Export</button>
            </div>
        </div>

        <div v-if="exports.length" class="card mb-3 py-3 text-sm">
            <span class="text-gray-500 mr-2">Your recent exports:</span>
            <span v-for="e in exports" :key="e.id" class="mr-4">
                <a v-if="e.status === 'ready'" :href="`/data-collection/exports/${e.id}/download`" class="text-brand-600 hover:underline">{{ e.format.toUpperCase() }} ({{ e.row_count }})</a>
                <span v-else-if="e.status === 'failed'" class="text-red-600" :title="e.error">{{ e.format.toUpperCase() }} failed</span>
                <span v-else class="text-gray-400">{{ e.format.toUpperCase() }} {{ e.status }}…</span>
            </span>
        </div>

        <div v-if="canEdit && selected.size" class="card mb-3 py-3 flex flex-wrap items-center gap-3 bg-brand-50">
            <span class="text-sm font-medium">{{ selected.size }} selected</span>
            <select v-model="bulk.status" class="input py-1.5 w-40">
                <option v-for="s in statuses" :key="s" :value="s">{{ STATUS_LABELS[s] }}</option>
            </select>
            <input v-model="bulk.note" class="input py-1.5 flex-1 min-w-[12rem]" placeholder="Note (optional)" />
            <button class="btn-primary btn-sm" :disabled="bulk.processing" @click="applyBulk">Set status</button>
        </div>

        <div class="card p-0 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th v-if="canEdit" class="table-th w-8"><input type="checkbox" :checked="allOnPage" @change="toggleAll" /></th>
                        <th class="table-th cursor-pointer whitespace-nowrap" @click="sortBy('id')">#<ChevronUpDownIcon class="h-3 w-3 inline" /></th>
                        <th class="table-th cursor-pointer whitespace-nowrap" @click="sortBy('collected_at')">Collected<ChevronUpDownIcon class="h-3 w-3 inline" /></th>
                        <th class="table-th cursor-pointer whitespace-nowrap" @click="sortBy('submitted_by')">Enumerator<ChevronUpDownIcon class="h-3 w-3 inline" /></th>
                        <th class="table-th cursor-pointer whitespace-nowrap" @click="sortBy('status')">Status<ChevronUpDownIcon class="h-3 w-3 inline" /></th>
                        <th v-for="f in visibleFields" :key="f.path" class="table-th whitespace-nowrap cursor-pointer max-w-[14rem]" :title="f.label" @click="!f.hidden && sortBy(f.path)">
                            <span class="truncate inline-block max-w-[12rem] align-bottom">{{ useLabels ? f.label : f.name }}</span>
                            <span v-if="f.pii" class="text-purple-500 ml-0.5">•</span>
                        </th>
                        <th v-for="r in topRepeats" :key="r.name" class="table-th whitespace-nowrap">{{ r.label }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="row in rows.data" :key="row.id" :class="['hover:bg-gray-50', row.deleted ? 'opacity-50' : '']">
                        <td v-if="canEdit" class="table-td"><input type="checkbox" :checked="selected.has(row.id)" @change="toggleRow(row.id)" /></td>
                        <td class="table-td whitespace-nowrap">
                            <Link :href="`/data-collection/submissions/${row.id}`" class="text-brand-600 hover:underline">{{ row.id }}</Link>
                            <PhotoIcon v-if="row.media_count" class="h-3.5 w-3.5 inline text-gray-400 ml-1" />
                        </td>
                        <td class="table-td whitespace-nowrap text-xs">{{ formatDateTime(row.collected_at) }}</td>
                        <td class="table-td whitespace-nowrap">{{ row.submitted_by }}</td>
                        <td class="table-td whitespace-nowrap">
                            <span :class="['badge', STATUS_COLORS[row.status]]" :title="row.status_note">{{ STATUS_LABELS[row.status] }}</span>
                        </td>
                        <td v-for="f in visibleFields" :key="f.path" class="table-td max-w-[14rem] truncate" :title="formatAnswer(f, row.data[f.name], useLabels)">
                            {{ formatAnswer(f, row.data[f.name], useLabels) }}
                        </td>
                        <td v-for="r in topRepeats" :key="r.name" class="table-td text-gray-500">{{ formatAnswer(null, row.data[r.name]) || '—' }}</td>
                    </tr>
                    <tr v-if="!rows.data.length">
                        <td :colspan="5 + visibleFields.length + topRepeats.length" class="table-td text-center text-gray-400 py-10">No submissions match.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 mt-3 text-sm text-gray-600">
            <div class="flex items-center gap-2">
                Rows per page
                <select :value="rows.per_page" class="input py-1 w-20" @change="perPage($event.target.value)">
                    <option v-for="n in [25, 50, 100]" :key="n" :value="n">{{ n }}</option>
                </select>
            </div>
            <div class="flex flex-wrap gap-1">
                <template v-for="link in rows.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" preserve-scroll
                        :class="['px-3 py-1 rounded border text-sm', link.active ? 'bg-brand-600 text-white border-brand-600' : 'border-gray-200 hover:bg-gray-50']"
                        v-html="link.label" />
                    <span v-else class="px-3 py-1 text-gray-300 text-sm" v-html="link.label" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
