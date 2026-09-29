<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowDownTrayIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ assets: Array, isManager: Boolean });

function money(v) {
    if (v === null || v === undefined) return '—';
    return Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function sum(key) {
    return props.assets.reduce((total, a) => total + (Number(a[key]) || 0), 0);
}

const totals = computed(() => ({
    salvage: sum('salvage_value'),
    annual: sum('annual_depreciation'),
    accumulated: sum('accumulated_depreciation'),
    book: sum('book_value'),
}));
</script>

<template>
    <AppLayout>
        <template #title>Depreciation Report</template>
        <template #header-actions>
            <div class="flex gap-2">
                <Link href="/fixed-assets" class="btn-secondary btn-sm inline-flex items-center gap-1">
                    <ArrowLeftIcon class="h-4 w-4" /> Back to Assets
                </Link>
                <a href="/fixed-assets/depreciation-report/export/excel" class="btn-secondary btn-sm inline-flex items-center gap-1">
                    <ArrowDownTrayIcon class="h-4 w-4" /> Excel
                </a>
                <a href="/fixed-assets/depreciation-report/export/pdf" class="btn-secondary btn-sm inline-flex items-center gap-1">
                    <ArrowDownTrayIcon class="h-4 w-4" /> PDF
                </a>
            </div>
        </template>

        <div class="card p-0 overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Asset Name</th>
                        <th class="table-th">Salvage Value</th>
                        <th class="table-th">Annual Depreciation</th>
                        <th class="table-th">Accumulated Depreciation</th>
                        <th class="table-th">Book Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="a in assets" :key="a.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium">{{ a.name }}</td>
                        <td class="table-td">{{ money(a.salvage_value) }}</td>
                        <td class="table-td">{{ a.annual_depreciation !== null ? money(a.annual_depreciation) : '—' }}</td>
                        <td class="table-td">{{ a.accumulated_depreciation !== null ? money(a.accumulated_depreciation) : '—' }}</td>
                        <td class="table-td font-medium">{{ a.book_value !== null ? money(a.book_value) : '—' }}</td>
                    </tr>
                    <tr v-if="!assets.length">
                        <td colspan="5" class="table-td text-center text-gray-400 py-8">No assets found.</td>
                    </tr>
                </tbody>
                <tfoot v-if="assets.length" class="bg-gray-50 border-t border-gray-100 font-semibold">
                    <tr>
                        <td class="table-td">Total</td>
                        <td class="table-td">{{ money(totals.salvage) }}</td>
                        <td class="table-td">{{ money(totals.annual) }}</td>
                        <td class="table-td">{{ money(totals.accumulated) }}</td>
                        <td class="table-td">{{ money(totals.book) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </AppLayout>
</template>
