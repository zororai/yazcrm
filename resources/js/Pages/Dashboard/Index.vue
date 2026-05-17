<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Line, Bar } from 'vue-chartjs';
import {
    Chart as ChartJS, CategoryScale, LinearScale, PointElement,
    LineElement, BarElement, Title, Tooltip, Legend, Filler,
} from 'chart.js';
import {
    PhoneIcon, PhoneArrowDownLeftIcon, PhoneArrowUpRightIcon,
    PhoneXMarkIcon, UserGroupIcon, TicketIcon, QueueListIcon,
} from '@heroicons/vue/24/outline';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Title, Tooltip, Legend, Filler);

const props = defineProps({
    stats: Object,
    callTrend: Array,
    topExtensions: Array,
    period: String,
});

const period = ref(props.period);

function changePeriod(p) {
    period.value = p;
    router.get('/dashboard', { period: p }, { preserveState: true, replace: true });
}

const statCards = computed(() => [
    { label: 'Total Calls',     value: props.stats.total_calls,      icon: PhoneIcon,              color: 'bg-blue-50 text-blue-700',   border: 'border-blue-200' },
    { label: 'Inbound',         value: props.stats.inbound_calls,    icon: PhoneArrowDownLeftIcon,  color: 'bg-green-50 text-green-700', border: 'border-green-200' },
    { label: 'Outbound',        value: props.stats.outbound_calls,   icon: PhoneArrowUpRightIcon,   color: 'bg-indigo-50 text-indigo-700', border: 'border-indigo-200' },
    { label: 'Missed',          value: props.stats.missed_calls,     icon: PhoneXMarkIcon,          color: 'bg-red-50 text-red-700',     border: 'border-red-200' },
    { label: 'Active Clients',  value: props.stats.active_clients,   icon: UserGroupIcon,           color: 'bg-purple-50 text-purple-700', border: 'border-purple-200' },
    { label: 'Open Tickets',    value: props.stats.open_tickets,     icon: TicketIcon,              color: 'bg-yellow-50 text-yellow-700', border: 'border-yellow-200' },
    { label: 'Pending Callbacks', value: props.stats.callback_pending, icon: QueueListIcon,         color: 'bg-orange-50 text-orange-700', border: 'border-orange-200' },
    { label: 'Avg Duration (s)', value: props.stats.avg_duration,    icon: PhoneIcon,               color: 'bg-teal-50 text-teal-700',   border: 'border-teal-200' },
]);

const trendChartData = computed(() => ({
    labels: props.callTrend.map(d => d.date),
    datasets: [
        {
            label: 'Total',
            data: props.callTrend.map(d => d.total),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.08)',
            fill: true,
            tension: 0.3,
        },
        {
            label: 'Missed',
            data: props.callTrend.map(d => d.missed),
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239,68,68,0.08)',
            fill: true,
            tension: 0.3,
        },
    ],
}));

const trendOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'top' } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
};

const extChartData = computed(() => ({
    labels: props.topExtensions.map(e => e.extension_number),
    datasets: [{
        label: 'Calls',
        data: props.topExtensions.map(e => e.total),
        backgroundColor: '#6366f1',
        borderRadius: 4,
    }],
}));

const extOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
};
</script>

<template>
    <AppLayout>
        <template #title>Dashboard</template>
        <template #header-actions>
            <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
                <button
                    v-for="p in ['today', 'week', 'month']"
                    :key="p"
                    @click="changePeriod(p)"
                    :class="['px-3 py-1 rounded-md text-xs font-medium capitalize transition-colors',
                             period === p ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700']"
                >
                    {{ p }}
                </button>
            </div>
        </template>

        <!-- Stats grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div
                v-for="s in statCards"
                :key="s.label"
                :class="['card flex items-center gap-4 p-4 border', s.border]"
            >
                <div :class="['p-2 rounded-lg', s.color]">
                    <component :is="s.icon" class="h-5 w-5" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ s.value }}</p>
                    <p class="text-xs text-gray-500">{{ s.label }}</p>
                </div>
            </div>
        </div>

        <!-- Charts row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="card lg:col-span-2">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Call Trend (7 days)</h3>
                <div class="h-56">
                    <Line :data="trendChartData" :options="trendOptions" />
                </div>
            </div>
            <div class="card">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Top Extensions (30 days)</h3>
                <div class="h-56">
                    <Bar :data="extChartData" :options="extOptions" />
                </div>
            </div>
        </div>

        <!-- Active calls -->
        <div v-if="stats.active_calls?.length" class="card mt-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Active Calls Now</h3>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="call in stats.active_calls"
                    :key="call.id"
                    class="badge bg-green-100 text-green-800"
                >
                    <span class="mr-1 h-2 w-2 rounded-full bg-green-500 animate-pulse inline-block" />
                    {{ call.caller }} → {{ call.callee }}
                </span>
            </div>
        </div>
    </AppLayout>
</template>
