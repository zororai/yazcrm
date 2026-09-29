<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowDownTrayIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ assets: Array, dueOnly: Boolean, isManager: Boolean });

const dueOnly = ref(props.dueOnly);
watch(dueOnly, (val) => {
    router.get('/fixed-assets/revaluations', { due_only: val || undefined }, { preserveState: true, replace: true });
});

function exportUrl(type) {
    const params = new URLSearchParams({ due_only: dueOnly.value ? '1' : '' });
    return `/fixed-assets/revaluations/export/${type}?${params.toString()}`;
}

function money(v) {
    if (v === null || v === undefined) return '—';
    return Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function open(asset) {
    router.get(`/fixed-assets/${asset.id}`);
}
</script>

<template>
    <AppLayout>
        <template #title>Asset Revaluations</template>
        <template #header-actions>
            <div class="flex gap-2">
                <Link href="/fixed-assets" class="btn-secondary btn-sm inline-flex items-center gap-1">
                    <ArrowLeftIcon class="h-4 w-4" /> Back to Assets
                </Link>
                <a :href="exportUrl('excel')" class="btn-secondary btn-sm inline-flex items-center gap-1">
                    <ArrowDownTrayIcon class="h-4 w-4" /> Excel
                </a>
                <a :href="exportUrl('pdf')" class="btn-secondary btn-sm inline-flex items-center gap-1">
                    <ArrowDownTrayIcon class="h-4 w-4" /> PDF
                </a>
            </div>
        </template>

        <div class="card mb-4 flex items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" v-model="dueOnly" /> Show only revaluations due
            </label>
            <span class="text-sm text-gray-400">{{ assets.length }} asset(s)</span>
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
                        <th class="table-th">Cycle</th>
                        <th class="table-th">Last Revalued</th>
                        <th class="table-th">Next Due</th>
                        <th class="table-th">Current Value</th>
                        <th class="table-th">Book Value</th>
                        <th class="table-th">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="a in assets" :key="a.id" class="hover:bg-gray-50 cursor-pointer" @click="open(a)">
                        <td class="table-td font-medium">{{ a.asset_number }}</td>
                        <td class="table-td">{{ a.name }}</td>
                        <td class="table-td">{{ a.category?.name ?? '—' }}</td>
                        <td class="table-td">{{ a.custodian?.name ?? '—' }}</td>
                        <td class="table-td">{{ a.department?.name ?? '—' }}</td>
                        <td class="table-td">{{ a.revaluation_cycle_years ? `${a.revaluation_cycle_years} yrs` : '—' }}</td>
                        <td class="table-td">{{ a.last_revalued_at ? new Date(a.last_revalued_at).toLocaleDateString() : 'Never' }}</td>
                        <td class="table-td">{{ a.next_revaluation_due ? new Date(a.next_revaluation_due).toLocaleDateString() : '—' }}</td>
                        <td class="table-td">{{ money(a.current_value) }}</td>
                        <td class="table-td font-medium">{{ money(a.book_value) }}</td>
                        <td class="table-td">
                            <span v-if="a.revaluation_due" class="badge bg-red-100 text-red-800">Due</span>
                            <span v-else class="badge bg-green-100 text-green-800">Up to date</span>
                        </td>
                    </tr>
                    <tr v-if="!assets.length">
                        <td colspan="11" class="table-td text-center text-gray-400 py-8">No assets match.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
