<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    risks:         Array,
    heatMap:       Object,
    bandCounts:    Object,
    byCategory:    Object,
    topRisks:      Array,
    actionSummary: Object,
    overdueActions: Number,
    assetsNoRisks:  Number,
    totalActions:   Number,
});

const totalRisks = computed(() => props.risks?.length ?? 0);

const bandCellColor = (likelihood, impact) => {
    const score = likelihood * impact;
    if (score >= 15) return 'bg-red-200 text-red-900';
    if (score >= 7)  return 'bg-yellow-100 text-yellow-900';
    return 'bg-green-100 text-green-900';
};

const bandColors = { red: 'bg-red-100 text-red-800', amber: 'bg-yellow-100 text-yellow-800', green: 'bg-green-100 text-green-800' };

function band(score) {
    if (score == null) return 'green';
    if (score >= 15) return 'red';
    if (score >= 7)  return 'amber';
    return 'green';
}

function categoryLabel(cat) {
    return cat?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) ?? cat;
}
</script>

<template>
    <AppLayout>
        <template #title>Risk Dashboard</template>
        <template #header-actions>
            <Link href="/risk/risks" class="btn-secondary btn-sm">Risk Register</Link>
            <Link href="/risk/actions" class="btn-secondary btn-sm">Actions</Link>
            <a href="/risk/report" target="_blank" class="btn-secondary btn-sm">Print Report</a>
        </template>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <div class="card text-center">
                <p class="text-2xl font-bold text-gray-900">{{ totalRisks }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Risks</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-red-600">{{ bandCounts.red }}</p>
                <p class="text-xs text-gray-500 mt-1">Red (&ge;15)</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-yellow-600">{{ bandCounts.amber }}</p>
                <p class="text-xs text-gray-500 mt-1">Amber (7–14)</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-green-600">{{ bandCounts.green }}</p>
                <p class="text-xs text-gray-500 mt-1">Green (&le;6)</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-blue-600">{{ totalActions }}</p>
                <p class="text-xs text-gray-500 mt-1">Open Actions</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold" :class="overdueActions > 0 ? 'text-red-600' : 'text-gray-500'">{{ overdueActions }}</p>
                <p class="text-xs text-gray-500 mt-1">Overdue Actions</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Heat Map -->
            <div class="card">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Risk Heat Map (Likelihood × Impact)</h3>
                <div class="overflow-x-auto">
                    <table class="border-collapse w-full text-center text-xs">
                        <thead>
                            <tr>
                                <th class="p-2 text-gray-400 font-normal text-right">L\I</th>
                                <th v-for="i in [1,2,3,4,5]" :key="i" class="p-2 w-12 font-semibold text-gray-600">{{ i }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="l in [5,4,3,2,1]" :key="l">
                                <td class="p-2 font-semibold text-gray-600 text-right">{{ l }}</td>
                                <td v-for="i in [1,2,3,4,5]" :key="i"
                                    :class="['p-2 w-12 h-10 rounded font-bold transition-colors', bandCellColor(l, i)]">
                                    {{ heatMap?.[l]?.[i] ?? 0 }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="flex items-center gap-4 mt-3 text-xs text-gray-500">
                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-red-200"></span> High (&ge;15)</span>
                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-yellow-100"></span> Medium (7–14)</span>
                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-green-100"></span> Low (&le;6)</span>
                    </div>
                </div>
            </div>

            <!-- By Category -->
            <div class="card">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Risks by Category</h3>
                <div class="space-y-2">
                    <div v-for="(count, cat) in byCategory" :key="cat" class="flex items-center gap-3">
                        <span class="text-xs text-gray-600 w-40 shrink-0">{{ categoryLabel(cat) }}</span>
                        <div class="flex-1 bg-gray-100 rounded-full h-4">
                            <div class="bg-brand-500 h-4 rounded-full transition-all"
                                :style="{ width: totalRisks ? ((count / totalRisks) * 100) + '%' : '0%' }"></div>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 w-6 text-right">{{ count }}</span>
                    </div>
                    <p v-if="!Object.keys(byCategory ?? {}).length" class="text-sm text-gray-400 text-center py-4">No risks yet.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Top 5 Residual Risks -->
            <div class="card">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Top 5 Highest Residual Risks</h3>
                <div v-if="!topRisks?.length" class="text-sm text-gray-400 text-center py-4">No risks yet.</div>
                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-400 border-b border-gray-100">
                            <th class="pb-2 font-medium">Ref</th>
                            <th class="pb-2 font-medium">Description</th>
                            <th class="pb-2 font-medium text-center">Score</th>
                            <th class="pb-2 font-medium">Band</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="risk in topRisks" :key="risk.id" class="border-b border-gray-50 last:border-0">
                            <td class="py-2 pr-3 font-mono text-xs text-gray-600">{{ risk.risk_ref }}</td>
                            <td class="py-2 pr-3 text-gray-700 max-w-xs truncate">{{ risk.description }}</td>
                            <td class="py-2 pr-3 text-center font-bold">{{ risk.residual_score }}</td>
                            <td class="py-2">
                                <span :class="['badge', bandColors[band(risk.residual_score)]]">
                                    {{ band(risk.residual_score) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Action Status -->
            <div class="card">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Action Status Summary</h3>
                <div class="space-y-3">
                    <div v-for="(count, status) in actionSummary" :key="status" class="flex items-center justify-between">
                        <span class="text-sm capitalize text-gray-600">{{ status.replace('_', ' ') }}</span>
                        <span class="badge"
                            :class="{ 'bg-yellow-100 text-yellow-800': status === 'open', 'bg-blue-100 text-blue-800': status === 'in_progress', 'bg-green-100 text-green-800': status === 'done' }">
                            {{ count }}
                        </span>
                    </div>
                    <p v-if="!Object.keys(actionSummary ?? {}).length" class="text-sm text-gray-400 text-center py-4">No actions yet.</p>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-500">Assets with no linked risks: <span class="font-semibold text-gray-700">{{ assetsNoRisks }}</span></p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
