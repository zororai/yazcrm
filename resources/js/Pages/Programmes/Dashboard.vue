<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js';
import { ArrowDownTrayIcon, ArrowTrendingUpIcon, ArrowTrendingDownIcon } from '@heroicons/vue/24/outline';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

const props = defineProps({
    filters: Object,
    periodLabel: String,
    summary: Object,
    trend: Object,
    caseTypes: Array,
    caseTypeRecorded: Number,
    services: Array,
    gender: Array,
    ageGroups: Array,
    ageGender: Array,
    programmes: Array,
    periods: Array,
    options: Object,
});

// ── Filters (same set as /screen) ────────────────────────────────────────────
// Each filter applies as soon as it changes; the date range applies once both
// dates are set ("Apply range" isn't needed).
const filters = ref({
    programme: props.filters.programme ?? '',
    period: props.filters.period ?? 'month',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    service: props.filters.service ?? '',
    gender: props.filters.gender ?? '',
    province: props.filters.province ?? '',
    district: props.filters.district ?? '',
    age: props.filters.age ?? '',
});
const query = computed(() => {
    const f = filters.value;
    const q = { period: f.period };
    for (const key of ['programme', 'service', 'gender', 'province', 'district', 'age']) {
        if (f[key]) q[key] = f[key];
    }
    if (f.period === 'custom') {
        if (f.from) q.from = f.from;
        if (f.to) q.to = f.to;
    }
    return q;
});
watch(query, q => {
    router.get('/programmes/dashboard', q, { preserveState: true, preserveScroll: true, replace: true });
}, { deep: true });

// Outside "Custom range", show the dates the chosen period actually covers.
watch(() => [props.filters.from, props.filters.to], ([from, to]) => {
    if (filters.value.period !== 'custom') {
        filters.value.from = from ?? '';
        filters.value.to = to ?? '';
    }
});

// Typing a date switches the period to "Custom range".
function onDateInput() {
    if (filters.value.period !== 'custom') filters.value.period = 'custom';
}

// District list narrows to the chosen province, as on /screen; a district
// outside the new province is cleared.
const districtOptions = computed(() => props.options.provinceDistricts[filters.value.province] ?? props.options.allDistricts);
watch(() => filters.value.province, () => {
    if (filters.value.district && !districtOptions.value.includes(filters.value.district)) filters.value.district = '';
});

const activeFilterCount = computed(() =>
    ['programme', 'service', 'gender', 'province', 'district', 'age'].filter(k => filters.value[k]).length);
function clearFilters() {
    Object.assign(filters.value, { programme: '', service: '', gender: '', province: '', district: '', age: '' });
}
const pdfHref = computed(() => `/programmes/dashboard/export/pdf?${new URLSearchParams(query.value)}`);

// ── Formatting ───────────────────────────────────────────────────────────────
const num = v => Number(v ?? 0).toLocaleString();
// Whole-number share; a non-zero share that rounds to 0 shows as "<1".
const pct = (part, whole) => {
    if (!(whole > 0)) return 0;
    const v = Math.round((part / whole) * 100);
    return v === 0 && part > 0 ? '<1' : v;
};
const change = computed(() => {
    const prev = props.summary.previous_total;
    if (prev === null || prev === undefined) return null;
    if (prev === 0) return props.summary.total > 0 ? { up: true, text: 'new this period' } : null;
    const diff = Math.round(((props.summary.total - prev) / prev) * 100);
    return { up: diff >= 0, text: `${diff >= 0 ? '+' : ''}${diff}% vs previous period (${num(prev)})` };
});
const genderTotal = computed(() => props.gender.reduce((s, g) => s + g.count, 0));

// ── Chart theme ──────────────────────────────────────────────────────────────
// Validated categorical slots 1–3 (blue, orange, aqua), stepped per theme.
const isLight = (() => { try { return localStorage.getItem('theme') === 'light'; } catch { return false; } })();
const c = isLight
    ? { s1: '#2a78d6', s2: '#eb6834', s3: '#1baf7a', text: '#52514e', grid: '#e5e7eb' }
    : { s1: '#3987e5', s2: '#d95926', s3: '#199e70', text: '#c3c2b7', grid: '#30363d' };

const tooltip = { backgroundColor: '#1f2937', titleColor: '#f9fafb', bodyColor: '#d1d5db', padding: 10 };
const axis = (extra = {}) => ({
    ticks: { color: c.text, font: { size: 11 }, precision: 0 },
    grid: { color: c.grid, drawTicks: false },
    border: { display: false },
    ...extra,
});
const legend = { position: 'top', align: 'start', labels: { color: c.text, boxWidth: 10, boxHeight: 10, useBorderRadius: true, borderRadius: 2 } };
const hBarOptions = (showLegend = false) => ({
    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', axis: 'y', intersect: false },
    plugins: { legend: showLegend ? legend : { display: false }, tooltip },
    scales: { x: axis({ beginAtZero: true }), y: axis({ grid: { display: false } }) },
});
const vBarOptions = (showLegend = false) => ({
    responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: showLegend ? legend : { display: false }, tooltip },
    scales: { x: axis({ grid: { display: false } }), y: axis({ beginAtZero: true }) },
});
const bar = (label, data, color, extra = {}) => ({ label, data, backgroundColor: color, borderRadius: 4, borderSkipped: 'start', barPercentage: 0.75, ...extra });
const barHeight = n => `${Math.max(160, n * 30 + 50)}px`;

const trendChart = computed(() => ({
    labels: props.trend.points.map(p => p.label),
    datasets: [bar('Cases', props.trend.points.map(p => p.count), c.s1)],
}));
const caseTypeChart = computed(() => ({
    labels: props.caseTypes.map(r => r.name),
    datasets: [bar('Cases', props.caseTypes.map(r => r.count), c.s1)],
}));
const serviceChart = computed(() => ({
    labels: props.services.map(s => s.name),
    datasets: [
        bar('Referred cases', props.services.map(s => s.referred), c.s1),
        bar('Confirmed service uptake', props.services.map(s => s.uptake), c.s2),
    ],
}));
const ageChart = computed(() => ({
    labels: props.ageGroups.map(a => a.name),
    datasets: [bar('Cases', props.ageGroups.map(a => a.count), c.s1)],
}));
const ageGenderChart = computed(() => ({
    labels: props.ageGender.map(r => r.band),
    datasets: [
        bar('Male', props.ageGender.map(r => r.male), c.s1),
        bar('Female', props.ageGender.map(r => r.female), c.s2),
        bar('Other', props.ageGender.map(r => r.other), c.s3),
    ],
}));
const genderColor = name => ({ Male: c.s1, Female: c.s2 }[name] ?? c.s3);
</script>

<template>
    <AppLayout>
        <template #title>Programmes Dashboard</template>
        <template #header-actions>
            <a :href="pdfHref" class="btn-secondary btn-sm inline-flex items-center gap-1">
                <ArrowDownTrayIcon class="h-4 w-4" /> Export PDF
            </a>
        </template>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-6 gap-3 items-end">
                <div>
                    <label class="label">Period</label>
                    <select v-model="filters.period" class="input">
                        <option v-for="p in periods" :key="p.key" :value="p.key">{{ p.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">From</label>
                    <input v-model.lazy="filters.from" @change="onDateInput" type="date" class="input" />
                </div>
                <div>
                    <label class="label">To</label>
                    <input v-model.lazy="filters.to" @change="onDateInput" type="date" class="input" />
                </div>
                <div>
                    <label class="label">Project</label>
                    <select v-model="filters.programme" class="input">
                        <option value="">All projects</option>
                        <option v-for="p in programmes" :key="p" :value="p">{{ p }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Service</label>
                    <select v-model="filters.service" class="input">
                        <option value="">All services</option>
                        <option v-for="s in options.services" :key="s" :value="s">{{ s }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Gender</label>
                    <select v-model="filters.gender" class="input">
                        <option value="">All genders</option>
                        <option v-for="(label, key) in options.genders" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Province</label>
                    <select v-model="filters.province" class="input">
                        <option value="">All provinces</option>
                        <option v-for="(_, p) in options.provinceDistricts" :key="p" :value="p">{{ p }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">District</label>
                    <select v-model="filters.district" class="input">
                        <option value="">{{ filters.province ? `All districts in ${filters.province}` : 'All districts' }}</option>
                        <option v-for="d in districtOptions" :key="d" :value="d">{{ d }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Age group</label>
                    <select v-model="filters.age" class="input">
                        <option value="">All ages</option>
                        <option v-for="(label, key) in options.ageGroups" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 mt-3 text-xs text-gray-400">
                <span>{{ periodLabel }}<template v-if="filters.period !== 'all'"> · {{ props.filters.from }} – {{ props.filters.to }}</template></span>
                <button v-if="activeFilterCount" type="button" @click="clearFilters" class="text-brand-600 hover:underline">
                    Clear {{ activeFilterCount }} filter{{ activeFilterCount === 1 ? '' : 's' }}
                </button>
            </div>
        </div>

        <!-- Total Cases -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="card">
                <div class="text-sm text-gray-500">Total cases</div>
                <div class="text-3xl font-semibold mt-1">{{ num(summary.total) }}</div>
                <div v-if="change" :class="['text-xs inline-flex items-center gap-1', change.up ? 'text-green-700' : 'text-red-600']">
                    <component :is="change.up ? ArrowTrendingUpIcon : ArrowTrendingDownIcon" class="h-3.5 w-3.5" /> {{ change.text }}
                </div>
            </div>
            <div class="card">
                <div class="text-sm text-gray-500">Referred cases</div>
                <div class="text-3xl font-semibold mt-1">{{ num(summary.referred) }}</div>
                <div class="text-xs text-gray-400">{{ pct(summary.referred, summary.total) }}% of cases</div>
            </div>
            <div class="card">
                <div class="text-sm text-gray-500">Confirmed service uptake</div>
                <div class="text-3xl font-semibold mt-1">{{ num(summary.uptake) }}</div>
                <div class="text-xs text-gray-400">{{ pct(summary.uptake, summary.total) }}% of cases</div>
            </div>
            <div class="card">
                <div class="text-sm text-gray-500">Valid cases</div>
                <div class="text-3xl font-semibold mt-1">{{ num(summary.valid) }}</div>
                <div class="text-xs text-gray-400">{{ pct(summary.valid, summary.total) }}% valid · {{ num(summary.repeat_callers) }} repeat callers</div>
            </div>
        </div>

        <div class="card mb-4">
            <h3 class="font-semibold text-gray-900">Cases per {{ trend.unit }}</h3>
            <p class="text-xs text-gray-400 mb-3">{{ periodLabel }}</p>
            <div class="h-56">
                <Bar :data="trendChart" :options="vBarOptions()" />
            </div>
        </div>

        <!-- Calls by Case Type & Referral By Service -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="card">
                <h3 class="font-semibold text-gray-900">Calls by case type</h3>
                <p class="text-xs text-gray-400 mb-3">{{ num(caseTypeRecorded) }} of {{ num(summary.total) }} cases have a case type recorded.</p>
                <template v-if="caseTypes.length">
                    <div :style="{ height: barHeight(caseTypes.length) }">
                        <Bar :data="caseTypeChart" :options="hBarOptions()" />
                    </div>
                    <table class="w-full text-sm mt-3">
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="r in caseTypes" :key="r.name">
                                <td class="py-1.5">{{ r.name }}</td>
                                <td class="py-1.5 text-right font-medium">{{ num(r.count) }}</td>
                                <td class="py-1.5 text-right text-gray-400 w-14">{{ pct(r.count, caseTypeRecorded) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </template>
                <p v-else class="text-sm text-gray-400 py-8 text-center">No cases in this period.</p>
            </div>

            <div class="card">
                <h3 class="font-semibold text-gray-900">Referral by service</h3>
                <p class="text-xs text-gray-400 mb-3">Cases referred to each service, and how many confirmed taking it up.</p>
                <template v-if="services.length">
                    <div :style="{ height: barHeight(services.length * 1.6) }">
                        <Bar :data="serviceChart" :options="hBarOptions(true)" />
                    </div>
                    <table class="w-full text-sm mt-3">
                        <thead>
                            <tr class="text-xs text-gray-400">
                                <th class="text-left font-medium py-1">Service</th>
                                <th class="text-right font-medium py-1">Referred</th>
                                <th class="text-right font-medium py-1">Uptake</th>
                                <th class="text-right font-medium py-1">Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="s in services" :key="s.name">
                                <td class="py-1.5">{{ s.name }}</td>
                                <td class="py-1.5 text-right font-medium">{{ num(s.referred) }}</td>
                                <td class="py-1.5 text-right">{{ num(s.uptake) }}</td>
                                <td class="py-1.5 text-right text-gray-400">{{ s.rate }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </template>
                <p v-else class="text-sm text-gray-400 py-8 text-center">No referrals in this period.</p>
            </div>
        </div>

        <!-- Demographics -->
        <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-400 mb-2">Demographics</h2>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <div class="card">
                <h3 class="font-semibold text-gray-900 mb-3">Gender</h3>
                <div v-if="gender.length" class="space-y-3">
                    <div v-for="g in gender" :key="g.name">
                        <div class="flex justify-between text-sm mb-1">
                            <span>{{ g.name }}</span>
                            <span><span class="font-medium">{{ num(g.count) }}</span> <span class="text-gray-400">· {{ pct(g.count, genderTotal) }}%</span></span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-2 rounded-full" :style="{ width: `${pct(g.count, genderTotal)}%`, background: genderColor(g.name) }"></div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400">{{ num(summary.total - genderTotal) }} cases with no gender recorded.</p>
                </div>
                <p v-else class="text-sm text-gray-400 py-8 text-center">No gender recorded.</p>
            </div>
            <div class="card lg:col-span-2">
                <h3 class="font-semibold text-gray-900 mb-3">Age groups</h3>
                <div class="h-52">
                    <Bar :data="ageChart" :options="vBarOptions()" />
                </div>
            </div>
        </div>

        <div class="card">
            <h3 class="font-semibold text-gray-900">Cases by gender &amp; age</h3>
            <p class="text-xs text-gray-400 mb-3">Same youth age bands as the helpline screen (ages 10 and over).</p>
            <div class="h-64">
                <Bar :data="ageGenderChart" :options="vBarOptions(true)" />
            </div>
            <table class="w-full text-sm mt-3">
                <thead>
                    <tr class="text-xs text-gray-400">
                        <th class="text-left font-medium py-1">Age</th>
                        <th class="text-right font-medium py-1">Male</th>
                        <th class="text-right font-medium py-1">Female</th>
                        <th class="text-right font-medium py-1">Other</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in ageGender" :key="r.band">
                        <td class="py-1.5">{{ r.band }}</td>
                        <td class="py-1.5 text-right">{{ num(r.male) }}</td>
                        <td class="py-1.5 text-right">{{ num(r.female) }}</td>
                        <td class="py-1.5 text-right">{{ num(r.other) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
