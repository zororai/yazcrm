<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ report: Object });

const year = ref(props.report.year);
watch(year, (y) => router.get('/leave/reports', { year: y }, { preserveState: true, replace: true }));

const statuses = ['pending', 'approved', 'rejected', 'cancelled'];
const search = ref('');
const rows = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.report.rows.filter(r => !q || r.user.name.toLowerCase().includes(q) || r.leave_type.name.toLowerCase().includes(q));
});
</script>

<template>
    <AppLayout>
        <template #title>Leave Reports</template>
        <template #header-actions>
            <a :href="`/leave/reports/export?year=${year}`" class="btn-secondary btn-sm"><ArrowDownTrayIcon class="h-4 w-4" /> Export CSV</a>
        </template>

        <div class="card mb-4 flex items-end gap-3">
            <div>
                <label class="label">Year</label>
                <input v-model.number="year" type="number" class="input w-28" />
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div v-for="s in statuses" :key="s" class="card">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 capitalize">{{ s }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900">{{ report.by_status[s]?.requests ?? 0 }}</p>
                <p class="text-xs text-gray-500">{{ report.by_status[s]?.days ?? 0 }} working days</p>
            </div>
        </div>

        <div class="card p-0 overflow-x-auto mb-4">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Leave type</th>
                        <th class="table-th text-right">Entitled</th>
                        <th class="table-th text-right">Used</th>
                        <th class="table-th text-right">Pending</th>
                        <th class="table-th text-right">Remaining</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="t in report.by_type" :key="t.leave_type">
                        <td class="table-td font-medium">{{ t.leave_type }}</td>
                        <td class="table-td text-right">{{ t.entitled_days }}</td>
                        <td class="table-td text-right">{{ t.used_days }}</td>
                        <td class="table-td text-right">{{ t.pending_days }}</td>
                        <td class="table-td text-right">{{ t.remaining_days }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card p-0 overflow-x-auto">
            <div class="p-4 border-b border-gray-100">
                <input v-model="search" class="input max-w-xs" placeholder="Filter by person or leave type…" />
            </div>
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Person</th>
                        <th class="table-th">Leave type</th>
                        <th class="table-th text-right">Entitled</th>
                        <th class="table-th text-right">Used</th>
                        <th class="table-th text-right">Pending</th>
                        <th class="table-th text-right">Remaining</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in rows" :key="`${r.user.id}-${r.leave_type.id}`" class="hover:bg-gray-50">
                        <td class="table-td font-medium">{{ r.user.name }}</td>
                        <td class="table-td">{{ r.leave_type.name }}</td>
                        <td class="table-td text-right">{{ r.entitled_days }}</td>
                        <td class="table-td text-right">{{ r.used_days }}</td>
                        <td class="table-td text-right">{{ r.pending_days }}</td>
                        <td class="table-td text-right">{{ r.remaining_days }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="6" class="table-td text-center text-gray-400 py-8">Nothing to show.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
