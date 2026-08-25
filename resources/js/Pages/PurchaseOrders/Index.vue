<script setup>
import { ref, watch, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, TrashIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ orders: Array, suppliers: Array, stores: Array, items: Array, isManager: Boolean });

const filters = ref({ status: '' });
let debounce;
watch(filters, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/purchase-orders', { status: filters.value.status || undefined }, { preserveState: true, replace: true });
    }, 200);
}, { deep: true });

const showForm = ref(false);
const form = useForm({
    supplier_id: '', store_id: '', order_date: '', expected_delivery_date: '', tax: 0, notes: '',
    lines: [{ item_id: '', description: '', quantity: 1, unit_cost: 0 }],
});

function addLine() {
    form.lines.push({ item_id: '', description: '', quantity: 1, unit_cost: 0 });
}
function removeLine(i) {
    form.lines.splice(i, 1);
}

const subtotal = computed(() => form.lines.reduce((sum, l) => sum + (Number(l.quantity) || 0) * (Number(l.unit_cost) || 0), 0));

function submit() {
    form.post('/purchase-orders', { onSuccess: () => { showForm.value = false; form.reset(); } });
}

function open(po) {
    router.get(`/purchase-orders/${po.id}`);
}

const statusColor = {
    draft: 'bg-gray-200 text-gray-600',
    pending_approval: 'bg-amber-100 text-amber-800',
    approved: 'bg-blue-100 text-blue-800',
    rejected: 'bg-red-100 text-red-800',
    sent: 'bg-indigo-100 text-indigo-800',
    partially_received: 'bg-orange-100 text-orange-800',
    received: 'bg-green-100 text-green-800',
    cancelled: 'bg-gray-200 text-gray-500',
};
</script>

<template>
    <AppLayout>
        <template #title>Purchase Orders</template>
        <template #header-actions>
            <div class="flex gap-2">
                <a href="/purchase-orders/export" class="btn-secondary btn-sm">
                    <ArrowDownTrayIcon class="h-4 w-4" /> Export CSV
                </a>
                <button v-if="isManager" @click="showForm = true" class="btn-primary btn-sm">
                    <PlusIcon class="h-4 w-4" /> New Purchase Order
                </button>
            </div>
        </template>

        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="label">Status</label>
                <select v-model="filters.status" class="input">
                    <option value="">All</option>
                    <option value="draft">Draft</option>
                    <option value="pending_approval">Pending Approval</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="sent">Sent</option>
                    <option value="partially_received">Partially Received</option>
                    <option value="received">Received</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">PO #</th>
                        <th class="table-th">Supplier</th>
                        <th class="table-th">Requested By</th>
                        <th class="table-th">Order Date</th>
                        <th class="table-th">Total</th>
                        <th class="table-th">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="po in orders" :key="po.id" class="hover:bg-gray-50 cursor-pointer" @click="open(po)">
                        <td class="table-td font-medium">{{ po.po_number }}</td>
                        <td class="table-td">{{ po.supplier?.name ?? '—' }}</td>
                        <td class="table-td">{{ po.requested_by?.name ?? '—' }}</td>
                        <td class="table-td">{{ po.order_date ?? '—' }}</td>
                        <td class="table-td">{{ po.currency }} {{ Number(po.total).toFixed(2) }}</td>
                        <td class="table-td"><span :class="['badge', statusColor[po.status]]">{{ po.status.replace('_', ' ') }}</span></td>
                    </tr>
                    <tr v-if="!orders.length">
                        <td colspan="6" class="table-td text-center text-gray-400 py-8">No purchase orders yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">New Purchase Order</h3>
                </div>
                <form @submit.prevent="submit" class="overflow-y-auto flex-1 px-6 py-4 space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Supplier</label>
                            <select v-model="form.supplier_id" class="input" required>
                                <option value="">Select…</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <p v-if="form.errors.supplier_id" class="mt-1 text-xs text-red-600">{{ form.errors.supplier_id }}</p>
                        </div>
                        <div>
                            <label class="label">Deliver To Store</label>
                            <select v-model="form.store_id" class="input">
                                <option value="">None</option>
                                <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Order Date</label>
                            <input v-model="form.order_date" type="date" class="input" />
                        </div>
                        <div>
                            <label class="label">Expected Delivery</label>
                            <input v-model="form.expected_delivery_date" type="date" class="input" />
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="label mb-0">Line Items</label>
                            <button type="button" @click="addLine" class="text-xs text-brand-600 hover:underline">+ Add line</button>
                        </div>
                        <div v-for="(line, i) in form.lines" :key="i" class="grid grid-cols-12 gap-2 items-center mb-2">
                            <select v-model="line.item_id" class="input col-span-4">
                                <option value="">Custom / other…</option>
                                <option v-for="it in items" :key="it.id" :value="it.id">{{ it.name }}</option>
                            </select>
                            <input v-model="line.description" class="input col-span-3" placeholder="Description" />
                            <input v-model.number="line.quantity" type="number" min="1" class="input col-span-2" placeholder="Qty" />
                            <input v-model.number="line.unit_cost" type="number" min="0" step="0.01" class="input col-span-2" placeholder="Unit cost" />
                            <button type="button" @click="removeLine(i)" class="col-span-1 text-gray-400 hover:text-red-600" :disabled="form.lines.length === 1">
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Tax</label>
                            <input v-model.number="form.tax" type="number" min="0" step="0.01" class="input" />
                        </div>
                        <div class="text-sm text-gray-600 flex flex-col justify-end pb-2">
                            <div>Subtotal: {{ subtotal.toFixed(2) }}</div>
                            <div class="font-medium">Total: {{ (subtotal + (Number(form.tax) || 0)).toFixed(2) }}</div>
                        </div>
                    </div>

                    <div>
                        <label class="label">Notes</label>
                        <textarea v-model="form.notes" class="input" rows="2"></textarea>
                    </div>
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="submit" class="btn-primary" :disabled="form.processing">Create</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
