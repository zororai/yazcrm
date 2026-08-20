<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CheckCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ stocktake: Object, isManager: Boolean });

const countsForm = useForm({
    counts: Object.fromEntries(props.stocktake.items.map(i => [i.item_id, i.physical_quantity ?? ''])),
});

function saveCounts() {
    countsForm.put(`/stocktakes/${props.stocktake.id}`);
}

function complete() {
    if (!confirm('Complete this stocktake? Any variances will be posted as stock adjustments and cannot be undone.')) return;
    router.post(`/stocktakes/${props.stocktake.id}/complete`);
}

const statusColor = {
    counting: 'bg-amber-100 text-amber-800',
    completed: 'bg-green-100 text-green-800',
};
</script>

<template>
    <AppLayout>
        <template #title>Stocktake {{ stocktake.stocktake_number }}</template>
        <template #header-actions>
            <div class="flex gap-2" v-if="isManager && stocktake.status === 'counting'">
                <button @click="saveCounts" class="btn-secondary btn-sm" :disabled="countsForm.processing">Save Counts</button>
                <button @click="complete" class="btn-primary btn-sm"><CheckCircleIcon class="h-4 w-4" /> Complete</button>
            </div>
        </template>

        <div class="card mb-4 flex items-center gap-4 text-sm text-gray-600">
            <span><span class="text-gray-400">Store:</span> {{ stocktake.store?.name }}</span>
            <span><span class="text-gray-400">Started By:</span> {{ stocktake.started_by?.name }}</span>
            <span :class="['badge', statusColor[stocktake.status]]">{{ stocktake.status }}</span>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Item</th>
                        <th class="table-th">System Qty</th>
                        <th class="table-th">Physical Qty</th>
                        <th class="table-th">Variance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="line in stocktake.items" :key="line.id">
                        <td class="table-td font-medium">{{ line.item?.name }} <span class="text-xs text-gray-400">({{ line.item?.item_code }})</span></td>
                        <td class="table-td">{{ line.system_quantity }}</td>
                        <td class="table-td">
                            <input
                                v-if="stocktake.status === 'counting'"
                                v-model.number="countsForm.counts[line.item_id]"
                                type="number" min="0" class="input w-24"
                            />
                            <span v-else>{{ line.physical_quantity ?? '—' }}</span>
                        </td>
                        <td class="table-td" :class="line.variance && line.variance !== 0 ? (line.variance < 0 ? 'text-red-600' : 'text-green-600') : ''">
                            {{ line.variance ?? '—' }}
                        </td>
                    </tr>
                    <tr v-if="!stocktake.items.length">
                        <td colspan="4" class="table-td text-center text-gray-400 py-8">No stock to count in this store.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
