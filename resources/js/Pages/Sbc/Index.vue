<script setup>
import { ref, watch } from 'vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowPathIcon, MagnifyingGlassIcon, TableCellsIcon, DocumentArrowUpIcon } from '@heroicons/vue/24/outline';
import { debounce } from 'lodash-es';

const props = defineProps({
    records:     Object,
    counts:      Object,
    sheet:       String,
    filters:     Object,
    lastSync:    String,
    hasTemplate: Boolean,
});

const templateFile  = ref(null);
const uploadingTpl  = ref(false);

function uploadTemplate() {
    if (!templateFile.value) return;
    const form = new FormData();
    form.append('template', templateFile.value);
    uploadingTpl.value = true;
    router.post('/sbc/upload-template', form, {
        forceFormData: true,
        onFinish: () => { uploadingTpl.value = false; templateFile.value = null; },
    });
}

const search   = ref(props.filters.search ?? '');
const syncing  = ref(false);

const SHEETS = ['SBC Signups', 'Certificates To Process'];

const debouncedSearch = debounce(() => {
    router.get('/sbc', { sheet: props.sheet, search: search.value || undefined }, {
        preserveState: true, replace: true,
    });
}, 350);

watch(search, debouncedSearch);

function switchTab(s) {
    search.value = '';
    router.get('/sbc', { sheet: s }, { preserveState: true, replace: true });
}

function sync() {
    syncing.value = true;
    router.post('/sbc/sync', {}, {
        onFinish: () => { syncing.value = false; },
    });
}

function fmt(dt) {
    if (!dt) return 'Never';
    return new Date(dt).toLocaleString([], { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const genderColor = {
    Male:   'bg-blue-100 text-blue-700',
    Female: 'bg-pink-100 text-pink-700',
};
</script>

<template>
    <AppLayout>
        <template #title>
            <span class="flex items-center gap-2">
                <TableCellsIcon class="h-5 w-5 text-brand-400" />
                SBC Signups
            </span>
        </template>
        <template #subtitle>Data stored in database · synced from Google Sheets</template>
        <template #header-actions>
            <!-- Template upload -->
            <label class="btn-secondary btn-sm cursor-pointer inline-flex items-center gap-1.5" title="Upload certificate PDF template">
                <DocumentArrowUpIcon class="h-4 w-4" />
                <span v-if="hasTemplate" class="text-green-700">Template ✓</span>
                <span v-else class="text-amber-600">Upload Template</span>
                <input type="file" accept="application/pdf" class="hidden"
                    @change="e => { templateFile = e.target.files[0]; uploadTemplate(); }" />
            </label>
            <button @click="sync" :disabled="syncing" class="btn-primary btn-sm">
                <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': syncing }" />
                {{ syncing ? 'Syncing…' : 'Sync from Sheets' }}
            </button>
        </template>

        <!-- Tabs -->
        <div class="flex gap-1 mb-4 bg-gray-100 rounded-xl p-1 w-fit border border-gray-200">
            <button
                v-for="s in SHEETS" :key="s"
                @click="switchTab(s)"
                :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors flex items-center gap-2',
                    sheet === s ? 'bg-white shadow text-gray-900 border border-gray-200' : 'text-gray-500 hover:text-gray-700']"
            >
                {{ s }}
                <span :class="['text-xs px-1.5 py-0.5 rounded-full font-bold',
                    sheet === s ? 'bg-brand-100 text-brand-700' : 'bg-gray-200 text-gray-500']">
                    {{ counts[s] ?? 0 }}
                </span>
            </button>
        </div>

        <!-- Last sync info -->
        <div class="mb-4 flex items-center gap-2 text-xs text-gray-400">
            <ArrowPathIcon class="h-3.5 w-3.5" />
            Last synced: <span class="text-gray-600 font-medium">{{ fmt(lastSync) }}</span>
            <span v-if="!lastSync" class="text-amber-500 font-medium">
                — click "Sync from Sheets" to import data
            </span>
        </div>

        <!-- Search -->
        <div class="card mb-4 py-3">
            <div class="relative max-w-sm">
                <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                <input v-model="search" class="input pl-9" placeholder="Search name, phone, location…" />
            </div>
        </div>

        <!-- Template missing warning (Certificates tab) -->
        <div v-if="sheet === 'Certificates To Process' && !hasTemplate"
            class="mb-4 flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800">
            <span class="text-lg leading-none">⚠️</span>
            <div>
                <strong>Certificate template not uploaded.</strong>
                Click <em>Upload Template</em> in the top-right and select the <strong>SAMPLE.pdf</strong> file.
                Once uploaded, the 🎓 Generate buttons will produce real PDFs with each person's name.
            </div>
        </div>

        <!-- Empty state (no sync yet) -->
        <div v-if="!records.total && !filters.search" class="card py-16 text-center">
            <TableCellsIcon class="h-12 w-12 text-gray-300 mx-auto mb-3" />
            <p class="text-gray-500 font-medium mb-1">No data in database yet</p>
            <p class="text-sm text-gray-400 mb-4">Click "Sync from Sheets" to import all records from Google Sheets.</p>
            <button @click="sync" :disabled="syncing" class="btn-primary mx-auto">
                <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': syncing }" />
                {{ syncing ? 'Syncing…' : 'Sync Now' }}
            </button>
        </div>

        <!-- Table -->
        <div v-else class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Date</th>
                        <th class="table-th">Phone Number</th>
                        <th class="table-th">First Name</th>
                        <th class="table-th">Surname</th>
                        <th class="table-th">Age</th>
                        <th class="table-th">Sex</th>
                        <th class="table-th">Location</th>
                        <th v-if="sheet === 'Certificates To Process'" class="table-th text-center">Certificate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!records.data.length">
                        <td :colspan="sheet === 'Certificates To Process' ? 8 : 7"
                            class="py-12 text-center text-sm text-gray-400">No records found.</td>
                    </tr>
                    <tr v-for="row in records.data" :key="row.id" class="hover:bg-gray-50">
                        <td class="table-td text-xs text-gray-500 whitespace-nowrap">
                            {{ row.date ? new Date(row.date).toLocaleDateString([], { day:'2-digit', month:'short', year:'numeric' }) : '—' }}
                        </td>
                        <td class="table-td font-mono text-xs text-gray-600">{{ row.phone_number || '—' }}</td>
                        <td class="table-td font-medium">{{ row.first_name || '—' }}</td>
                        <td class="table-td">{{ row.surname || '—' }}</td>
                        <td class="table-td text-sm">{{ row.age || '—' }}</td>
                        <td class="table-td">
                            <span v-if="row.sex" :class="['badge', genderColor[row.sex] ?? 'bg-gray-100 text-gray-600']">
                                {{ row.sex }}
                            </span>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                        <td class="table-td text-sm">{{ row.location || '—' }}</td>
                        <!-- Generate Certificate button — Certificates tab only -->
                        <td v-if="sheet === 'Certificates To Process'" class="table-td text-center">
                            <a :href="`/sbc/${row.id}/certificate`" target="_blank"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold
                                       bg-amber-500 hover:bg-amber-600 text-white transition-colors shadow-sm">
                                🎓 Generate
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="records.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">
                    Showing {{ records.from }}–{{ records.to }} of {{ records.total }} records
                </p>
                <div class="flex gap-1">
                    <Link v-for="link in records.links" :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-xs',
                            link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100',
                            !link.url && 'opacity-40 pointer-events-none']"
                        v-html="link.label" />
                </div>
            </div>

            <!-- Footer -->
            <div class="px-4 py-2 border-t border-gray-100 text-xs text-gray-400">
                {{ records.total }} total records in database
            </div>
        </div>
    </AppLayout>
</template>
