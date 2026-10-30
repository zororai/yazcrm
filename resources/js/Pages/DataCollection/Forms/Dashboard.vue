<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormTabs from '@/Components/DataCollection/FormTabs.vue';
import FilterBar from '@/Components/DataCollection/FilterBar.vue';
import { STATUS_LABELS, formatDateTime, formatDuration } from '@/Components/DataCollection/answers';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, BarElement, CategoryScale, LinearScale, Tooltip } from 'chart.js';

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip);

const props = defineProps({
    form: Object, can: Object, fields: Array, repeats: Array, enumerators: Array, filters: Object, statuses: Array,
    dashboard: Object,
});

const d = computed(() => props.dashboard);
const perDayChart = computed(() => ({
    labels: d.value.per_day.map(r => r.day),
    datasets: [{ data: d.value.per_day.map(r => r.count), backgroundColor: '#2a78d6' }],
}));
const perEnumeratorChart = computed(() => ({
    labels: d.value.per_enumerator.slice(0, 15).map(r => r.name),
    datasets: [{ data: d.value.per_enumerator.slice(0, 15).map(r => r.count), backgroundColor: '#1baf7a' }],
}));
const options = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } };
const horizontal = { ...options, indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } };
</script>

<template>
    <AppLayout>
        <template #title>{{ form.name }}: dashboard</template>

        <FormTabs :form="form" :can="can" active="dashboard" :filters="filters" />
        <FilterBar :filters="filters" :fields="fields" :enumerators="enumerators" :statuses="statuses" />

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-4">
            <div class="card">
                <p class="text-xs text-gray-500 uppercase">Submissions</p>
                <p class="text-2xl font-semibold">{{ d.total.toLocaleString() }}</p>
            </div>
            <div class="card">
                <p class="text-xs text-gray-500 uppercase">Completion rate</p>
                <p class="text-2xl font-semibold">{{ d.completion_rate != null ? `${d.completion_rate}%` : '—' }}</p>
                <p class="text-xs text-gray-400">Visible questions answered</p>
            </div>
            <div class="card">
                <p class="text-xs text-gray-500 uppercase">Avg time to complete</p>
                <p class="text-2xl font-semibold">{{ formatDuration(d.avg_duration_sec) }}</p>
                <p class="text-xs text-gray-400">{{ d.timed ? `from ${d.timed} timed submissions` : 'The app does not send start times yet' }}</p>
            </div>
            <div class="card">
                <p class="text-xs text-gray-500 uppercase">Rejected on upload</p>
                <p class="text-2xl font-semibold" :class="d.rejected ? 'text-red-600' : ''">{{ d.rejected.toLocaleString() }}</p>
            </div>
            <div class="card">
                <p class="text-xs text-gray-500 uppercase mb-1">Validation</p>
                <p v-for="s in statuses" :key="s" class="text-xs flex justify-between">
                    <span>{{ STATUS_LABELS[s] }}</span><span class="font-medium">{{ d.by_status[s] ?? 0 }}</span>
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="card">
                <h3 class="font-semibold text-gray-900 mb-2">Submissions per day</h3>
                <div class="h-64"><Bar :data="perDayChart" :options="options" /></div>
            </div>
            <div class="card">
                <h3 class="font-semibold text-gray-900 mb-2">Per enumerator</h3>
                <div class="h-64"><Bar :data="perEnumeratorChart" :options="horizontal" /></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="card p-0 overflow-hidden">
                <h3 class="font-semibold text-gray-900 px-6 pt-5 pb-3">Enumerators</h3>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50"><tr><th class="table-th">Name</th><th class="table-th text-right">Submissions</th><th class="table-th text-right">Avg completion</th></tr></thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="e in d.per_enumerator" :key="e.user_id">
                            <td class="table-td">{{ e.name }}</td>
                            <td class="table-td text-right">{{ e.count }}</td>
                            <td class="table-td text-right">{{ e.completion != null ? `${e.completion}%` : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card p-0 overflow-hidden">
                <h3 class="font-semibold text-gray-900 px-6 pt-5 pb-3">Latest rejected uploads</h3>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50"><tr><th class="table-th">When</th><th class="table-th">Who</th><th class="table-th">Reason</th></tr></thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="r in d.recent_rejections" :key="r.id">
                            <td class="table-td text-xs whitespace-nowrap">{{ formatDateTime(r.created_at) }}</td>
                            <td class="table-td">{{ r.user?.name ?? '—' }}</td>
                            <td class="table-td">
                                {{ r.reason }}
                                <p v-for="(e, i) in (r.errors ?? []).slice(0, 3)" :key="i" class="text-xs text-gray-500">{{ e.field }}: {{ e.message }}</p>
                            </td>
                        </tr>
                        <tr v-if="!d.recent_rejections.length"><td colspan="3" class="table-td text-center text-gray-400 py-6">None.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
