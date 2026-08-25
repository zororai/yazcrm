<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ assets: Array, categories: Array, isManager: Boolean });

const filters = ref({ search: '', status: '', warranty_expiring: false });
let debounce;
watch(filters, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/fixed-assets', {
            search: filters.value.search || undefined,
            status: filters.value.status || undefined,
            warranty_expiring: filters.value.warranty_expiring || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}, { deep: true });

const showForm = ref(false);
const form = useForm({
    asset_category_id: '', name: '', manufacturer: '', model: '', serial_number: '',
    purchase_date: '', purchase_cost: '', supplier_name: '', warranty_expiry: '',
});

function submit() {
    form.post('/fixed-assets', { onSuccess: () => { showForm.value = false; form.reset(); } });
}

function open(asset) {
    router.get(`/fixed-assets/${asset.id}`);
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
            <button v-if="isManager" @click="showForm = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> Register Asset
            </button>
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
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Asset #</th>
                        <th class="table-th">Name</th>
                        <th class="table-th">Category</th>
                        <th class="table-th">Custodian</th>
                        <th class="table-th">Department</th>
                        <th class="table-th">Status</th>
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
                        <td class="table-td"><span :class="['badge', statusColor[a.status]]">{{ a.status.replace('_', ' ') }}</span></td>
                    </tr>
                    <tr v-if="!assets.length">
                        <td colspan="6" class="table-td text-center text-gray-400 py-8">No assets match.</td>
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
    </AppLayout>
</template>
