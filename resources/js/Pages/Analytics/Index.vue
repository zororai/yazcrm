<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS, CategoryScale, LinearScale, PointElement,
    LineElement, Title, Tooltip, Legend, Filler,
} from 'chart.js';
import { PhoneIcon, PhoneXMarkIcon, TicketIcon } from '@heroicons/vue/24/outline';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend, Filler);

const props = defineProps({
    overview:         Object,
    callTrend:        Array,
    agentPerformance: Array,
    days:             Number,
});

const selectedDays = ref(props.days);

function changeDays(d) {
    selectedDays.value = d;
    router.get('/analytics', { days: d }, { preserveState: true, replace: true });
}

const trendData = {
    labels: props.callTrend.map(d => d.date),
    datasets: [
        { label: 'Total',    data: props.callTrend.map(d => d.total),    borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.08)', fill: true, tension: 0.3 },
        { label: 'Answered', data: props.callTrend.map(d => d.answered), borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.08)',   fill: true, tension: 0.3 },
        { label: 'Missed',   data: props.callTrend.map(d => d.missed),   borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.08)',   fill: true, tension: 0.3 },
    ],
};
const trendOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } };

const answerRate = props.overview.total_calls
    ? Math.round((props.overview.answered_calls / props.overview.total_calls) * 100)
    : 0;

const ticketResolutionRate = props.overview.total_tickets
    ? Math.round((props.overview.resolved_tickets / props.overview.total_tickets) * 100)
    : 0;
</script>

<template>
    <AppLayout>
        <template #title>Analytics</template>
        <template #header-actions>
            <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
                <button
                    v-for="d in [7, 30, 90]"
                    :key="d"
                    @click="changeDays(d)"
                    :class="['px-3 py-1 rounded-md text-xs font-medium transition-colors',
                             selectedDays === d ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700']"
                >
                    {{ d }}d
                </button>
            </div>
        </template>

        <!-- Overview cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <div class="card text-center p-4">
                <p class="text-2xl font-bold text-gray-900">{{ overview.total_calls }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Calls</p>
            </div>
            <div class="card text-center p-4">
                <p class="text-2xl font-bold text-green-600">{{ overview.answered_calls }}</p>
                <p class="text-xs text-gray-500 mt-1">Answered</p>
            </div>
            <div class="card text-center p-4">
                <p class="text-2xl font-bold text-red-600">{{ overview.missed_calls }}</p>
                <p class="text-xs text-gray-500 mt-1">Missed</p>
            </div>
            <div class="card text-center p-4">
                <p class="text-2xl font-bold text-brand-600">{{ answerRate }}%</p>
                <p class="text-xs text-gray-500 mt-1">Answer Rate</p>
            </div>
            <div class="card text-center p-4">
                <p class="text-2xl font-bold text-gray-900">{{ overview.avg_duration }}s</p>
                <p class="text-xs text-gray-500 mt-1">Avg Duration</p>
            </div>
            <div class="card text-center p-4">
                <p class="text-2xl font-bold text-purple-600">{{ ticketResolutionRate }}%</p>
                <p class="text-xs text-gray-500 mt-1">Ticket Resolution</p>
            </div>
        </div>

        <!-- Trend chart -->
        <div class="card mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Call Trend</h3>
            <div class="h-64">
                <Line :data="trendData" :options="trendOptions" />
            </div>
        </div>

        <!-- Agent performance -->
        <div class="card p-0 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Agent Performance</h3>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Agent</th>
                        <th class="table-th">Total Calls</th>
                        <th class="table-th">Answered</th>
                        <th class="table-th">Missed</th>
                        <th class="table-th">Answer Rate</th>
                        <th class="table-th">Open Tickets</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!agentPerformance.length">
                        <td colspan="6" class="py-8 text-center text-sm text-gray-400">No data.</td>
                    </tr>
                    <tr v-for="agent in agentPerformance" :key="agent.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium">{{ agent.name }}</td>
                        <td class="table-td">{{ agent.total_calls }}</td>
                        <td class="table-td text-green-700">{{ agent.answered_calls }}</td>
                        <td class="table-td text-red-600">{{ agent.missed_calls }}</td>
                        <td class="table-td">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-gray-200 rounded-full h-1.5">
                                    <div
                                        class="bg-brand-600 h-1.5 rounded-full"
                                        :style="{ width: agent.total_calls ? Math.round(agent.answered_calls / agent.total_calls * 100) + '%' : '0%' }"
                                    />
                                </div>
                                <span class="text-xs text-gray-600 w-8">
                                    {{ agent.total_calls ? Math.round(agent.answered_calls / agent.total_calls * 100) : 0 }}%
                                </span>
                            </div>
                        </td>
                        <td class="table-td">{{ agent.open_tickets }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
