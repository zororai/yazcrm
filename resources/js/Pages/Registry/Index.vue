<script setup>
import { ref, watch, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    PlusIcon, ArrowDownTrayIcon, ArrowUpTrayIcon, MagnifyingGlassIcon,
    PencilIcon, TrashIcon, EyeIcon, ArrowUturnLeftIcon, FolderOpenIcon,
} from '@heroicons/vue/24/outline';
import { debounce } from 'lodash-es';

const props = defineProps({
    assets:     Object,
    summary:    Object,
    categories: { type: Array, default: () => [] },
    filters:    Object,
});

const search          = ref(props.filters.search           ?? '');
const type            = ref(props.filters.type             ?? '');
const categoryId      = ref(props.filters.category_id      ?? '');
const lifecycleStatus = ref(props.filters.lifecycle_status ?? '');
const dataSensitivity = ref(props.filters.data_sensitivity ?? '');
const criticality     = ref(props.filters.criticality_393  ?? '');
const trashed         = ref(!!props.filters.trashed);

function apply() {
    router.get('/registry', {
        search:           search.value           || undefined,
        type:             type.value             || undefined,
        category_id:      categoryId.value        || undefined,
        lifecycle_status: lifecycleStatus.value  || undefined,
        data_sensitivity: dataSensitivity.value  || undefined,
        criticality_393:  criticality.value       || undefined,
        trashed:          trashed.value          || undefined,
    }, { preserveState: true, replace: true });
}

const debouncedApply = debounce(apply, 350);
watch(search, debouncedApply);
watch([type, categoryId, lifecycleStatus, dataSensitivity, criticality, trashed], apply);

function deleteAsset(asset) {
    if (!confirm(`Delete asset "${asset.name}" (${asset.asset_tag})? It will be moved to Trash and can be restored.`)) return;
    router.delete(`/registry/assets/${asset.id}`, { preserveScroll: true });
}

function restoreAsset(asset) {
    router.post(`/registry/assets/${asset.id}/restore`, {}, { preserveScroll: true });
}

function forceDeleteAsset(asset) {
    if (!confirm(`Permanently delete "${asset.name}" (${asset.asset_tag})? This cannot be undone.`)) return;
    router.delete(`/registry/assets/${asset.id}/force`, { preserveScroll: true });
}

// ── Bulk selection ───────────────────────────────────────────────────────────
const selected = ref(new Set());
const allSelected = computed(() => props.assets.data.length > 0 && selected.value.size === props.assets.data.length);

function toggleAll() {
    selected.value = allSelected.value ? new Set() : new Set(props.assets.data.map(a => a.id));
}
function toggleOne(id) {
    const next = new Set(selected.value);
    next.has(id) ? next.delete(id) : next.add(id);
    selected.value = next;
}

function runBulk(action, extra = {}) {
    if (!selected.value.size) return;
    const label = { delete: 'delete', restore: 'restore' }[action] ?? 'update';
    if (['delete'].includes(action) && !confirm(`${label} ${selected.value.size} selected asset(s)?`)) return;
    router.post('/registry/bulk', { ids: [...selected.value], action, ...extra }, {
        preserveScroll: true,
        onSuccess: () => { selected.value = new Set(); },
    });
}

const bulkStatus = ref('');
function applyBulkStatus() {
    if (!bulkStatus.value) return;
    runBulk('set_lifecycle_status', { lifecycle_status: bulkStatus.value });
    bulkStatus.value = '';
}

// ── CSV import ────────────────────────────────────────────────────────────────
const showImport = ref(false);
const importForm = useForm({ file: null });
function submitImport() {
    importForm.post('/registry/import', {
        onSuccess: () => { showImport.value = false; importForm.reset(); },
    });
}

function formatCurrency(val) {
    if (val == null) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 }).format(val);
}

function formatDate(val) {
    if (!val) return '—';
    return new Date(val).toLocaleDateString();
}

function warrantyClass(date) {
    if (!date) return '';
    const d = new Date(date);
    const now = new Date();
    if (d < now) return 'text-red-600 font-semibold';
    const soon = new Date(now);
    soon.setMonth(soon.getMonth() + 3);
    if (d <= soon) return 'text-orange-500 font-semibold';
    return '';
}

const typeColors = {
    server:      'bg-blue-100 text-blue-800',
    network:     'bg-purple-100 text-purple-800',
    endpoint:    'bg-cyan-100 text-cyan-800',
    telephony:   'bg-teal-100 text-teal-800',
    application: 'bg-indigo-100 text-indigo-800',
    power:       'bg-yellow-100 text-yellow-800',
    saas:        'bg-pink-100 text-pink-800',
};
const lifecycleColors = {
    active:    'bg-green-100 text-green-800',
    in_repair: 'bg-yellow-100 text-yellow-800',
    retired:   'bg-gray-100 text-gray-600',
    disposed:  'bg-red-100 text-red-800',
};
const patchColors = {
    current:  'bg-green-100 text-green-800',
    outdated: 'bg-orange-100 text-orange-800',
    eol:      'bg-red-100 text-red-800',
    unknown:  'bg-gray-100 text-gray-600',
};
const critColors = {
    low:    'bg-gray-100 text-gray-600',
    medium: 'bg-blue-100 text-blue-800',
    high:   'bg-red-100 text-red-800',
};
</script>

<template>
    <AppLayout>
        <template #title>Asset Register</template>
        <template #header-actions>
            <Link href="/registry/categories" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                <FolderOpenIcon class="h-4 w-4" /> Categories
            </Link>
            <button @click="showImport = true" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                <ArrowUpTrayIcon class="h-4 w-4" /> Import CSV
            </button>
            <a href="/registry/export" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                <ArrowDownTrayIcon class="h-4 w-4" /> Export CSV
            </a>
            <Link href="/registry/assets/create" class="btn-primary btn-sm inline-flex items-center gap-1.5">
                <PlusIcon class="h-4 w-4" /> Add Asset
            </Link>
        </template>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-4 mb-6">
            <div class="card text-center">
                <p class="text-2xl font-bold text-gray-900">{{ summary.total }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Assets</p>
            </div>
            <div class="card text-center">
                <p class="text-xl font-bold text-gray-900">{{ formatCurrency(summary.book_value) }}</p>
                <p class="text-xs text-gray-500 mt-1">Book Value</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-orange-600">{{ summary.warranty_soon }}</p>
                <p class="text-xs text-gray-500 mt-1">Warranty Expiring</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-yellow-600">{{ summary.refresh_soon }}</p>
                <p class="text-xs text-gray-500 mt-1">Refresh Due</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-blue-600">{{ summary.in_repair }}</p>
                <p class="text-xs text-gray-500 mt-1">In Repair</p>
            </div>
            <div class="card text-center cursor-pointer" @click="trashed = !trashed">
                <p class="text-2xl font-bold text-gray-500">{{ summary.trashed }}</p>
                <p class="text-xs text-gray-500 mt-1">Trashed</p>
            </div>
            <div class="card text-center">
                <p class="text-xs font-medium text-gray-600 mb-1">By Type</p>
                <div class="space-y-0.5 text-left">
                    <div v-for="(count, t) in summary.by_type" :key="t" class="flex justify-between text-xs">
                        <span class="text-gray-500 capitalize">{{ t }}</span>
                        <span class="font-semibold">{{ count }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-48">
                    <label class="label">Search</label>
                    <div class="relative">
                        <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                        <input v-model="search" class="input pl-9" placeholder="Tag, name, location, owner…" />
                    </div>
                </div>
                <div>
                    <label class="label">Type</label>
                    <select v-model="type" class="input w-36">
                        <option value="">All</option>
                        <option value="server">Server</option>
                        <option value="network">Network</option>
                        <option value="endpoint">Endpoint</option>
                        <option value="telephony">Telephony</option>
                        <option value="application">Application</option>
                        <option value="power">Power</option>
                        <option value="saas">SaaS</option>
                    </select>
                </div>
                <div>
                    <label class="label">Status</label>
                    <select v-model="lifecycleStatus" class="input w-36">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="in_repair">In Repair</option>
                        <option value="retired">Retired</option>
                        <option value="disposed">Disposed</option>
                    </select>
                </div>
                <div>
                    <label class="label">Sensitivity</label>
                    <select v-model="dataSensitivity" class="input w-32">
                        <option value="">All</option>
                        <option value="none">None</option>
                        <option value="internal">Internal</option>
                        <option value="sensitive">Sensitive</option>
                    </select>
                </div>
                <div>
                    <label class="label">Criticality</label>
                    <select v-model="criticality" class="input w-32">
                        <option value="">All</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div>
                    <label class="label">Category</label>
                    <select v-model="categoryId" class="input w-40">
                        <option value="">All</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-600 pb-2">
                    <input type="checkbox" v-model="trashed" /> Show trashed only
                </label>
            </div>
        </div>

        <!-- Bulk action bar -->
        <div v-if="selected.size" class="card mb-4 flex flex-wrap items-center gap-3 bg-brand-50 border-brand-200">
            <span class="text-sm font-medium text-brand-700">{{ selected.size }} selected</span>
            <template v-if="!trashed">
                <button @click="runBulk('delete')" class="btn-secondary btn-sm">Delete Selected</button>
                <select v-model="bulkStatus" @change="applyBulkStatus" class="input w-44">
                    <option value="">Set lifecycle status…</option>
                    <option value="active">Active</option>
                    <option value="in_repair">In Repair</option>
                    <option value="retired">Retired</option>
                    <option value="disposed">Disposed</option>
                </select>
            </template>
            <button v-else @click="runBulk('restore')" class="btn-secondary btn-sm">Restore Selected</button>
            <button @click="selected = new Set()" class="text-xs text-gray-500 hover:underline ml-auto">Clear selection</button>
        </div>

        <!-- Table -->
        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th w-8"><input type="checkbox" :checked="allSelected" @change="toggleAll" /></th>
                        <th class="table-th">Tag</th>
                        <th class="table-th">Name</th>
                        <th class="table-th">Type</th>
                        <th class="table-th">Category</th>
                        <th class="table-th">Location</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Patch</th>
                        <th class="table-th">Criticality</th>
                        <th class="table-th">Cost</th>
                        <th class="table-th">Warranty Expires</th>
                        <th class="table-th w-24"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!assets.data.length">
                        <td colspan="12" class="py-12 text-center text-sm text-gray-400">
                            {{ trashed ? 'No trashed assets.' : 'No assets found.' }}
                        </td>
                    </tr>
                    <tr v-for="a in assets.data" :key="a.id" class="hover:bg-gray-50">
                        <td class="table-td"><input type="checkbox" :checked="selected.has(a.id)" @change="toggleOne(a.id)" /></td>
                        <td class="table-td font-mono text-xs font-semibold text-gray-700">{{ a.asset_tag }}</td>
                        <td class="table-td font-medium">
                            <Link v-if="!trashed" :href="`/registry/assets/${a.id}`" class="text-brand-600 hover:underline">{{ a.name }}</Link>
                            <span v-else class="text-gray-500">{{ a.name }}</span>
                        </td>
                        <td class="table-td">
                            <span :class="['badge', typeColors[a.type] ?? 'bg-gray-100 text-gray-600']">{{ a.type }}</span>
                        </td>
                        <td class="table-td text-sm text-gray-600">{{ a.category?.name ?? '—' }}</td>
                        <td class="table-td text-sm text-gray-600">{{ a.location ?? '—' }}</td>
                        <td class="table-td">
                            <span :class="['badge', lifecycleColors[a.lifecycle_status] ?? 'bg-gray-100 text-gray-600']">
                                {{ a.lifecycle_status?.replace('_', ' ') }}
                            </span>
                        </td>
                        <td class="table-td">
                            <span :class="['badge', patchColors[a.patch_status] ?? 'bg-gray-100 text-gray-600']">{{ a.patch_status }}</span>
                        </td>
                        <td class="table-td">
                            <span :class="['badge', critColors[a.criticality_393] ?? 'bg-gray-100 text-gray-600']">{{ a.criticality_393 }}</span>
                        </td>
                        <td class="table-td text-sm">{{ formatCurrency(a.cost) }}</td>
                        <td class="table-td text-sm" :class="warrantyClass(a.warranty_expires_on)">
                            {{ formatDate(a.warranty_expires_on) }}
                        </td>
                        <td class="table-td">
                            <div v-if="!trashed" class="flex items-center gap-1">
                                <Link :href="`/registry/assets/${a.id}`" class="text-gray-400 hover:text-brand-600" title="View">
                                    <EyeIcon class="h-4 w-4" />
                                </Link>
                                <Link :href="`/registry/assets/${a.id}/edit`" class="text-gray-400 hover:text-blue-600" title="Edit">
                                    <PencilIcon class="h-4 w-4" />
                                </Link>
                                <button @click="deleteAsset(a)" class="text-gray-400 hover:text-red-600" title="Delete">
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </div>
                            <div v-else class="flex items-center gap-1">
                                <button @click="restoreAsset(a)" class="text-gray-400 hover:text-green-600" title="Restore">
                                    <ArrowUturnLeftIcon class="h-4 w-4" />
                                </button>
                                <button @click="forceDeleteAsset(a)" class="text-gray-400 hover:text-red-600" title="Delete permanently">
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="assets.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">Showing {{ assets.from }}–{{ assets.to }} of {{ assets.total }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in assets.links" :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                        preserve-state v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Import CSV modal -->
        <div v-if="showImport" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 class="font-semibold text-gray-900 mb-1">Import Assets from CSV</h3>
                <p class="text-xs text-gray-500 mb-4">
                    Matches rows by Asset Tag — existing tags are updated, new tags are created.
                    <a href="/registry/import-template" class="text-brand-600 hover:underline">Download template</a>
                </p>
                <form @submit.prevent="submitImport" class="space-y-3">
                    <input type="file" accept=".csv,text/csv" class="input"
                        @change="importForm.file = $event.target.files[0]" required />
                    <p v-if="importForm.errors.file" class="text-xs text-red-600">{{ importForm.errors.file }}</p>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showImport = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="importForm.processing || !importForm.file">
                            {{ importForm.processing ? 'Importing…' : 'Import' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
