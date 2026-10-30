<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormTabs from '@/Components/DataCollection/FormTabs.vue';
import FilterBar from '@/Components/DataCollection/FilterBar.vue';
import { currentQuery } from '@/Components/DataCollection/answers';
import { Bar, Pie, Line } from 'vue-chartjs';
import {
    Chart as ChartJS, ArcElement, BarElement, CategoryScale, LinearScale, LineElement, PointElement, Tooltip, Legend,
} from 'chart.js';

ChartJS.register(ArcElement, BarElement, CategoryScale, LinearScale, LineElement, PointElement, Tooltip, Legend);

const props = defineProps({
    form: Object, can: Object, fields: Array, repeats: Array, enumerators: Array, filters: Object, statuses: Array,
    summary: Object, crosstab: Object, groupBy: String, row: String, col: String,
});

const PALETTE = ['#2a78d6', '#eb6834', '#1baf7a', '#8b5cf6', '#e0a106', '#d03b6b', '#0e9db3', '#6b7280', '#7c9a2a', '#b45309'];

// Pie or bar per select question, remembered for this page view.
const chartKind = ref({});

const groupable = computed(() => props.fields.filter(f => !f.repeat && !f.hidden && ['select_one', 'select_multiple'].includes(f.type)));
const crossable = computed(() => props.fields.filter(f => !f.hidden && ['select_one', 'select_multiple'].includes(f.type)));

const crossRow = ref(props.row ?? '');
const crossCol = ref(props.col ?? '');

function setParam(params) {
    router.get(window.location.pathname, { ...currentQuery(), ...params }, { preserveScroll: true });
}

function choiceChart(field) {
    return {
        labels: field.rows.map(r => r.label),
        datasets: [{ data: field.rows.map(r => r.count), backgroundColor: field.rows.map((_, i) => PALETTE[i % PALETTE.length]) }],
    };
}
function histogramChart(field) {
    return {
        labels: field.histogram.map(b => (b.from === b.to ? `${b.from}` : `${b.from}–${b.to}`)),
        datasets: [{ data: field.histogram.map(b => b.count), backgroundColor: PALETTE[0] }],
    };
}
function timelineChart(field) {
    return {
        labels: field.timeline.map(t => t.day),
        datasets: [{ data: field.timeline.map(t => t.count), borderColor: PALETTE[0], backgroundColor: PALETTE[0], tension: 0.2 }],
    };
}
const barOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } };
const pieOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } };

const groupLabels = computed(() => props.summary.groups ?? {});

function crossTotal(rowValue) {
    return Object.values(props.crosstab.cells[rowValue] ?? {}).reduce((a, b) => a + b, 0);
}
</script>

<template>
    <AppLayout>
        <template #title>{{ form.name }}: summary</template>

        <FormTabs :form="form" :can="can" active="summary" :filters="filters" />
        <FilterBar :filters="filters" :fields="fields" :enumerators="enumerators" :statuses="statuses" />

        <div class="card mb-4 flex flex-wrap items-end gap-4">
            <p class="text-sm text-gray-700 mr-auto"><span class="text-2xl font-semibold">{{ summary.total.toLocaleString() }}</span> submissions</p>
            <div class="w-60">
                <label class="label">Group by</label>
                <select :value="groupBy ?? ''" class="input" @change="setParam({ group_by: $event.target.value || undefined })">
                    <option value="">No grouping</option>
                    <option v-for="f in groupable" :key="f.path" :value="f.path">{{ f.label }}</option>
                </select>
            </div>
            <div class="w-52">
                <label class="label">Cross-tab rows</label>
                <select v-model="crossRow" class="input">
                    <option value="">—</option>
                    <option v-for="f in crossable" :key="f.path" :value="f.path">{{ f.label }}</option>
                </select>
            </div>
            <div class="w-52">
                <label class="label">× columns</label>
                <select v-model="crossCol" class="input">
                    <option value="">—</option>
                    <option v-for="f in crossable" :key="f.path" :value="f.path">{{ f.label }}</option>
                </select>
            </div>
            <button class="btn-secondary btn-sm" :disabled="!crossRow || !crossCol || crossRow === crossCol" @click="setParam({ row: crossRow, col: crossCol })">Cross-tabulate</button>
        </div>

        <!-- Cross-tab -->
        <div v-if="crosstab" class="card mb-4 overflow-x-auto">
            <h3 class="font-semibold text-gray-900 mb-1">{{ crosstab.row.label }} × {{ crosstab.col.label }}</h3>
            <p class="text-xs text-gray-500 mb-3">Counts per {{ crosstab.per }}.</p>
            <table class="text-sm">
                <thead><tr>
                    <th class="table-th"></th>
                    <th v-for="c in crosstab.col.values" :key="c.value" class="table-th text-right">{{ c.label }}</th>
                    <th class="table-th text-right">Total</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in crosstab.row.values" :key="r.value">
                        <td class="table-td font-medium">{{ r.label }}</td>
                        <td v-for="c in crosstab.col.values" :key="c.value" class="table-td text-right">{{ crosstab.cells[r.value]?.[c.value] ?? 0 }}</td>
                        <td class="table-td text-right font-medium">{{ crossTotal(r.value) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Repeats -->
        <div v-if="summary.repeats.length" class="card mb-4">
            <h3 class="font-semibold text-gray-900 mb-2">Repeat groups</h3>
            <table class="text-sm w-full">
                <thead><tr>
                    <th class="table-th">Repeat</th><th class="table-th text-right">Submissions with entries</th><th class="table-th text-right">Entries</th>
                    <th class="table-th text-right">Mean per submission</th><th class="table-th text-right">Max</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in summary.repeats" :key="r.name">
                        <td class="table-td">{{ r.label }}</td>
                        <td class="table-td text-right">{{ r.submissions }}</td>
                        <td class="table-td text-right">{{ r.instances }}</td>
                        <td class="table-td text-right">{{ r.mean ?? '—' }}</td>
                        <td class="table-td text-right">{{ r.max ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="text-xs text-gray-500 mt-2">Questions inside a repeat are summarised below per entry.</p>
        </div>

        <!-- Per question -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div v-for="field in summary.fields" :key="field.path" class="card">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ field.label }}</h3>
                        <p class="text-xs text-gray-400">
                            {{ field.type.replace('_', ' ') }}<template v-if="field.repeat"> · in {{ field.repeat }} (per entry)</template>
                            <template v-if="field.answered !== undefined"> · {{ field.answered.toLocaleString() }} answered</template>
                        </p>
                    </div>
                    <div v-if="field.kind === 'choices'" class="flex gap-1 text-xs">
                        <button :class="(chartKind[field.path] ?? 'bar') === 'bar' ? 'font-semibold text-brand-700' : 'text-gray-400'" @click="chartKind[field.path] = 'bar'">Bar</button>
                        <button :class="chartKind[field.path] === 'pie' ? 'font-semibold text-brand-700' : 'text-gray-400'" @click="chartKind[field.path] = 'pie'">Pie</button>
                    </div>
                </div>

                <p v-if="field.kind === 'hidden'" class="text-sm text-gray-400">Personal data: hidden for your role.</p>

                <template v-else-if="field.kind === 'choices'">
                    <div class="h-48 mb-3">
                        <Pie v-if="chartKind[field.path] === 'pie'" :data="choiceChart(field)" :options="pieOptions" />
                        <Bar v-else :data="choiceChart(field)" :options="barOptions" />
                    </div>
                    <table class="w-full text-sm">
                        <thead><tr>
                            <th class="table-th">Answer</th><th class="table-th text-right">Count</th><th class="table-th text-right">%</th>
                            <th v-for="(label, g) in (field.grouped ? groupLabels : {})" :key="g" class="table-th text-right">{{ label }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="r in field.rows" :key="r.value">
                                <td class="table-td">{{ r.label }}</td>
                                <td class="table-td text-right">{{ r.count }}</td>
                                <td class="table-td text-right">{{ r.pct }}</td>
                                <td v-for="(label, g) in (field.grouped ? groupLabels : {})" :key="g" class="table-td text-right text-gray-600">{{ field.grouped[r.value]?.[g] ?? 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </template>

                <template v-else-if="field.kind === 'numeric'">
                    <dl class="grid grid-cols-4 gap-2 text-sm mb-3">
                        <div><dt class="text-xs text-gray-400">Mean</dt><dd class="font-semibold">{{ field.mean ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-400">Median</dt><dd class="font-semibold">{{ field.median ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-400">Mode</dt><dd class="font-semibold">{{ field.mode ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-400">Std dev</dt><dd class="font-semibold">{{ field.stddev ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-400">Min</dt><dd class="font-semibold">{{ field.min ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-400">Max</dt><dd class="font-semibold">{{ field.max ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-gray-400">Count</dt><dd class="font-semibold">{{ field.count }}</dd></div>
                    </dl>
                    <div v-if="field.histogram.length" class="h-40"><Bar :data="histogramChart(field)" :options="barOptions" /></div>
                    <table v-if="field.grouped" class="w-full text-sm mt-3">
                        <thead><tr><th class="table-th">Group</th><th class="table-th text-right">Count</th><th class="table-th text-right">Mean</th><th class="table-th text-right">Median</th></tr></thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="(stats, g) in field.grouped" :key="g">
                                <td class="table-td">{{ groupLabels[g] ?? g }}</td>
                                <td class="table-td text-right">{{ stats.count }}</td>
                                <td class="table-td text-right">{{ stats.mean }}</td>
                                <td class="table-td text-right">{{ stats.median }}</td>
                            </tr>
                        </tbody>
                    </table>
                </template>

                <div v-else-if="field.kind === 'timeline'" class="h-40">
                    <Line v-if="field.timeline.length" :data="timelineChart(field)" :options="barOptions" />
                    <p v-else class="text-sm text-gray-400">No answers.</p>
                </div>

                <ul v-else-if="field.kind === 'text'" class="text-sm divide-y divide-gray-50 max-h-56 overflow-y-auto">
                    <li v-for="(s, i) in field.samples" :key="i" class="py-1">{{ s }}</li>
                    <li v-if="!field.samples.length" class="text-gray-400">No answers.</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
