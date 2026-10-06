<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Bar, Line } from 'vue-chartjs';
import {
    Chart as ChartJS, CategoryScale, LinearScale, PointElement,
    LineElement, BarElement, Tooltip, Legend,
} from 'chart.js';
import { ArrowDownTrayIcon, ExclamationTriangleIcon, XCircleIcon } from '@heroicons/vue/24/outline';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Legend);

const props = defineProps({
    period: Object,
    summary: Object,
    trend: Array,
    topItems: Array,
    byDepartment: Array,
    byStore: Array,
    reorder: Array,
    reorderTotal: Number,
    recentIssues: Array,
    stores: Array,
    departments: Array,
    filters: Object,
});

// ── Filters ──────────────────────────────────────────────────────────────────
const periods = [{ key: '30d', label: 'Last 30 days' }, { key: '90d', label: 'Last 90 days' }, { key: '12m', label: 'Last 12 months' }];
const filters = ref({
    period: props.filters?.period ?? '90d',
    store_id: props.filters?.store_id ? String(props.filters.store_id) : '',
    department_id: props.filters?.department_id ? String(props.filters.department_id) : '',
});
const filterQuery = computed(() => Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v)));
watch(filters, () => {
    router.get('/inventory/dashboard', filterQuery.value, { preserveState: true, preserveScroll: true, replace: true });
}, { deep: true });
const pdfHref = computed(() => `/inventory/dashboard/export/pdf?${new URLSearchParams(filterQuery.value)}`);

const num = v => Number(v ?? 0).toLocaleString();
const periodLabel = computed(() => periods.find(p => p.key === props.period.key)?.label ?? '');

// ── Chart theme ──────────────────────────────────────────────────────────────
// Validated categorical slots 1–2 (blue = issued, orange = received), stepped
// for light/dark surfaces. Theme comes from the layout's stored choice.
const isLight = (() => { try { return localStorage.getItem('theme') === 'light'; } catch { return false; } })();
const c = isLight
    ? { s1: '#2a78d6', s2: '#eb6834', text: '#52514e', grid: '#e5e7eb', surface: '#ffffff' }
    : { s1: '#3987e5', s2: '#d95926', text: '#c3c2b7', grid: '#30363d', surface: '#111827' };

const tooltip = {
    backgroundColor: '#1f2937', titleColor: '#f9fafb', bodyColor: '#d1d5db', padding: 10,
    callbacks: { label: ctx => `${ctx.dataset.label}: ${num(ctx.parsed.x ?? ctx.parsed.y)} units` },
};
const axis = (extra = {}) => ({
    ticks: { color: c.text, font: { size: 11 }, precision: 0 },
    grid: { color: c.grid, drawTicks: false },
    border: { display: false },
    ...extra,
});

const trendChart = computed(() => ({
    labels: props.trend.map(p => p.label),
    datasets: [
        { label: 'Issued', data: props.trend.map(p => p.issued), borderColor: c.s1, backgroundColor: c.s1,
          borderWidth: 2, tension: 0, pointRadius: 4, pointHoverRadius: 6, pointBorderColor: c.surface, pointBorderWidth: 2 },
        ...(props.summary.receipts_counted ? [{
          label: 'Received', data: props.trend.map(p => p.received), borderColor: c.s2, backgroundColor: c.s2,
          borderWidth: 2, tension: 0, pointRadius: 4, pointHoverRadius: 6, pointBorderColor: c.surface, pointBorderWidth: 2 }] : []),
    ],
}));
const trendOptions = {
    responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { position: 'top', align: 'start', labels: { color: c.text, boxWidth: 10, boxHeight: 10, useBorderRadius: true, borderRadius: 2 } },
        tooltip,
    },
    scales: { x: axis({ grid: { display: false } }), y: axis({ beginAtZero: true }) },
};

const barOptions = {
    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip },
    scales: { x: axis({ beginAtZero: true }), y: axis({ grid: { display: false } }) },
};
const singleBar = rows => ({
    labels: rows.map(r => r.name),
    datasets: [{ label: 'Issued', data: rows.map(r => r.quantity), backgroundColor: c.s1, borderRadius: 4, borderSkipped: 'start', barPercentage: 0.7 }],
});
const topItemsChart = computed(() => singleBar(props.topItems));
const departmentChart = computed(() => singleBar(props.byDepartment));
const barHeight = n => `${Math.max(160, n * 34 + 40)}px`;
</script>

<template>
    <AppLayout>
        <template #title>Inventory Dashboard</template>
        <template #header-actions>
            <a :href="pdfHref" class="btn-secondary btn-sm inline-flex items-center gap-1">
                <ArrowDownTrayIcon class="h-4 w-4" /> Export PDF
            </a>
        </template>

        <!-- Filters -->
        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="label">Period</label>
                <select v-model="filters.period" class="input">
                    <option v-for="p in periods" :key="p.key" :value="p.key">{{ p.label }}</option>
                </select>
            </div>
            <div>
                <label class="label">Store</label>
                <select v-model="filters.store_id" class="input">
                    <option value="">All stores</option>
                    <option v-for="s in stores" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                </select>
            </div>
            <div>
                <label class="label">Department (issues)</label>
                <select v-model="filters.department_id" class="input">
                    <option value="">All departments</option>
                    <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                </select>
            </div>
            <p class="text-xs text-gray-400 pb-2">{{ period.from }} – {{ period.to }} · quantities in each item's own unit</p>
        </div>

        <!-- Headline figures -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="card">
                <div class="text-sm text-gray-500">Items issued</div>
                <div class="text-2xl font-semibold mt-1">{{ num(summary.units_issued) }} <span class="text-sm font-normal text-gray-400">units</span></div>
                <div class="text-xs text-gray-400">{{ num(summary.issues) }} issue{{ summary.issues === 1 ? '' : 's' }} · {{ periodLabel.toLowerCase() }}</div>
            </div>
            <div class="card">
                <div class="text-sm text-gray-500">Items received</div>
                <div class="text-2xl font-semibold mt-1">
                    <template v-if="summary.receipts_counted">{{ num(summary.units_received) }} <span class="text-sm font-normal text-gray-400">units</span></template>
                    <span v-else class="text-base font-normal text-gray-400">n/a</span>
                </div>
                <div class="text-xs text-gray-400">{{ summary.receipts_counted ? periodLabel.toLowerCase() : 'Receipts have no department' }}</div>
            </div>
            <div class="card">
                <div class="text-sm text-gray-500">Stock on hand</div>
                <div class="text-2xl font-semibold mt-1">{{ num(summary.units_on_hand) }} <span class="text-sm font-normal text-gray-400">units</span></div>
                <div class="text-xs text-gray-400">{{ num(summary.active_items) }} active items · {{ num(summary.units_reserved) }} reserved · {{ summary.in_transit }} transfer{{ summary.in_transit === 1 ? '' : 's' }} in transit</div>
            </div>
            <div class="card">
                <div class="text-sm text-gray-500">Needs reordering</div>
                <div class="text-2xl font-semibold mt-1 flex items-center gap-2">
                    {{ summary.low_stock + summary.out_of_stock }}
                    <span v-if="summary.out_of_stock" class="badge bg-red-100 text-red-800 inline-flex items-center gap-1 text-xs">
                        <XCircleIcon class="h-3.5 w-3.5" /> {{ summary.out_of_stock }} out
                    </span>
                </div>
                <div class="text-xs text-gray-400">{{ summary.low_stock }} at or below reorder level · {{ summary.out_of_stock }} out of stock</div>
            </div>
        </div>

        <!-- Trend -->
        <div class="card mb-4">
            <h3 class="font-semibold text-gray-900">Issued vs received</h3>
            <p class="text-xs text-gray-400 mb-3">Units per {{ period.key === '12m' ? 'month' : 'week' }}.</p>
            <div class="h-64">
                <Line :data="trendChart" :options="trendOptions" />
            </div>
        </div>

        <!-- Top items & departments -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="card">
                <h3 class="font-semibold text-gray-900">Most issued items</h3>
                <p class="text-xs text-gray-400 mb-3">Top 10 by units issued.</p>
                <div v-if="topItems.length" :style="{ height: barHeight(topItems.length) }">
                    <Bar :data="topItemsChart" :options="barOptions" />
                </div>
                <p v-else class="text-sm text-gray-400 py-8 text-center">Nothing issued in this period.</p>
            </div>
            <div class="card">
                <h3 class="font-semibold text-gray-900">Issued by department</h3>
                <p class="text-xs text-gray-400 mb-3">Units issued to each department.</p>
                <div v-if="byDepartment.length" :style="{ height: barHeight(byDepartment.length) }">
                    <Bar :data="departmentChart" :options="barOptions" />
                </div>
                <p v-else class="text-sm text-gray-400 py-8 text-center">Nothing issued in this period.</p>
            </div>
        </div>

        <!-- Reorder list -->
        <div class="card p-0 overflow-x-auto mb-4">
            <div class="px-4 pt-4 pb-2">
                <h3 class="font-semibold text-gray-900">Items to reorder</h3>
                <p v-if="reorderTotal > reorder.length" class="text-xs text-gray-400">Showing {{ reorder.length }} of {{ reorderTotal }}, out of stock first.</p>
            </div>
            <table class="w-full">
                <thead class="bg-gray-50 border-y border-gray-100">
                    <tr>
                        <th class="table-th">Item</th>
                        <th class="table-th">Status</th>
                        <th class="table-th text-right">Available</th>
                        <th class="table-th text-right">Reorder Level</th>
                        <th class="table-th text-right">Minimum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in reorder" :key="r.id" class="hover:bg-gray-50 cursor-pointer" @click="router.get(`/items/${r.id}`)">
                        <td class="table-td">
                            <div class="font-medium">{{ r.name }}</div>
                            <div class="text-xs text-gray-400">{{ r.category ?? 'Uncategorised' }}</div>
                        </td>
                        <td class="table-td whitespace-nowrap">
                            <span v-if="r.status === 'out'" class="badge bg-red-100 text-red-800 inline-flex items-center gap-1">
                                <XCircleIcon class="h-3.5 w-3.5" /> Out of stock
                            </span>
                            <span v-else class="badge bg-amber-100 text-amber-800 inline-flex items-center gap-1">
                                <ExclamationTriangleIcon class="h-3.5 w-3.5" /> Low
                            </span>
                        </td>
                        <td class="table-td text-right font-medium">{{ num(r.available) }} <span class="text-xs text-gray-400">{{ r.unit }}</span></td>
                        <td class="table-td text-right">{{ num(r.reorder_level) }}</td>
                        <td class="table-td text-right">{{ num(r.minimum_stock) }}</td>
                    </tr>
                    <tr v-if="!reorder.length">
                        <td colspan="5" class="table-td text-center text-gray-400 py-6">No items at or below their reorder level.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Recent issues -->
            <div class="card p-0 overflow-x-auto lg:col-span-2">
                <h3 class="font-semibold text-gray-900 px-4 pt-4 pb-2">Recent issues</h3>
                <table class="w-full">
                    <thead class="bg-gray-50 border-y border-gray-100">
                        <tr>
                            <th class="table-th">Issue #</th>
                            <th class="table-th">Date</th>
                            <th class="table-th">Store</th>
                            <th class="table-th">Issued To</th>
                            <th class="table-th text-right">Units</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="i in recentIssues" :key="i.id">
                            <td class="table-td font-medium whitespace-nowrap">{{ i.number }}</td>
                            <td class="table-td whitespace-nowrap">{{ i.date }}</td>
                            <td class="table-td">{{ i.store ?? '—' }}</td>
                            <td class="table-td">
                                <div>{{ i.issued_to || '—' }}</div>
                                <div class="text-xs text-gray-400">{{ i.department ?? 'No department' }}<span v-if="i.issued_by"> · by {{ i.issued_by }}</span></div>
                            </td>
                            <td class="table-td text-right">{{ num(i.units) }} <span class="text-xs text-gray-400">({{ i.lines }} line{{ i.lines === 1 ? '' : 's' }})</span></td>
                        </tr>
                        <tr v-if="!recentIssues.length">
                            <td colspan="5" class="table-td text-center text-gray-400 py-6">No issues in this period.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Stock by store -->
            <div class="card p-0 overflow-x-auto">
                <h3 class="font-semibold text-gray-900 px-4 pt-4 pb-2">Stock by store</h3>
                <table class="w-full">
                    <thead class="bg-gray-50 border-y border-gray-100">
                        <tr>
                            <th class="table-th">Store</th>
                            <th class="table-th text-right">Items</th>
                            <th class="table-th text-right">Units</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="s in byStore" :key="s.name">
                            <td class="table-td font-medium">{{ s.name }}</td>
                            <td class="table-td text-right">{{ num(s.items) }}</td>
                            <td class="table-td text-right">{{ num(s.quantity) }}</td>
                        </tr>
                        <tr v-if="!byStore.length">
                            <td colspan="3" class="table-td text-center text-gray-400 py-6">No stock recorded.</td>
                        </tr>
                    </tbody>
                </table>
                <div class="px-4 py-3">
                    <Link href="/stores" class="text-xs text-brand-600 hover:underline">All stores →</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
