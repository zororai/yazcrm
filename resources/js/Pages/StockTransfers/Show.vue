<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { TruckIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ transfer: Object, isManager: Boolean });

function dispatch() {
    if (!confirm('Dispatch this transfer? Stock will be deducted from the source store.')) return;
    router.post(`/stock-transfers/${props.transfer.id}/dispatch`);
}

function receive() {
    if (!confirm('Confirm receipt? Stock will be added to the destination store.')) return;
    router.post(`/stock-transfers/${props.transfer.id}/receive`);
}

function cancel() {
    if (!confirm('Cancel this transfer?')) return;
    router.post(`/stock-transfers/${props.transfer.id}/cancel`);
}

const statusColor = {
    draft: 'bg-gray-100 text-gray-700',
    dispatched: 'bg-amber-100 text-amber-800',
    received: 'bg-green-100 text-green-800',
    cancelled: 'bg-gray-200 text-gray-500',
};
</script>

<template>
    <AppLayout>
        <template #title>Transfer {{ transfer.transfer_number }}</template>
        <template #header-actions>
            <div class="flex gap-2" v-if="isManager">
                <button v-if="transfer.status === 'draft'" @click="dispatch" class="btn-primary btn-sm"><TruckIcon class="h-4 w-4" /> Dispatch</button>
                <button v-if="transfer.status === 'dispatched'" @click="receive" class="btn-primary btn-sm"><CheckCircleIcon class="h-4 w-4" /> Confirm Receipt</button>
                <button v-if="transfer.status === 'draft'" @click="cancel" class="btn-danger btn-sm"><XCircleIcon class="h-4 w-4" /> Cancel</button>
            </div>
        </template>

        <div class="card mb-4 grid grid-cols-4 gap-4 text-sm text-gray-600">
            <div><p class="text-gray-400">From</p><p class="font-medium text-gray-900">{{ transfer.from_store?.name }}</p></div>
            <div><p class="text-gray-400">To</p><p class="font-medium text-gray-900">{{ transfer.to_store?.name }}</p></div>
            <div><p class="text-gray-400">Requested By</p><p class="font-medium text-gray-900">{{ transfer.requested_by?.name }}</p></div>
            <div><p class="text-gray-400">Status</p><span :class="['badge', statusColor[transfer.status]]">{{ transfer.status }}</span></div>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Item</th>
                        <th class="table-th">Quantity</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="line in transfer.items" :key="line.id">
                        <td class="table-td font-medium">{{ line.item?.name }} <span class="text-xs text-gray-400">({{ line.item?.item_code }})</span></td>
                        <td class="table-td">{{ line.quantity }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="transfer.notes" class="text-sm text-gray-500 mt-4">{{ transfer.notes }}</p>
    </AppLayout>
</template>
