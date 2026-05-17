<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PencilSquareIcon, TrashIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ rows: Array });

// ── Totals summary ────────────────────────────────────────────────────────────
const grandTotal = computed(() => {
    const active = props.rows.filter(r => r.daily_target && !r.expired);
    return {
        period_target: active.reduce((s, r) => s + (r.period_target ?? 0), 0),
        period_calls:  active.reduce((s, r) => s + (r.period_calls  ?? 0), 0),
    };
});

// ── Inline edit ───────────────────────────────────────────────────────────────
const editing = ref(null);

const form = useForm({
    agent_id:     '',
    daily_target: '',
    start_date:   '',
    end_date:     '',
});

function openEdit(row) {
    editing.value     = row.id;
    form.agent_id     = row.id;
    form.daily_target = row.daily_target ?? '';
    form.start_date   = row.start_date   ?? new Date().toISOString().slice(0, 10);
    form.end_date     = row.end_date     ?? '';
}

function closeEdit() { editing.value = null; form.reset(); }
function save() { form.post('/call-targets', { onSuccess: closeEdit }); }

function remove(agentId) {
    if (!confirm("Remove this agent's target?")) return;
    router.delete(`/call-targets/${agentId}`);
}

// ── Display helpers ───────────────────────────────────────────────────────────
function pct(made, total) {
    if (!total) return 0;
    return Math.min(100, Math.round((made / total) * 100));
}

function barColor(p) {
    if (p >= 100) return 'bg-green-500';
    if (p >= 60)  return 'bg-blue-500';
    if (p >= 30)  return 'bg-yellow-400';
    return 'bg-red-500';
}

function fmt(d) {
    if (!d) return '—';
    return new Date(d + 'T00:00:00').toLocaleDateString();
}
</script>

<template>
    <AppLayout>
        <template #title>Call Targets</template>

        <!-- ── Overall summary cards ─────────────────────────────────────── -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="card text-center p-5 border border-gray-200">
                <p class="text-3xl font-bold text-gray-900">{{ grandTotal.period_target.toLocaleString() }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Targeted Calls</p>
                <p class="text-xs text-gray-400">(across all active agents &amp; periods)</p>
            </div>
            <div class="card text-center p-5 border border-blue-200 bg-blue-50">
                <p class="text-3xl font-bold text-blue-700">{{ grandTotal.period_calls.toLocaleString() }}</p>
                <p class="text-xs text-gray-500 mt-1">Calls Made So Far</p>
            </div>
            <div class="card text-center p-5 border border-gray-200">
                <p class="text-3xl font-bold"
                   :class="pct(grandTotal.period_calls, grandTotal.period_target) >= 100 ? 'text-green-600' : 'text-gray-900'">
                    {{ pct(grandTotal.period_calls, grandTotal.period_target) }}%
                </p>
                <p class="text-xs text-gray-500 mt-1">Overall Coverage</p>
                <!-- Grand progress bar -->
                <div class="mt-2 h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div
                        :class="['h-full rounded-full transition-all', barColor(pct(grandTotal.period_calls, grandTotal.period_target))]"
                        :style="{ width: pct(grandTotal.period_calls, grandTotal.period_target) + '%' }"
                    />
                </div>
            </div>
        </div>

        <!-- ── Per-agent table ────────────────────────────────────────────── -->
        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Agent</th>
                        <th class="table-th text-right">Daily Target</th>
                        <th class="table-th">Period</th>
                        <th class="table-th text-right">Period Target</th>
                        <th class="table-th text-right">Calls Made</th>
                        <th class="table-th">Period Coverage</th>
                        <th class="table-th text-right">Carry-Fwd</th>
                        <th class="table-th text-right">Today Required</th>
                        <th class="table-th text-right">Today's Calls</th>
                        <th class="table-th w-24">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <template v-for="row in rows" :key="row.id">

                        <!-- Normal row -->
                        <tr v-if="editing !== row.id" :class="row.expired ? 'opacity-50' : 'hover:bg-gray-50'">
                            <td class="table-td font-medium">
                                {{ row.name }}
                                <span v-if="row.expired" class="ml-1 text-xs text-red-500">(expired)</span>
                            </td>
                            <td class="table-td text-right">
                                <span v-if="row.daily_target" class="font-semibold">{{ row.daily_target }}</span>
                                <span v-else class="text-gray-400 text-xs">not set</span>
                            </td>
                            <td class="table-td text-xs text-gray-500 whitespace-nowrap">
                                <template v-if="row.start_date">
                                    {{ fmt(row.start_date) }} →
                                    <span :class="row.end_date ? '' : 'italic text-gray-400'">
                                        {{ row.end_date ? fmt(row.end_date) : 'ongoing' }}
                                    </span>
                                </template>
                                <span v-else class="text-gray-400">—</span>
                            </td>

                            <!-- Period target -->
                            <td class="table-td text-right font-semibold text-gray-700">
                                {{ row.period_target != null ? row.period_target.toLocaleString() : '—' }}
                            </td>

                            <!-- Calls made in period -->
                            <td class="table-td text-right font-semibold"
                                :class="row.period_calls >= row.period_target ? 'text-green-600' : 'text-gray-800'">
                                {{ row.period_calls != null ? row.period_calls.toLocaleString() : '—' }}
                            </td>

                            <!-- Period coverage bar -->
                            <td class="table-td min-w-[140px]">
                                <div v-if="row.period_target" class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div
                                            :class="['h-full rounded-full transition-all', barColor(pct(row.period_calls, row.period_target))]"
                                            :style="{ width: pct(row.period_calls, row.period_target) + '%' }"
                                        />
                                    </div>
                                    <span class="text-xs text-gray-500 w-10 text-right">
                                        {{ pct(row.period_calls, row.period_target) }}%
                                    </span>
                                </div>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>

                            <!-- Today cols -->
                            <td class="table-td text-right">
                                <span v-if="row.carry_forward > 0" class="font-semibold text-orange-600">+{{ row.carry_forward }}</span>
                                <span v-else class="text-gray-400">0</span>
                            </td>
                            <td class="table-td text-right font-semibold">{{ row.today_required ?? '—' }}</td>
                            <td class="table-td text-right">
                                <span :class="['font-semibold', row.today_required && row.today_calls >= row.today_required ? 'text-green-600' : 'text-gray-800']">
                                    {{ row.today_calls }}
                                </span>
                            </td>

                            <td class="table-td">
                                <div class="flex gap-1">
                                    <button @click="openEdit(row)" class="p-1.5 rounded text-gray-400 hover:text-brand-600 hover:bg-gray-100" title="Edit">
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </button>
                                    <button v-if="row.daily_target" @click="remove(row.id)" class="p-1.5 rounded text-gray-400 hover:text-red-600 hover:bg-red-50" title="Remove">
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Inline edit row -->
                        <tr v-else class="bg-brand-50">
                            <td class="table-td font-medium text-brand-700">{{ row.name }}</td>
                            <td class="table-td">
                                <input
                                    v-model="form.daily_target"
                                    type="number" min="1" max="9999"
                                    class="input w-24 py-1 text-sm"
                                    placeholder="50"
                                    autofocus
                                />
                                <p v-if="form.errors.daily_target" class="text-xs text-red-600 mt-1">{{ form.errors.daily_target }}</p>
                            </td>
                            <td class="table-td" colspan="6">
                                <div class="flex items-center gap-3 flex-wrap">
                                    <div class="flex items-center gap-1.5">
                                        <label class="text-xs text-gray-500">Start</label>
                                        <input v-model="form.start_date" type="date" class="input w-36 py-1 text-sm" />
                                    </div>
                                    <span class="text-gray-400">→</span>
                                    <div class="flex items-center gap-1.5">
                                        <label class="text-xs text-gray-500">End</label>
                                        <input v-model="form.end_date" type="date" class="input w-36 py-1 text-sm" />
                                        <span class="text-xs text-gray-400">(blank = ongoing)</span>
                                    </div>
                                    <p v-if="form.errors.end_date" class="text-xs text-red-600">{{ form.errors.end_date }}</p>
                                </div>
                            </td>
                            <td class="table-td" colspan="2">
                                <div class="flex gap-1.5">
                                    <button @click="save" :disabled="form.processing" class="btn-primary btn-sm py-1">
                                        <CheckCircleIcon class="h-4 w-4" /> Save
                                    </button>
                                    <button @click="closeEdit" class="btn-secondary btn-sm py-1">
                                        <XCircleIcon class="h-4 w-4" /> Cancel
                                    </button>
                                </div>
                            </td>
                        </tr>

                    </template>

                    <!-- Grand total row -->
                    <tr class="bg-gray-50 font-semibold border-t-2 border-gray-200">
                        <td class="table-td" colspan="3">Total (active)</td>
                        <td class="table-td text-right">{{ grandTotal.period_target.toLocaleString() }}</td>
                        <td class="table-td text-right" :class="grandTotal.period_calls >= grandTotal.period_target ? 'text-green-600' : ''">
                            {{ grandTotal.period_calls.toLocaleString() }}
                        </td>
                        <td class="table-td">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div
                                        :class="['h-full rounded-full', barColor(pct(grandTotal.period_calls, grandTotal.period_target))]"
                                        :style="{ width: pct(grandTotal.period_calls, grandTotal.period_target) + '%' }"
                                    />
                                </div>
                                <span class="text-xs text-gray-600 w-10 text-right">{{ pct(grandTotal.period_calls, grandTotal.period_target) }}%</span>
                            </div>
                        </td>
                        <td colspan="4" />
                    </tr>

                    <tr v-if="!rows.length">
                        <td colspan="10" class="py-12 text-center text-sm text-gray-400">No agents found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Legend -->
        <div class="mt-4 flex flex-wrap gap-4 text-xs text-gray-500">
            <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-6 rounded bg-green-500"></span> Met (≥ 100%)</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-6 rounded bg-blue-500"></span> On track (≥ 60%)</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-6 rounded bg-yellow-400"></span> Behind (≥ 30%)</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-2 w-6 rounded bg-red-500"></span> At risk (< 30%)</span>
            <span class="ml-4 text-orange-600 font-medium">Period target = daily target × total days in period</span>
        </div>
    </AppLayout>
</template>
