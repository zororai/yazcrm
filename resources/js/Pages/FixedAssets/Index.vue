<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, ArrowDownTrayIcon, ArrowUpTrayIcon, ClockIcon, ChartBarIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ assets: Array, categories: Array, isManager: Boolean, canDelete: Boolean });

function exportUrl(type) {
    const params = new URLSearchParams({
        search: filters.value.search || '',
        status: filters.value.status || '',
        warranty_expiring: filters.value.warranty_expiring ? '1' : '',
        revaluation_due: filters.value.revaluation_due ? '1' : '',
    });
    return `/fixed-assets/export/${type}?${params.toString()}`;
}

const filters = ref({ search: '', status: '', warranty_expiring: false, revaluation_due: false });
let debounce;
watch(filters, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/fixed-assets', {
            search: filters.value.search || undefined,
            status: filters.value.status || undefined,
            warranty_expiring: filters.value.warranty_expiring || undefined,
            revaluation_due: filters.value.revaluation_due || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}, { deep: true });

const showForm = ref(false);
const form = useForm({
    asset_category_id: '', name: '', manufacturer: '', model: '', serial_number: '',
    purchase_date: '', purchase_cost: '', useful_life_years: '', salvage_value: '',
    revaluation_cycle_years: 3,
    supplier_name: '', warranty_expiry: '',
});

function money(v) {
    if (v === null || v === undefined) return '—';
    return Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function submit() {
    form.post('/fixed-assets', { onSuccess: () => { showForm.value = false; form.reset(); } });
}

function open(asset) {
    router.get(`/fixed-assets/${asset.id}`);
}

// ── Edit ─────────────────────────────────────────────────────────────────────
// Saved changes are written to the Audit Trail as field: old → new.
const editing = ref(null);
const editForm = useForm({
    asset_category_id: '', name: '', description: '', manufacturer: '', model: '', serial_number: '',
    purchase_date: '', purchase_cost: '', useful_life_years: '', salvage_value: '',
    revaluation_cycle_years: '', supplier_name: '', warranty_expiry: '',
});
const day = v => (v ? String(v).slice(0, 10) : '');
function startEdit(asset) {
    editing.value = asset;
    editForm.clearErrors();
    Object.assign(editForm, {
        asset_category_id: asset.asset_category_id ?? '',
        name: asset.name ?? '',
        description: asset.description ?? '',
        manufacturer: asset.manufacturer ?? '',
        model: asset.model ?? '',
        serial_number: asset.serial_number ?? '',
        purchase_date: day(asset.purchase_date),
        purchase_cost: asset.purchase_cost ?? '',
        useful_life_years: asset.useful_life_years ?? '',
        salvage_value: asset.salvage_value ?? '',
        revaluation_cycle_years: asset.revaluation_cycle_years ?? '',
        supplier_name: asset.supplier_name ?? '',
        warranty_expiry: day(asset.warranty_expiry),
    });
}
function saveEdit() {
    editForm.put(`/fixed-assets/${editing.value.id}`, { preserveScroll: true, onSuccess: () => { editing.value = null; } });
}

// ── Import ───────────────────────────────────────────────────────────────────
// All-or-nothing: if any row fails, the server lists every problem by row.
const showImport = ref(false);
const importForm = useForm({ file: null });
const importErrors = computed(() => Object.entries(importForm.errors)
    .filter(([key]) => key.startsWith('rows.'))
    .map(([, message]) => message));
function openImport() {
    importForm.reset();
    importForm.clearErrors();
    showImport.value = true;
}
function submitImport() {
    importForm.post('/fixed-assets/import', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { showImport.value = false; importForm.reset(); },
    });
}

// ── Delete ───────────────────────────────────────────────────────────────────
// Soft delete with a required reason, recorded in the Audit Trail.
const deleting = ref(null);
const deleteForm = useForm({ reason: '' });
function startDelete(asset) {
    deleting.value = asset;
    deleteForm.reset();
    deleteForm.clearErrors();
}
function confirmDelete() {
    deleteForm.delete(`/fixed-assets/${deleting.value.id}`, { preserveScroll: true, onSuccess: () => { deleting.value = null; } });
}

const statusColor = {
    available: 'bg-green-100 text-green-800',
    reserved: 'bg-blue-100 text-blue-800',
    assigned: 'bg-amber-100 text-amber-800',
    in_transit: 'bg-blue-100 text-blue-800',
    under_maintenance: 'bg-orange-100 text-orange-800',
    damaged: 'bg-red-100 text-red-800',
    lost: 'bg-red-100 text-red-800',
    stolen: 'bg-red-100 text-red-800',
    retired: 'bg-gray-200 text-gray-500',
    disposed: 'bg-gray-200 text-gray-500',
};
</script>

<template>
    <AppLayout>
        <template #title>Fixed Assets</template>
        <template #header-actions>
            <div class="flex gap-2">
                <Link href="/fixed-assets/revaluations" class="btn-secondary btn-sm inline-flex items-center gap-1">
                    <ClockIcon class="h-4 w-4" /> Revaluations
                </Link>
                <Link href="/fixed-assets/depreciation-report" class="btn-secondary btn-sm inline-flex items-center gap-1">
                    <ChartBarIcon class="h-4 w-4" /> Depreciation Report
                </Link>
                <a :href="exportUrl('excel')" class="btn-secondary btn-sm inline-flex items-center gap-1">
                    <ArrowDownTrayIcon class="h-4 w-4" /> Excel
                </a>
                <a :href="exportUrl('pdf')" class="btn-secondary btn-sm inline-flex items-center gap-1">
                    <ArrowDownTrayIcon class="h-4 w-4" /> PDF
                </a>
                <button v-if="isManager" @click="openImport" class="btn-secondary btn-sm inline-flex items-center gap-1">
                    <ArrowUpTrayIcon class="h-4 w-4" /> Import
                </button>
                <button v-if="isManager" @click="showForm = true" class="btn-primary btn-sm">
                    <PlusIcon class="h-4 w-4" /> Register Asset
                </button>
            </div>
        </template>

        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="label">Search</label>
                <input v-model="filters.search" class="input" placeholder="Name, asset #, serial #…" />
            </div>
            <div>
                <label class="label">Status</label>
                <select v-model="filters.status" class="input">
                    <option value="">All</option>
                    <option value="available">Available</option>
                    <option value="assigned">Assigned</option>
                    <option value="under_maintenance">Under Maintenance</option>
                    <option value="damaged">Damaged</option>
                    <option value="lost">Lost</option>
                    <option value="stolen">Stolen</option>
                    <option value="retired">Retired</option>
                    <option value="disposed">Disposed</option>
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-600 pb-2">
                <input type="checkbox" v-model="filters.warranty_expiring" /> Warranty expiring soon
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-600 pb-2">
                <input type="checkbox" v-model="filters.revaluation_due" /> Revaluation due
            </label>
        </div>

        <div class="card p-0 overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Asset #</th>
                        <th class="table-th">Name</th>
                        <th class="table-th">Category</th>
                        <th class="table-th">Custodian</th>
                        <th class="table-th">Department</th>
                        <th class="table-th">Useful Life</th>
                        <th class="table-th">Salvage Value</th>
                        <th class="table-th">Annual Depreciation</th>
                        <th class="table-th">Accumulated Depreciation</th>
                        <th class="table-th">Book Value</th>
                        <th class="table-th">Next Revaluation</th>
                        <th class="table-th">Status</th>
                        <th v-if="isManager || canDelete" class="table-th text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="a in assets" :key="a.id" class="hover:bg-gray-50 cursor-pointer" @click="open(a)">
                        <td class="table-td font-medium">{{ a.asset_number }}</td>
                        <td class="table-td">
                            {{ a.name }}
                            <span v-if="a.warranty_expiring" class="badge bg-orange-100 text-orange-800 ml-1 text-[10px]">warranty expiring</span>
                        </td>
                        <td class="table-td">{{ a.category?.name ?? '—' }}</td>
                        <td class="table-td">{{ a.custodian?.name ?? '—' }}</td>
                        <td class="table-td">{{ a.department?.name ?? '—' }}</td>
                        <td class="table-td">{{ a.useful_life_years ? `${a.useful_life_years} yrs` : '—' }}</td>
                        <td class="table-td">{{ money(a.salvage_value) }}</td>
                        <td class="table-td">{{ a.annual_depreciation !== null ? money(a.annual_depreciation) : '—' }}</td>
                        <td class="table-td">{{ a.accumulated_depreciation !== null ? money(a.accumulated_depreciation) : '—' }}</td>
                        <td class="table-td font-medium">{{ a.book_value !== null ? money(a.book_value) : '—' }}</td>
                        <td class="table-td">
                            {{ a.next_revaluation_due ?? '—' }}
                            <span v-if="a.revaluation_due" class="badge bg-red-100 text-red-800 ml-1 text-[10px]">due</span>
                        </td>
                        <td class="table-td"><span :class="['badge', statusColor[a.status]]">{{ a.status.replace('_', ' ') }}</span></td>
                        <td v-if="isManager || canDelete" class="table-td text-right whitespace-nowrap" @click.stop>
                            <button v-if="isManager" type="button" @click="startEdit(a)" class="p-1.5 rounded text-gray-400 hover:text-brand-600 hover:bg-gray-100" :title="`Edit ${a.asset_number}`" :aria-label="`Edit ${a.asset_number}`">
                                <PencilSquareIcon class="h-4 w-4" />
                            </button>
                            <button v-if="canDelete" type="button" @click="startDelete(a)" class="p-1.5 rounded text-gray-400 hover:text-red-600 hover:bg-red-50" :title="`Delete ${a.asset_number}`" :aria-label="`Delete ${a.asset_number}`">
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!assets.length">
                        <td :colspan="isManager || canDelete ? 13 : 12" class="table-td text-center text-gray-400 py-8">No assets match.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[90vh] flex flex-col">
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">Register Asset</h3>
                </div>
                <form @submit.prevent="submit" class="overflow-y-auto flex-1 px-6 py-4 space-y-3">
                    <div>
                        <label class="label">Name</label>
                        <input v-model="form.name" class="input" required />
                    </div>
                    <div>
                        <label class="label">Category</label>
                        <select v-model="form.asset_category_id" class="input">
                            <option value="">None</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Manufacturer</label>
                            <input v-model="form.manufacturer" class="input" />
                        </div>
                        <div>
                            <label class="label">Model</label>
                            <input v-model="form.model" class="input" />
                        </div>
                    </div>
                    <div>
                        <label class="label">Serial Number</label>
                        <input v-model="form.serial_number" class="input" />
                        <p v-if="form.errors.serial_number" class="mt-1 text-xs text-red-600">{{ form.errors.serial_number }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Purchase Date</label>
                            <input v-model="form.purchase_date" type="date" class="input" />
                        </div>
                        <div>
                            <label class="label">Purchase Cost</label>
                            <input v-model.number="form.purchase_cost" type="number" min="0" step="0.01" class="input" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Useful Life (years)</label>
                            <input v-model.number="form.useful_life_years" type="number" min="1" step="1" class="input" placeholder="e.g. 5" />
                            <p v-if="form.errors.useful_life_years" class="mt-1 text-xs text-red-600">{{ form.errors.useful_life_years }}</p>
                        </div>
                        <div>
                            <label class="label">Salvage Value</label>
                            <input v-model.number="form.salvage_value" type="number" min="0" step="0.01" class="input" placeholder="0.00" />
                        </div>
                    </div>
                    <div>
                        <label class="label">Revaluation Cycle (years)</label>
                        <input v-model.number="form.revaluation_cycle_years" type="number" min="1" step="1" class="input" placeholder="e.g. 3" />
                        <p class="mt-1 text-xs text-gray-400">How often this asset should be revalued. Defaults to 3 years.</p>
                    </div>
                    <div>
                        <label class="label">Supplier</label>
                        <input v-model="form.supplier_name" class="input" />
                    </div>
                    <div>
                        <label class="label">Warranty Expiry</label>
                        <input v-model="form.warranty_expiry" type="date" class="input" />
                    </div>
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="submit" class="btn-primary" :disabled="form.processing">Register</button>
                </div>
            </div>
        </div>

        <!-- Import assets -->
        <div v-if="showImport" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col">
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">Import Assets</h3>
                    <p class="text-xs text-gray-400 mt-1">Register many assets at once from an Excel or CSV file.</p>
                </div>
                <form @submit.prevent="submitImport" class="overflow-y-auto flex-1 px-6 py-4 space-y-4">
                    <ol class="text-sm text-gray-600 space-y-2 list-decimal list-inside">
                        <li>
                            <a href="/fixed-assets/import-template" class="text-brand-600 hover:underline font-medium">Download the Excel template</a>
                            — it has drop-down lists for Category, Condition, Department and Location, and an
                            <em>Instructions</em> sheet explaining every column.
                        </li>
                        <li>Fill one asset per row on the <em>Assets</em> sheet. Only <strong>Name</strong> is required; asset numbers are created automatically.</li>
                        <li>Upload the file below. Every row is checked first — if any row has a problem, nothing is imported and you'll see what to fix.</li>
                    </ol>
                    <div>
                        <label class="label">File (.xlsx, .xls or .csv, up to 5 MB)</label>
                        <input type="file" accept=".xlsx,.xls,.csv" class="input" @change="importForm.file = $event.target.files[0]" />
                        <p v-if="importForm.errors.file" class="mt-1 text-xs text-red-600">{{ importForm.errors.file }}</p>
                    </div>
                    <div v-if="importErrors.length" class="rounded-lg border border-red-200 bg-red-50 p-3">
                        <p class="text-sm font-medium text-red-800">Nothing was imported — fix these {{ importErrors.length }} problem{{ importErrors.length === 1 ? '' : 's' }} and upload again:</p>
                        <ul class="mt-2 text-xs text-red-700 space-y-1 max-h-48 overflow-y-auto">
                            <li v-for="(e, i) in importErrors" :key="i">{{ e }}</li>
                        </ul>
                    </div>
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="showImport = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="submitImport" class="btn-primary" :disabled="importForm.processing || !importForm.file">
                        {{ importForm.processing ? 'Importing…' : 'Import' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit asset -->
        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[90vh] flex flex-col">
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">Edit {{ editing.asset_number }}</h3>
                    <p class="text-xs text-gray-400 mt-1">Changes are recorded in the Audit Trail and the asset's history.</p>
                </div>
                <form @submit.prevent="saveEdit" class="overflow-y-auto flex-1 px-6 py-4 space-y-3">
                    <div>
                        <label class="label">Name</label>
                        <input v-model="editForm.name" class="input" required />
                        <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="label">Category</label>
                        <select v-model="editForm.asset_category_id" class="input">
                            <option value="">None</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Description</label>
                        <textarea v-model="editForm.description" class="input" rows="2"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Manufacturer</label>
                            <input v-model="editForm.manufacturer" class="input" />
                        </div>
                        <div>
                            <label class="label">Model</label>
                            <input v-model="editForm.model" class="input" />
                        </div>
                    </div>
                    <div>
                        <label class="label">Serial Number</label>
                        <input v-model="editForm.serial_number" class="input" />
                        <p v-if="editForm.errors.serial_number" class="mt-1 text-xs text-red-600">{{ editForm.errors.serial_number }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Purchase Date</label>
                            <input v-model="editForm.purchase_date" type="date" class="input" />
                        </div>
                        <div>
                            <label class="label">Purchase Cost</label>
                            <input v-model="editForm.purchase_cost" type="number" min="0" step="0.01" class="input" />
                            <p v-if="editForm.errors.purchase_cost" class="mt-1 text-xs text-red-600">{{ editForm.errors.purchase_cost }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Useful Life (years)</label>
                            <input v-model="editForm.useful_life_years" type="number" min="1" step="1" class="input" />
                            <p v-if="editForm.errors.useful_life_years" class="mt-1 text-xs text-red-600">{{ editForm.errors.useful_life_years }}</p>
                        </div>
                        <div>
                            <label class="label">Salvage Value</label>
                            <input v-model="editForm.salvage_value" type="number" min="0" step="0.01" class="input" />
                        </div>
                    </div>
                    <div>
                        <label class="label">Revaluation Cycle (years)</label>
                        <input v-model="editForm.revaluation_cycle_years" type="number" min="1" step="1" class="input" />
                    </div>
                    <div>
                        <label class="label">Supplier</label>
                        <input v-model="editForm.supplier_name" class="input" />
                    </div>
                    <div>
                        <label class="label">Warranty Expiry</label>
                        <input v-model="editForm.warranty_expiry" type="date" class="input" />
                    </div>
                    <p class="text-xs text-gray-400">Changing cost, useful life, salvage value or purchase date recalculates depreciation and book value.</p>
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="editing = null" class="btn-secondary">Cancel</button>
                    <button type="button" @click="saveEdit" class="btn-primary" :disabled="editForm.processing">Save Changes</button>
                </div>
            </div>
        </div>

        <!-- Delete asset -->
        <div v-if="deleting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900">Delete {{ deleting.asset_number }}?</h3>
                <p class="text-sm text-gray-600 mt-1">{{ deleting.name }}</p>
                <p class="text-xs text-gray-500 mt-3">
                    Use this for assets registered by mistake. For assets that are sold, scrapped or written off, use
                    <strong>Dispose</strong> on the asset's page instead so the register keeps its record.
                </p>
                <label class="label mt-4">Reason for deleting</label>
                <textarea v-model="deleteForm.reason" class="input" rows="3" required placeholder="e.g. Duplicate of FA-0012"></textarea>
                <p v-if="deleteForm.errors.reason" class="mt-1 text-xs text-red-600">{{ deleteForm.errors.reason }}</p>
                <p class="text-xs text-gray-400 mt-2">The deletion and reason are recorded in the Audit Trail.</p>
                <div class="flex gap-2 justify-end mt-4">
                    <button type="button" @click="deleting = null" class="btn-secondary">Cancel</button>
                    <button type="button" @click="confirmDelete" class="btn-danger" :disabled="deleteForm.processing || !deleteForm.reason.trim()">Delete Asset</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
