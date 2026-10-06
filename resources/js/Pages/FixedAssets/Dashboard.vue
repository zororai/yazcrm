<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Bar, Line } from 'vue-chartjs';
import {
    Chart as ChartJS, CategoryScale, LinearScale, PointElement,
    LineElement, BarElement, Tooltip, Legend,
} from 'chart.js';
import { ArrowDownTrayIcon, ExclamationTriangleIcon, ClockIcon } from '@heroicons/vue/24/outline';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, Tooltip, Legend);

const props = defineProps({
    summary: Object,
    byCategory: Array,
    byStatus: Array,
    projection: Array,
    upcomingRevaluations: Array,
    recentRevaluations: Array,
    categories: Array,
    departments: Array,
    filters: Object,
});

// ── Filters ──────────────────────────────────────────────────────────────────
const filters = ref({
    category_id: props.filters?.category_id ?? '',
    department_id: props.filters?.department_id ?? '',
});
const filterQuery = computed(() => {
    const q = {};
    if (filters.value.category_id) q.category_id = filters.value.category_id;
    if (filters.value.department_id) q.department_id = filters.value.department_id;
    return q;
});
watch(filters, () => {
    router.get('/fixed-assets/dashboard', filterQuery.value, { preserveState: true, preserveScroll: true, replace: true });
}, { deep: true });
const pdfHref = computed(() => {
    const qs = new URLSearchParams(filterQuery.value).toString();
    return `/fixed-assets/dashboard/export/pdf${qs ? `?${qs}` : ''}`;
});

// ── Formatting ───────────────────────────────────────────────────────────────
function money(v) {
    if (v === null || v === undefined) return '—';
    return Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function compact(v) {
    return Number(v).toLocaleString(undefined, { notation: 'compact', maximumFractionDigits: 1 });
}
function label(status) {
    return status.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase());
}
function signed(v) {
    return `${v > 0 ? '+' : v < 0 ? '−' : ''}${money(Math.abs(v))}`;
}

// ── Chart theme ──────────────────────────────────────────────────────────────
// Series colours: validated categorical slots 1–2 (blue, orange), stepped
// separately for light and dark surfaces. The layout stores the theme choice.
const isLight = (() => { try { return localStorage.getItem('theme') === 'light'; } catch { return false; } })();
const c = isLight
    ? { s1: '#2a78d6', s2: '#eb6834', text: '#52514e', grid: '#e5e7eb', surface: '#ffffff' }
    : { s1: '#3987e5', s2: '#d95926', text: '#c3c2b7', grid: '#30363d', surface: '#111827' };

const tooltip = {
    backgroundColor: '#1f2937', titleColor: '#f9fafb', bodyColor: '#d1d5db', padding: 10,
    callbacks: { label: ctx => `${ctx.dataset.label}: ${money(ctx.parsed.x ?? ctx.parsed.y)}` },
};
const axis = (extra = {}) => ({
    ticks: { color: c.text, font: { size: 11 } },
    grid: { color: c.grid, drawTicks: false },
    border: { display: false },
    ...extra,
});

// Book value + accumulated depreciation per category (stacked → base cost).
const categoryChart = computed(() => ({
    labels: props.byCategory.map(r => r.name),
    datasets: [
        {
            label: 'Book value', data: props.byCategory.map(r => r.book_value),
            backgroundColor: c.s1, borderColor: c.surface, borderWidth: { right: 2 }, borderSkipped: false,
            borderRadius: { topLeft: 4, bottomLeft: 4 }, barPercentage: 0.7,
        },
        {
            label: 'Accumulated depreciation', data: props.byCategory.map(r => r.accumulated_depreciation),
            backgroundColor: c.s2, borderRadius: { topRight: 4, bottomRight: 4 }, borderSkipped: false, barPercentage: 0.7,
        },
    ],
}));
const categoryOptions = {
    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', axis: 'y', intersect: false },
    plugins: {
        legend: { position: 'top', align: 'start', labels: { color: c.text, boxWidth: 10, boxHeight: 10, useBorderRadius: true, borderRadius: 2 } },
        tooltip,
    },
    scales: {
        x: axis({ stacked: true, ticks: { color: c.text, font: { size: 11 }, callback: v => compact(v) } }),
        y: axis({ stacked: true, grid: { display: false } }),
    },
};

// Projected total book value, today and each of the next five years.
const projectionChart = computed(() => ({
    labels: props.projection.map(p => p.year),
    datasets: [{
        label: 'Book value', data: props.projection.map(p => p.book_value),
        borderColor: c.s1, backgroundColor: c.s1, borderWidth: 2, tension: 0,
        pointRadius: 4, pointHoverRadius: 6, pointBorderColor: c.surface, pointBorderWidth: 2,
    }],
}));
const projectionOptions = {
    responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { display: false }, tooltip },
    scales: {
        x: axis({ grid: { display: false } }),
        y: axis({ beginAtZero: true, ticks: { color: c.text, font: { size: 11 }, callback: v => compact(v) } }),
    },
};

// Asset count by status (single series).
const statusChart = computed(() => ({
    labels: props.byStatus.map(s => label(s.status)),
    datasets: [{
        label: 'Assets', data: props.byStatus.map(s => s.count),
        backgroundColor: c.s1, borderRadius: 4, borderSkipped: 'start', barPercentage: 0.7,
    }],
}));
const statusOptions = {
    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { ...tooltip, callbacks: { label: ctx => `${ctx.parsed.x} assets` } } },
    scales: {
        x: axis({ beginAtZero: true, ticks: { color: c.text, font: { size: 11 }, precision: 0 } }),
        y: axis({ grid: { display: false } }),
    },
};

const depreciatedPct = computed(() => props.summary.cost > 0
    ? Math.round((props.summary.accumulated_depreciation / props.summary.cost) * 100) : 0);
</script>

<template>
    <AppLayout>
        <template #title>Asset Dashboard</template>
        <template #header-actions>
            <a :href="pdfHref" class="btn-secondary btn-sm inline-flex items-center gap-1">
                <ArrowDownTrayIcon class="h-4 w-4" /> Export PDF
            </a>
        </template>

        <!-- Filters -->
        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="label">Category</label>
                <select v-model="filters.category_id" class="input">
                    <option value="">All categories</option>
                    <option v-for="cat in categories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
                </select>
            </div>
            <div>
                <label class="label">Department</label>
                <select v-model="filters.department_id" class="input">
                    <option value="">All departments</option>
                    <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                </select>
            </div>
            <p class="text-xs text-gray-400 pb-2">Values exclude disposed assets ({{ summary.disposed_count }}).</p>
        </div>

        <!-- Headline figures -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="card">
                <div class="text-sm text-gray-500">Assets on books</div>
                <div class="text-2xl font-semibold mt-1">{{ summary.asset_count.toLocaleString() }}</div>
                <Link href="/fixed-assets" class="text-xs text-brand-600 hover:underline">View register →</Link>
            </div>
            <div class="card">
                <div class="text-sm text-gray-500">Net book value</div>
                <div class="text-2xl font-semibold mt-1">{{ money(summary.book_value) }}</div>
                <div class="text-xs text-gray-400">of {{ money(summary.cost) }} cost / revalued amount</div>
            </div>
            <div class="card">
                <div class="text-sm text-gray-500">Accumulated depreciation</div>
                <div class="text-2xl font-semibold mt-1">{{ money(summary.accumulated_depreciation) }}</div>
                <div class="text-xs text-gray-400">{{ depreciatedPct }}% depreciated · {{ money(summary.annual_depreciation) }} / year</div>
                <Link href="/fixed-assets/depreciation-report" class="text-xs text-brand-600 hover:underline">Depreciation report →</Link>
            </div>
            <div class="card">
                <div class="text-sm text-gray-500">Revaluations</div>
                <div class="text-2xl font-semibold mt-1 flex items-center gap-2">
                    {{ summary.revaluations_overdue }}
                    <span v-if="summary.revaluations_overdue" class="badge bg-red-100 text-red-800 inline-flex items-center gap-1 text-xs">
                        <ExclamationTriangleIcon class="h-3.5 w-3.5" /> Overdue
                    </span>
                    <span v-else class="text-sm font-normal text-gray-400">overdue</span>
                </div>
                <div class="text-xs text-gray-400">{{ summary.revaluations_due_soon }} due in 90 days · {{ summary.revaluation_count_12m }} done in 12 months ({{ signed(summary.revaluation_change_12m) }})</div>
                <Link href="/fixed-assets/revaluations" class="text-xs text-brand-600 hover:underline">Revaluations →</Link>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <div class="card lg:col-span-2">
                <h3 class="font-semibold text-gray-900">Value by category</h3>
                <p class="text-xs text-gray-400 mb-3">Book value plus accumulated depreciation makes up each category's cost / revalued amount.</p>
                <div v-if="byCategory.length" :style="{ height: `${Math.max(180, byCategory.length * 40 + 60)}px` }">
                    <Bar :data="categoryChart" :options="categoryOptions" />
                </div>
                <p v-else class="text-sm text-gray-400 py-8 text-center">No assets match these filters.</p>
            </div>
            <div class="card">
                <h3 class="font-semibold text-gray-900">Assets by status</h3>
                <p class="text-xs text-gray-400 mb-3">Includes disposed assets.</p>
                <div v-if="byStatus.length" :style="{ height: `${Math.max(180, byStatus.length * 34 + 40)}px` }">
                    <Bar :data="statusChart" :options="statusOptions" />
                </div>
                <p v-else class="text-sm text-gray-400 py-8 text-center">No assets.</p>
            </div>
        </div>

        <div class="card mb-4">
            <h3 class="font-semibold text-gray-900">Projected net book value</h3>
            <p class="text-xs text-gray-400 mb-3">Straight-line depreciation on assets currently on the books, assuming no new purchases, disposals or revaluations.</p>
            <div class="h-56">
                <Line :data="projectionChart" :options="projectionOptions" />
            </div>
        </div>

        <!-- Category table (also the accessible view of the category chart) -->
        <div class="card p-0 overflow-x-auto mb-4">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Category</th>
                        <th class="table-th text-right">Assets</th>
                        <th class="table-th text-right">Cost / Revalued</th>
                        <th class="table-th text-right">Accumulated Dep.</th>
                        <th class="table-th text-right">Book Value</th>
                        <th class="table-th text-right">Annual Dep.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in byCategory" :key="r.name">
                        <td class="table-td font-medium">{{ r.name }}</td>
                        <td class="table-td text-right">{{ r.count }}</td>
                        <td class="table-td text-right">{{ money(r.cost) }}</td>
                        <td class="table-td text-right">{{ money(r.accumulated_depreciation) }}</td>
                        <td class="table-td text-right font-medium">{{ money(r.book_value) }}</td>
                        <td class="table-td text-right">{{ money(r.annual_depreciation) }}</td>
                    </tr>
                    <tr v-if="!byCategory.length">
                        <td colspan="6" class="table-td text-center text-gray-400 py-6">No assets match these filters.</td>
                    </tr>
                </tbody>
                <tfoot v-if="byCategory.length" class="border-t border-gray-100">
                    <tr>
                        <td class="table-td font-semibold">Total</td>
                        <td class="table-td text-right font-semibold">{{ summary.asset_count }}</td>
                        <td class="table-td text-right font-semibold">{{ money(summary.cost) }}</td>
                        <td class="table-td text-right font-semibold">{{ money(summary.accumulated_depreciation) }}</td>
                        <td class="table-td text-right font-semibold">{{ money(summary.book_value) }}</td>
                        <td class="table-td text-right font-semibold">{{ money(summary.annual_depreciation) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="card p-0 overflow-x-auto">
                <h3 class="font-semibold text-gray-900 px-4 pt-4">Revaluations due (next 90 days)</h3>
                <p class="text-xs text-gray-400 px-4 pb-2">
                    <template v-if="summary.revaluations_overdue + summary.revaluations_due_soon > upcomingRevaluations.length">
                        Showing the {{ upcomingRevaluations.length }} most overdue of {{ summary.revaluations_overdue + summary.revaluations_due_soon }} —
                        <Link href="/fixed-assets/revaluations?due_only=1" class="text-brand-600 hover:underline">see all</Link>
                    </template>
                </p>
                <table class="w-full">
                    <thead class="bg-gray-50 border-y border-gray-100">
                        <tr>
                            <th class="table-th">Asset</th>
                            <th class="table-th">Due</th>
                            <th class="table-th text-right">Book Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="r in upcomingRevaluations" :key="r.id" class="hover:bg-gray-50 cursor-pointer" @click="router.get(`/fixed-assets/${r.id}`)">
                            <td class="table-td">
                                <div class="font-medium">{{ r.name }}</div>
                                <div class="text-xs text-gray-400">{{ r.asset_number }} · {{ r.category ?? 'Uncategorised' }}</div>
                            </td>
                            <td class="table-td whitespace-nowrap">
                                <span v-if="r.overdue" class="badge bg-red-100 text-red-800 inline-flex items-center gap-1">
                                    <ExclamationTriangleIcon class="h-3.5 w-3.5" /> Overdue · {{ r.due }}
                                </span>
                                <span v-else class="badge bg-amber-100 text-amber-800 inline-flex items-center gap-1">
                                    <ClockIcon class="h-3.5 w-3.5" /> {{ r.due }}
                                </span>
                            </td>
                            <td class="table-td text-right">{{ money(r.book_value) }}</td>
                        </tr>
                        <tr v-if="!upcomingRevaluations.length">
                            <td colspan="3" class="table-td text-center text-gray-400 py-6">Nothing due in the next 90 days.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card p-0 overflow-x-auto">
                <h3 class="font-semibold text-gray-900 px-4 pt-4 pb-2">Recent revaluations (last 12 months)</h3>
                <table class="w-full">
                    <thead class="bg-gray-50 border-y border-gray-100">
                        <tr>
                            <th class="table-th">Date</th>
                            <th class="table-th">Asset</th>
                            <th class="table-th text-right">Previous</th>
                            <th class="table-th text-right">Revalued</th>
                            <th class="table-th text-right">Change</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="(r, i) in recentRevaluations" :key="i">
                            <td class="table-td whitespace-nowrap">{{ r.date }}</td>
                            <td class="table-td">
                                <div class="font-medium">{{ r.name }}</div>
                                <div class="text-xs text-gray-400">{{ r.asset_number }}<span v-if="r.by"> · by {{ r.by }}</span></div>
                            </td>
                            <td class="table-td text-right">{{ money(r.previous_value) }}</td>
                            <td class="table-td text-right">{{ money(r.revalued_amount) }}</td>
                            <td :class="['table-td text-right font-medium', r.change < 0 ? 'text-red-600' : 'text-green-700']">{{ signed(r.change) }}</td>
                        </tr>
                        <tr v-if="!recentRevaluations.length">
                            <td colspan="5" class="table-td text-center text-gray-400 py-6">No revaluations in the last 12 months.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
