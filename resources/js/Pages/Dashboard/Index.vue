<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Line, Bar, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS, CategoryScale, LinearScale, PointElement,
    LineElement, BarElement, Title, Tooltip, Legend, Filler,
    DoughnutController, ArcElement,
} from 'chart.js';
import {
    PhoneIcon, PhoneArrowDownLeftIcon, PhoneArrowUpRightIcon,
    PhoneXMarkIcon, UserGroupIcon, TicketIcon, QueueListIcon, ClockIcon,
    CalendarDaysIcon,
} from '@heroicons/vue/24/outline';

ChartJS.register(
    CategoryScale, LinearScale, PointElement, LineElement, BarElement,
    Title, Tooltip, Legend, Filler, DoughnutController, ArcElement,
);

const page  = usePage();
const props = defineProps({
    stats:         Object,
    prevStats:     Object,
    callTrend:     Array,
    topExtensions: Array,
    period:        String,
    targetSummary: Object,
    extension:     String,
    recentCalls:   Array,
});

const period = ref(props.period);

function changePeriod(p) {
    period.value = p;
    router.get('/dashboard', { period: p }, { preserveState: true, replace: true });
}

let refreshTimer = null;
onMounted(() => {
    refreshTimer = setInterval(() => {
        router.reload({ only: ['stats', 'prevStats', 'callTrend', 'topExtensions', 'targetSummary', 'recentCalls'] });
    }, 30000);
});
onUnmounted(() => clearInterval(refreshTimer));

// ── Helpers ──────────────────────────────────────────────────────────────────
function pct(curr, prev) {
    if (prev == null || prev === 0) return null;
    return ((curr - prev) / prev * 100).toFixed(1);
}

function sparkPath(data) {
    const arr = (data ?? []).map(Number);
    if (!arr.length) return null;
    const w = 120, h = 28;
    const allZero = arr.every(v => v === 0);
    const max = allZero ? 1 : Math.max(...arr);
    const min = allZero ? 0 : Math.min(...arr);
    const range = max - min || 1;
    const n = arr.length;
    const pts = arr.map((v, i) => {
        const x = n === 1 ? w / 2 : (i / (n - 1)) * w;
        const y = allZero ? h / 2 : h - 2 - ((v - min) / range) * (h - 4);
        return `${x.toFixed(1)},${y.toFixed(1)}`;
    });
    return 'M ' + pts.join(' L ');
}

function fmtTime(dt) {
    if (!dt) return '';
    return new Date(dt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function fmtDur(s) {
    if (!s) return '0:00';
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

const avatarColors = ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#3b82f6'];
function avatarColor(i) { return avatarColors[i % avatarColors.length]; }
function callerInitials(num) {
    if (!num) return '?';
    const d = String(num).replace(/\D/g, '');
    return d.slice(-2) || '??';
}

// ── Date range label ──────────────────────────────────────────────────────────
const dateRangeLabel = computed(() => {
    const now = new Date();
    const fmt = d => d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    if (period.value === 'week') {
        const s = new Date(now); s.setDate(now.getDate() - now.getDay());
        return `${fmt(s)} – ${fmt(now)}`;
    }
    if (period.value === 'month') {
        return `${fmt(new Date(now.getFullYear(), now.getMonth(), 1))} – ${fmt(now)}`;
    }
    return fmt(now);
});

const periodLabel = computed(() => ({ today: 'vs yesterday', week: 'vs last week', month: 'vs last month' }[period.value] || 'vs prev'));

// ── Stat cards ────────────────────────────────────────────────────────────────
const cardDefs = [
    { label: 'Total Calls',       key: 'total_calls',      color: '#6366f1', bg: 'rgba(99,102,241,0.15)',  icon: PhoneIcon,               sparkKey: 'total' },
    { label: 'Inbound',           key: 'inbound_calls',    color: '#10b981', bg: 'rgba(16,185,129,0.15)',  icon: PhoneArrowDownLeftIcon,  sparkKey: 'inbound' },
    { label: 'Outbound',          key: 'outbound_calls',   color: '#3b82f6', bg: 'rgba(59,130,246,0.15)',  icon: PhoneArrowUpRightIcon,   sparkKey: 'outbound' },
    { label: 'Missed',            key: 'missed_calls',     color: '#ef4444', bg: 'rgba(239,68,68,0.15)',   icon: PhoneXMarkIcon,          sparkKey: 'missed' },
    { label: 'Active Clients',    key: 'active_clients',   color: '#8b5cf6', bg: 'rgba(139,92,246,0.15)', icon: UserGroupIcon,            sparkKey: null },
    { label: 'Open Tickets',      key: 'open_tickets',     color: '#f59e0b', bg: 'rgba(245,158,11,0.15)', icon: TicketIcon,               sparkKey: null },
    { label: 'Pending Callbacks', key: 'callback_pending', color: '#f97316', bg: 'rgba(249,115,22,0.15)', icon: QueueListIcon,            sparkKey: null },
    { label: 'Avg Duration',      key: 'avg_duration',     color: '#14b8a6', bg: 'rgba(20,184,166,0.15)', icon: ClockIcon,                sparkKey: null, suffix: 's' },
];

const sparkSeries = computed(() => ({
    total:   props.callTrend.map(d => Number(d.total)),
    inbound: props.callTrend.map(d => Number(d.inbound)),
    outbound:props.callTrend.map(d => Number(d.outbound)),
    missed:  props.callTrend.map(d => Number(d.missed)),
}));

// ── Chart: call trend ─────────────────────────────────────────────────────────
const trendChartData = computed(() => ({
    labels: props.callTrend.map(d => d.date),
    datasets: [
        {
            label: 'Total',
            data: props.callTrend.map(d => d.total),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.08)',
            fill: true, tension: 0.4,
            pointRadius: 4, pointBackgroundColor: '#6366f1',
        },
        {
            label: 'Missed',
            data: props.callTrend.map(d => d.missed),
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239,68,68,0.08)',
            fill: true, tension: 0.4,
            pointRadius: 4, pointBackgroundColor: '#ef4444',
        },
    ],
}));

const trendOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#1f2937', titleColor: '#f9fafb', bodyColor: '#d1d5db' },
    },
    scales: {
        x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6b7280', font: { size: 11 } } },
        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6b7280', precision: 0, font: { size: 11 } } },
    },
};

// ── Chart: top extensions ─────────────────────────────────────────────────────
const extChartData = computed(() => ({
    labels: props.topExtensions.map(e => e.extension_number),
    datasets: [{
        label: 'Calls',
        data: props.topExtensions.map(e => e.total),
        backgroundColor: '#6366f1',
        borderRadius: 5,
    }],
}));

const extOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#1f2937', titleColor: '#f9fafb', bodyColor: '#d1d5db' },
    },
    scales: {
        x: { grid: { display: false }, ticks: { color: '#6b7280', font: { size: 11 } } },
        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#6b7280', precision: 0, font: { size: 11 } } },
    },
};

// ── Chart: call distribution ──────────────────────────────────────────────────
const distChartData = computed(() => ({
    labels: ['Inbound', 'Outbound', 'Missed'],
    datasets: [{
        data: [props.stats.inbound_calls, props.stats.outbound_calls, props.stats.missed_calls],
        backgroundColor: ['#6366f1', '#f59e0b', '#ef4444'],
        borderWidth: 0,
        hoverOffset: 6,
    }],
}));

const distOptions = {
    responsive: true, maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#1f2937', titleColor: '#f9fafb', bodyColor: '#d1d5db' },
    },
    cutout: '70%',
};

// ── System overview ───────────────────────────────────────────────────────────
const answerRate = computed(() => {
    const t = props.stats.total_calls;
    return t ? Math.round((props.stats.answered_calls / t) * 100) : 100;
});
const callQuality = computed(() => {
    const r = answerRate.value;
    return r >= 90 ? 'Excellent' : r >= 75 ? 'Good' : r >= 60 ? 'Fair' : 'Poor';
});
const qualityColor = computed(() => ({
    Excellent: '#10b981', Good: '#3b82f6', Fair: '#f59e0b', Poor: '#ef4444',
}[callQuality.value]));
</script>

<template>
    <AppLayout>
        <template #title>Dashboard</template>
        <template #subtitle>Welcome back, {{ page.props.auth.user?.name }}! Here's what's happening today.</template>
        <template #header-actions>
            <!-- Date range display -->
            <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gray-800 border border-gray-700 text-xs text-gray-300">
                <CalendarDaysIcon class="h-3.5 w-3.5 text-gray-400" />
                {{ dateRangeLabel }}
            </div>
            <!-- Period toggle -->
            <div class="flex gap-0.5 bg-gray-800 rounded-lg p-1 border border-gray-700">
                <button
                    v-for="p in ['today','week','month']"
                    :key="p"
                    @click="changePeriod(p)"
                    :class="['px-3 py-1 rounded-md text-xs font-medium capitalize transition-colors',
                             period === p ? 'bg-brand-600 text-white shadow' : 'text-gray-400 hover:text-white']"
                >{{ p }}</button>
            </div>
        </template>

        <!-- Agent warnings -->
        <div v-if="extension" class="mb-5 flex items-center gap-2 p-3 rounded-xl text-xs bg-brand-600/10 border border-brand-500/20 text-brand-300">
            <span class="font-semibold">Extension {{ extension }}</span>
            <span class="text-brand-400">— showing your calls only</span>
        </div>
        <div v-else-if="extension === null && page.props.auth.user?.role !== 'admin'" class="mb-5 p-3 rounded-xl text-xs bg-amber-500/10 border border-amber-500/20 text-amber-400">
            No extension assigned to your account — contact your admin.
        </div>

        <!-- ── Stat cards 4×2 ──────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
            <div
                v-for="(card, i) in cardDefs"
                :key="card.key"
                class="db-card relative overflow-hidden flex flex-col"
            >
                <!-- Icon badge + label row -->
                <div class="flex items-center gap-2 mb-2">
                    <div class="p-2 rounded-lg flex-shrink-0" :style="{ background: card.bg }">
                        <component :is="card.icon" class="h-4 w-4" :style="{ color: card.color }" />
                    </div>
                    <span class="text-xs text-gray-400 truncate">{{ card.label }}</span>
                </div>

                <!-- Value -->
                <p class="text-2xl font-bold text-white">
                    {{ (stats[card.key] ?? 0).toLocaleString() }}{{ card.suffix ?? '' }}
                </p>

                <!-- % vs prev period -->
                <div class="mt-1 text-xs flex items-center gap-1">
                    <template v-if="prevStats && pct(stats[card.key], prevStats[card.key]) !== null">
                        <span :class="Number(pct(stats[card.key], prevStats[card.key])) >= 0 ? 'text-green-400' : 'text-red-400'">
                            {{ Number(pct(stats[card.key], prevStats[card.key])) >= 0 ? '↑' : '↓' }}
                            {{ Math.abs(Number(pct(stats[card.key], prevStats[card.key]))) }}%
                        </span>
                    </template>
                    <template v-else>
                        <span class="text-gray-600">—  0%</span>
                    </template>
                    <span class="text-gray-600">{{ periodLabel }}</span>
                </div>

                <!-- Sparkline -->
                <div class="mt-auto pt-3" v-if="card.sparkKey">
                    <svg class="w-full" height="28" viewBox="0 0 120 28" preserveAspectRatio="none">
                        <path
                            v-if="sparkPath(sparkSeries[card.sparkKey])"
                            :d="sparkPath(sparkSeries[card.sparkKey])"
                            :stroke="card.color"
                            stroke-width="1.5"
                            fill="none"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </div>
            </div>
        </div>

        <!-- ── Agent target card ───────────────────────────────────────────── -->
        <div v-if="targetSummary?.daily_target" class="db-card mb-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-white">Today's Call Target</h3>
                <span class="text-xs text-gray-400">
                    {{ targetSummary.today_calls }} / {{ targetSummary.today_required }} calls
                </span>
            </div>
            <div class="h-2 bg-gray-700 rounded-full overflow-hidden mb-3">
                <div
                    class="h-full rounded-full transition-all"
                    :class="targetSummary.today_calls >= targetSummary.today_required ? 'bg-green-500' : 'bg-brand-500'"
                    :style="{ width: Math.min(100, Math.round((targetSummary.today_calls / targetSummary.today_required) * 100)) + '%' }"
                />
            </div>
            <div class="grid grid-cols-3 gap-4 text-center text-sm">
                <div>
                    <p class="text-xl font-bold text-white">{{ targetSummary.daily_target }}</p>
                    <p class="text-xs text-gray-400">Daily target</p>
                </div>
                <div>
                    <p class="text-xl font-bold" :class="targetSummary.carry_forward > 0 ? 'text-orange-400' : 'text-white'">
                        +{{ targetSummary.carry_forward }}
                    </p>
                    <p class="text-xs text-gray-400">Carried forward</p>
                </div>
                <div>
                    <p class="text-xl font-bold" :class="targetSummary.remaining === 0 ? 'text-green-400' : 'text-white'">
                        {{ targetSummary.remaining === 0 ? '✓ Done' : targetSummary.remaining }}
                    </p>
                    <p class="text-xs text-gray-400">{{ targetSummary.remaining === 0 ? 'Target met!' : 'Still needed' }}</p>
                </div>
            </div>
        </div>

        <!-- ── Charts row ──────────────────────────────────────────────────── -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
            <!-- Call Trend -->
            <div class="db-card lg:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-white">Call Trend (7 days)</h3>
                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <span class="flex items-center gap-1.5">
                            <span class="w-3 h-0.5 rounded bg-indigo-500 inline-block"></span> Total
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-3 h-0.5 rounded bg-red-500 inline-block"></span> Missed
                        </span>
                    </div>
                </div>
                <div class="h-56">
                    <Line :data="trendChartData" :options="trendOptions" />
                </div>
            </div>
            <!-- Top Extensions -->
            <div class="db-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-white">Top Extensions (30 days)</h3>
                    <a href="/extensions" class="text-xs text-brand-400 hover:text-brand-300">View all</a>
                </div>
                <div class="h-56">
                    <Bar :data="extChartData" :options="extOptions" />
                </div>
            </div>
        </div>

        <!-- ── Bottom row: 4 panels ────────────────────────────────────────── -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

            <!-- Recent Calls -->
            <div class="db-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-white">Recent Calls</h3>
                    <a href="/calls" class="text-xs text-brand-400 hover:text-brand-300">View all</a>
                </div>
                <div class="space-y-3">
                    <div
                        v-for="(call, i) in (recentCalls ?? []).slice(0, 5)"
                        :key="call.id"
                        class="flex items-center gap-3"
                    >
                        <div
                            class="flex-shrink-0 h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold text-white select-none"
                            :style="{ background: avatarColor(i) }"
                        >{{ callerInitials(call.caller) }}</div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-gray-200 truncate">{{ call.caller || 'Unknown' }}</p>
                            <p class="text-xs text-gray-600">{{ fmtTime(call.started_at) }}</p>
                        </div>
                        <span
                            :class="['text-xs px-1.5 py-0.5 rounded font-medium',
                                     call.direction === 'inbound'
                                         ? 'bg-green-500/10 text-green-400'
                                         : 'bg-red-500/10 text-red-400']"
                        >{{ call.direction === 'inbound' ? '↓ In' : '↑ Out' }}</span>
                    </div>
                    <div v-if="!recentCalls?.length" class="text-xs text-gray-600 text-center py-6">No calls yet</div>
                </div>
            </div>

            <!-- Call Distribution -->
            <div class="db-card">
                <h3 class="text-sm font-semibold text-white mb-4">Call Distribution</h3>
                <div class="h-32 mb-4">
                    <Doughnut :data="distChartData" :options="distOptions" />
                </div>
                <div class="space-y-2">
                    <div
                        v-for="item in [
                            { label: 'Inbound',  val: stats.inbound_calls,  color: '#6366f1' },
                            { label: 'Outbound', val: stats.outbound_calls, color: '#f59e0b' },
                            { label: 'Missed',   val: stats.missed_calls,   color: '#ef4444' },
                        ]"
                        :key="item.label"
                        class="flex items-center justify-between"
                    >
                        <span class="flex items-center gap-2 text-xs text-gray-400">
                            <span class="w-2 h-2 rounded-full flex-shrink-0" :style="{ background: item.color }"></span>
                            {{ item.label }}
                        </span>
                        <span class="text-xs font-medium text-gray-200">
                            {{ stats.total_calls ? Math.round(item.val / stats.total_calls * 100) : 0 }}%
                        </span>
                    </div>
                </div>
            </div>

            <!-- System Overview -->
            <div class="db-card">
                <h3 class="text-sm font-semibold text-white mb-4">System Overview</h3>
                <div class="space-y-3.5">
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-xs text-gray-400">
                            <PhoneIcon class="h-3.5 w-3.5" /> PBX Status
                        </span>
                        <span class="text-xs font-medium text-green-400">Operational</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-xs text-gray-400">
                            <PhoneArrowDownLeftIcon class="h-3.5 w-3.5" /> Active Calls
                        </span>
                        <span class="text-xs font-medium text-blue-400">{{ stats.active_calls?.length ?? 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-xs text-gray-400">
                            <ClockIcon class="h-3.5 w-3.5" /> Avg Duration
                        </span>
                        <span class="text-xs font-medium text-gray-200">{{ stats.avg_duration }}s</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-xs text-gray-400">
                            <TicketIcon class="h-3.5 w-3.5" /> Open Tickets
                        </span>
                        <span class="text-xs font-medium text-gray-200">{{ stats.open_tickets }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-2 text-xs text-gray-400">
                            <QueueListIcon class="h-3.5 w-3.5" /> Call Quality
                        </span>
                        <span class="text-xs font-medium" :style="{ color: qualityColor }">{{ callQuality }}</span>
                    </div>
                    <div class="pt-2 border-t border-gray-700/50">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs text-gray-500">Answer Rate</span>
                            <span class="text-xs font-medium text-gray-300">{{ answerRate }}%</span>
                        </div>
                        <div class="h-1.5 bg-gray-700 rounded-full overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{ width: answerRate + '%', background: qualityColor }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Activity -->
            <div class="db-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-white">Live Activity</h3>
                    <span class="flex items-center gap-1.5 text-xs text-green-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span>
                        Live
                    </span>
                </div>
                <div class="space-y-3">
                    <div
                        v-for="(call, i) in (recentCalls ?? []).slice(0, 5)"
                        :key="`act-${call.id}`"
                        class="flex items-start gap-3"
                    >
                        <div
                            class="flex-shrink-0 h-7 w-7 rounded-full flex items-center justify-center mt-0.5"
                            :style="{ background: call.direction === 'inbound' ? 'rgba(16,185,129,0.12)' : 'rgba(239,68,68,0.12)' }"
                        >
                            <component
                                :is="call.direction === 'inbound' ? PhoneArrowDownLeftIcon : PhoneArrowUpRightIcon"
                                class="h-3.5 w-3.5"
                                :style="{ color: call.direction === 'inbound' ? '#10b981' : '#ef4444' }"
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-gray-300 leading-tight">
                                {{ call.direction === 'inbound' ? 'Inbound' : 'Outbound' }} call
                                <span :class="call.status === 'missed' ? 'text-red-400' : 'text-green-400'">{{ call.status }}</span>
                            </p>
                            <p class="text-xs text-gray-600 truncate">{{ call.caller }}</p>
                        </div>
                        <span class="text-xs text-gray-700 flex-shrink-0 mt-0.5">{{ fmtTime(call.started_at) }}</span>
                    </div>
                    <div v-if="!recentCalls?.length" class="text-xs text-gray-600 text-center py-6">No recent activity</div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<style scoped>
.db-card {
    background: #161b2e;
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 0.875rem;
    padding: 1.25rem;
}
</style>
