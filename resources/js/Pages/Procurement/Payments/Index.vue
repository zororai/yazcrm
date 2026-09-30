<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ payments: Array, isHof: Boolean, isApprover: Boolean });

const filters = ref({ status: '' });
let debounce;
watch(filters, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/procurement-payments', { status: filters.value.status || undefined }, { preserveState: true, replace: true });
    }, 200);
}, { deep: true });

function open(p) {
    router.get(`/procurement-payments/${p.id}`);
}

const statusColor = {
    draft: 'bg-gray-200 text-gray-600',
    pending_review: 'bg-amber-100 text-amber-800',
    pending_approval: 'bg-blue-100 text-blue-800',
    approved: 'bg-indigo-100 text-indigo-800',
    loaded: 'bg-purple-100 text-purple-800',
    released: 'bg-teal-100 text-teal-800',
    recorded: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-200 text-gray-500',
};
</script>

<template>
    <AppLayout>
        <template #title>Payment Requisitions</template>

        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="label">Status</label>
                <select v-model="filters.status" class="input">
                    <option value="">All</option>
                    <option value="draft">Draft</option>
                    <option value="pending_review">Pending Review</option>
                    <option value="pending_approval">Pending Approval</option>
                    <option value="approved">Approved</option>
                    <option value="loaded">Loaded</option>
                    <option value="released">Released</option>
                    <option value="recorded">Recorded</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <p class="text-xs text-gray-400 pb-2" v-if="!isHof && !isApprover">Showing payments you prepared.</p>
            <p class="text-xs text-gray-400 pb-2" v-else>Showing all payment requisitions.</p>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Payment #</th>
                        <th class="table-th">Purchase Order</th>
                        <th class="table-th">Payee</th>
                        <th class="table-th">Prepared By</th>
                        <th class="table-th">Amount</th>
                        <th class="table-th">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="p in payments" :key="p.id" class="hover:bg-gray-50 cursor-pointer" @click="open(p)">
                        <td class="table-td font-medium">{{ p.payment_number }}</td>
                        <td class="table-td">{{ p.purchase_order?.po_number ?? '—' }}</td>
                        <td class="table-td">{{ p.payee_name }}</td>
                        <td class="table-td">{{ p.prepared_by?.name ?? '—' }}</td>
                        <td class="table-td">{{ p.currency }} {{ Number(p.amount).toFixed(2) }}</td>
                        <td class="table-td"><span :class="['badge', statusColor[p.status]]">{{ p.status.replace(/_/g, ' ') }}</span></td>
                    </tr>
                    <tr v-if="!payments.length">
                        <td colspan="6" class="table-td text-center text-gray-400 py-8">No payment requisitions yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
