<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { CalendarDaysIcon, SunIcon, MoonIcon, XMarkIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    agents: Array,
    allAgents: Array,
    isManager: Boolean,
    filters: Object,
    shiftTimes: Object,
});

const start = ref(props.filters.start);
const end   = ref(props.filters.end);
const agentId = ref(props.filters.agent_id ?? '');

function runFilter() {
    router.get('/timetable', { start: start.value, end: end.value, agent_id: agentId.value || undefined }, { preserveState: true, replace: true });
}

// ── Date grid for the selected range ─────────────────────────────────────────
const dates = computed(() => {
    if (!start.value || !end.value) return [];
    const out = [];
    let d = new Date(start.value + 'T00:00:00');
    const last = new Date(end.value + 'T00:00:00');
    while (d <= last) {
        out.push(d.toISOString().slice(0, 10));
        d.setDate(d.getDate() + 1);
    }
    return out;
});

function fmtDay(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return { weekday: d.toLocaleDateString(undefined, { weekday: 'short' }), day: d.getDate() };
}

function shiftFor(agent, dateStr) {
    return agent.shifts.find(s => s.date === dateStr)?.shift_type ?? null;
}

function specialFor(agent, dateStr) {
    return agent.special_days.find(s => s.date === dateStr) ?? null;
}

const cellStyle = {
    day:   'bg-amber-100 text-amber-800',
    night: 'bg-indigo-100 text-indigo-800',
};

// ── Generate (manager) ────────────────────────────────────────────────────────
const showGenerate = ref(false);
const generateForm = useForm({
    start_date: props.filters.start,
    end_date:   props.filters.end,
    block_size: 1,
    agent_ids:  [],
});

const weekdays = [
    { value: 1, label: 'Mon' }, { value: 2, label: 'Tue' }, { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' }, { value: 5, label: 'Fri' }, { value: 6, label: 'Sat' },
    { value: 0, label: 'Sun' },
];

// Each agent's own weekly off days, editable right in the Generate modal —
// keyed by agent id so every agent can have a different pattern.
const agentWeeklyOffState = ref(
    Object.fromEntries(props.allAgents.map(a => [a.id, [...(a.weekly_off_days ?? [])]]))
);

function toggleAgentWeeklyOff(agent, day) {
    const current = agentWeeklyOffState.value[agent.id] ?? [];
    const next = current.includes(day) ? current.filter(d => d !== day) : [...current, day];
    agentWeeklyOffState.value = { ...agentWeeklyOffState.value, [agent.id]: next };

    router.post('/timetable/weekly-off', { user_id: agent.id, weekly_off: next }, { preserveScroll: true });
}

// Each agent's own shift preference — 'rotating' (default), 'day', or 'night'.
const shiftPreferenceOptions = [
    { value: 'rotating', label: 'Rotating' },
    { value: 'day',      label: 'Day only' },
    { value: 'night',    label: 'Night only' },
];

const agentShiftPrefState = ref(
    Object.fromEntries(props.allAgents.map(a => [a.id, a.shift_preference ?? 'rotating']))
);

function setAgentShiftPreference(agent, pref) {
    agentShiftPrefState.value = { ...agentShiftPrefState.value, [agent.id]: pref };
    router.post('/timetable/shift-preference', { user_id: agent.id, shift_preference: pref }, { preserveScroll: true });
}

function submitGenerate() {
    generateForm.post('/timetable/generate', {
        preserveScroll: true,
        onSuccess: () => { showGenerate.value = false; router.reload({ only: ['agents'] }); },
    });
}

// ── Special days (agent marks their own unavailable dates) ──────────────────
const specialForm = useForm({ date: '', reason: '' });

function addSpecialDay() {
    specialForm.post('/timetable/special-days', {
        preserveScroll: true,
        onSuccess: () => { specialForm.reset(); router.reload({ only: ['agents'] }); },
    });
}

function removeSpecialDay(id) {
    router.delete(`/timetable/special-days/${id}`, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['agents'] }),
    });
}

// Only meaningful when viewing a single agent (self, or manager filtered to one)
const soleAgent = computed(() => props.agents.length === 1 ? props.agents[0] : null);

// ── Weekly off days — per agent, e.g. always off Sat + Sun ───────────────────
const weeklyOffSelection = ref([...(soleAgent.value?.weekly_off_days ?? [])]);
watch(soleAgent, (a) => { weeklyOffSelection.value = [...(a?.weekly_off_days ?? [])]; });

function toggleWeeklyOff(day) {
    const i = weeklyOffSelection.value.indexOf(day);
    if (i === -1) weeklyOffSelection.value.push(day);
    else weeklyOffSelection.value.splice(i, 1);

    router.post('/timetable/weekly-off', {
        user_id: soleAgent.value.id,
        weekly_off: weeklyOffSelection.value,
    }, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['agents'] }),
    });
}

// ── Shift preference — self view ─────────────────────────────────────────────
const shiftPrefSelection = ref(soleAgent.value?.shift_preference ?? 'rotating');
watch(soleAgent, (a) => { shiftPrefSelection.value = a?.shift_preference ?? 'rotating'; });

function setShiftPreference(pref) {
    shiftPrefSelection.value = pref;
    router.post('/timetable/shift-preference', {
        user_id: soleAgent.value.id,
        shift_preference: pref,
    }, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['agents'] }),
    });
}
</script>

<template>
    <AppLayout>
        <template #title>Timetable</template>
        <template #header-actions>
            <button v-if="isManager" @click="showGenerate = true" class="btn-primary btn-sm">Generate Timetable</button>
        </template>

        <!-- Filters -->
        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="label">From</label>
                <input v-model="start" @change="runFilter" type="date" class="input" />
            </div>
            <div>
                <label class="label">To</label>
                <input v-model="end" @change="runFilter" type="date" class="input" />
            </div>
            <div v-if="isManager">
                <label class="label">Agent</label>
                <select v-model="agentId" @change="runFilter" class="input">
                    <option value="">All agents</option>
                    <option v-for="a in allAgents" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
            </div>
            <div class="flex items-center gap-3 text-xs text-gray-500 ml-auto">
                <span class="flex items-center gap-1.5"><SunIcon class="h-4 w-4 text-amber-500" /> Day {{ shiftTimes.day }}</span>
                <span class="flex items-center gap-1.5"><MoonIcon class="h-4 w-4 text-indigo-500" /> Night {{ shiftTimes.night }}</span>
            </div>
        </div>

        <!-- Grid -->
        <div class="card p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="sticky left-0 bg-gray-50 px-4 py-2 text-left text-xs uppercase text-gray-500 z-10 min-w-[160px]">Agent</th>
                            <th v-for="d in dates" :key="d" class="px-1.5 py-2 text-center text-[11px] text-gray-400 font-medium min-w-[38px]">
                                <div>{{ fmtDay(d).weekday }}</div>
                                <div class="text-gray-600 font-semibold">{{ fmtDay(d).day }}</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="a in agents" :key="a.id">
                            <td class="sticky left-0 bg-white px-4 py-2 font-medium text-gray-800 z-10 border-r border-gray-100">
                                {{ a.name }}
                            </td>
                            <td v-for="d in dates" :key="d" class="text-center px-0.5 py-1.5">
                                <span v-if="specialFor(a, d)"
                                    class="inline-flex items-center justify-center h-7 w-7 rounded-lg bg-red-50 text-red-500 text-[10px] font-semibold"
                                    :title="specialFor(a, d).reason || 'Unavailable'">
                                    Off
                                </span>
                                <span v-else-if="shiftFor(a, d) === 'day'"
                                    class="inline-flex items-center justify-center h-7 w-7 rounded-lg bg-amber-100 text-amber-700">
                                    <SunIcon class="h-4 w-4" />
                                </span>
                                <span v-else-if="shiftFor(a, d) === 'night'"
                                    class="inline-flex items-center justify-center h-7 w-7 rounded-lg bg-indigo-100 text-indigo-700">
                                    <MoonIcon class="h-4 w-4" />
                                </span>
                                <span v-else class="inline-flex items-center justify-center h-7 w-7 rounded-lg text-gray-300 text-[10px]">—</span>
                            </td>
                        </tr>
                        <tr v-if="!agents.length">
                            <td :colspan="dates.length + 1" class="px-4 py-16 text-center text-gray-400">
                                <CalendarDaysIcon class="h-8 w-8 mx-auto mb-2 text-gray-300" />
                                No agents to show.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- My special days (shown when viewing a single agent — self, or manager filtered) -->
        <div v-if="soleAgent" class="card mt-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Unavailable days — {{ soleAgent.name }}</h3>

            <form @submit.prevent="addSpecialDay" class="flex flex-wrap items-end gap-2 mb-4">
                <div>
                    <label class="label">Date</label>
                    <input v-model="specialForm.date" type="date" class="input" required />
                </div>
                <div class="flex-1 min-w-[180px]">
                    <label class="label">Reason (optional)</label>
                    <input v-model="specialForm.reason" class="input" placeholder="e.g. medical appointment" />
                </div>
                <button type="submit" :disabled="specialForm.processing" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                    <PlusIcon class="h-4 w-4" /> Mark unavailable
                </button>
            </form>

            <div class="flex flex-wrap gap-2">
                <span v-for="s in soleAgent.special_days" :key="s.id"
                    class="inline-flex items-center gap-1.5 bg-red-50 text-red-700 rounded-full pl-3 pr-1.5 py-1 text-xs">
                    {{ s.date }}<span v-if="s.reason" class="text-red-400">· {{ s.reason }}</span>
                    <button @click="removeSpecialDay(s.id)" class="hover:bg-red-100 rounded-full p-0.5">
                        <XMarkIcon class="h-3 w-3" />
                    </button>
                </span>
                <p v-if="!soleAgent.special_days.length" class="text-xs text-gray-400">No unavailable days marked in this range.</p>
            </div>
        </div>

        <!-- Weekly off days — recurring, per agent -->
        <div v-if="soleAgent" class="card mt-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Weekly off days — {{ soleAgent.name }}</h3>
            <p class="text-xs text-gray-400 mb-3">Always off on these weekdays, every cycle, until changed.</p>
            <div class="grid grid-cols-7 gap-1 max-w-sm">
                <button v-for="w in weekdays" :key="w.value" type="button" @click="toggleWeeklyOff(w.value)"
                    :class="['flex flex-col items-center gap-1 rounded-lg py-2 text-xs font-medium ring-1 transition-colors',
                        weeklyOffSelection.includes(w.value) ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-gray-600 ring-gray-200 hover:bg-gray-50']">
                    {{ w.label }}
                </button>
            </div>
        </div>

        <!-- Shift preference — per agent -->
        <div v-if="soleAgent" class="card mt-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Shift preference — {{ soleAgent.name }}</h3>
            <p class="text-xs text-gray-400 mb-3">Day only / Night only pins every working day to that shift instead of alternating.</p>
            <div class="flex gap-2">
                <button v-for="opt in shiftPreferenceOptions" :key="opt.value" type="button" @click="setShiftPreference(opt.value)"
                    :class="['px-3 py-1.5 rounded-full text-xs font-medium transition-colors',
                        shiftPrefSelection === opt.value ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
                    {{ opt.label }}
                </button>
            </div>
        </div>

        <!-- Generate modal -->
        <div v-if="showGenerate" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
            <div class="w-full max-w-xl bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between bg-brand-600 px-5 py-4 flex-shrink-0">
                    <span class="text-white font-semibold">Generate Timetable</span>
                    <button @click="showGenerate = false" class="text-white/70 hover:text-white"><XMarkIcon class="h-5 w-5" /></button>
                </div>
                <form @submit.prevent="submitGenerate" class="p-5 space-y-4 overflow-y-auto flex-1">
                    <p class="text-xs text-gray-500">
                        Each agent cycles 14 working days on, 14 days resting, repeating for the whole range
                        (their own marked unavailable days and weekly off days are skipped without breaking
                        the cycle), alternating Day/Night shift in blocks across the working days.
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">Start date</label>
                            <input v-model="generateForm.start_date" type="date" class="input" required />
                        </div>
                        <div>
                            <label class="label">End date</label>
                            <input v-model="generateForm.end_date" type="date" class="input" required />
                        </div>
                    </div>
                    <div>
                        <label class="label">Shift block size (days per block)</label>
                        <input v-model.number="generateForm.block_size" type="number" min="1" max="14" class="input" />
                        <p class="text-xs text-gray-400 mt-1">1 = alternates every working day (Day, Night, Day, Night…). 7 = a week of Day shift, then a week of Night shift.</p>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="label mb-0">Agents (leave empty for all) — each has their own weekly off days &amp; shift preference</label>
                            <button type="button" @click="generateForm.agent_ids = []" class="text-xs text-brand-600 hover:underline">
                                Clear selection
                            </button>
                        </div>
                        <div class="max-h-64 overflow-y-auto rounded-lg ring-1 ring-gray-200 divide-y divide-gray-100">
                            <div v-for="a in allAgents" :key="a.id" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50">
                                <label class="flex items-center gap-2 text-sm cursor-pointer w-28 flex-shrink-0">
                                    <input type="checkbox" :value="a.id" v-model="generateForm.agent_ids" class="rounded border-gray-300 text-brand-600" />
                                    <span class="truncate">{{ a.name }}</span>
                                </label>
                                <div class="flex gap-1">
                                    <button v-for="w in weekdays" :key="w.value" type="button"
                                        @click="toggleAgentWeeklyOff(a, w.value)"
                                        :class="['h-6 w-6 rounded text-[10px] font-medium transition-colors',
                                            agentWeeklyOffState[a.id]?.includes(w.value) ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-400 hover:bg-gray-200']"
                                        :title="w.label">
                                        {{ w.label[0] }}
                                    </button>
                                </div>
                                <select :value="agentShiftPrefState[a.id]" @change="setAgentShiftPreference(a, $event.target.value)"
                                    class="text-xs rounded-lg border-gray-200 py-1 pl-2 pr-6 flex-shrink-0">
                                    <option v-for="opt in shiftPreferenceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">
                            {{ generateForm.agent_ids.length ? `${generateForm.agent_ids.length} selected` : 'None selected — generating for all agents' }}
                        </p>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" :disabled="generateForm.processing" class="btn-primary flex-1 justify-center">
                            {{ generateForm.processing ? 'Generating…' : 'Generate' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
