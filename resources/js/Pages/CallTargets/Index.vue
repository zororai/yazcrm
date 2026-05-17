<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PencilSquareIcon, TrashIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ rows: Array });

const editing = ref(null); // agent id currently being edited

function openEdit(row) {
    editing.value = row.id;
    form.agent_id     = row.id;
    form.daily_target = row.daily_target ?? '';
    form.start_date   = row.start_date   ?? new Date().toISOString().slice(0, 10);
}

function closeEdit() {
    editing.value = null;
    form.reset();
}

const form = useForm({
    agent_id:     '',
    daily_target: '',
    start_date:   new Date().toISOString().slice(0, 10),
});

function save() {
    form.post('/call-targets', {
        onSuccess: () => closeEdit(),
    });
}

function remove(agentId) {
    if (!confirm('Remove this agent\'s target?')) return;
    router.delete(`/call-targets/${agentId}`);
}

function pct(made, required) {
    if (!required) return 0;
    return Math.min(100, Math.round((made / required) * 100));
}

function barColor(p) {
    if (p >= 100) return 'bg-green-500';
    if (p >= 60)  return 'bg-blue-500';
    if (p >= 30)  return 'bg-yellow-400';
    return 'bg-red-500';
}
</script>

<template>
    <AppLayout>
        <template #title>Call Targets</template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Agent</th>
                        <th class="table-th text-right">Daily Target</th>
                        <th class="table-th text-right">Carry-Forward</th>
                        <th class="table-th text-right">Today Required</th>
                        <th class="table-th text-right">Today's Calls</th>
                        <th class="table-th">Progress</th>
                        <th class="table-th w-24">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <template v-for="row in rows" :key="row.id">
                        <!-- Normal row -->
                        <tr v-if="editing !== row.id" class="hover:bg-gray-50">
                            <td class="table-td font-medium">{{ row.name }}</td>
                            <td class="table-td text-right">
                                <span v-if="row.daily_target" class="font-semibold text-gray-800">{{ row.daily_target }}</span>
                                <span v-else class="text-gray-400 text-xs">not set</span>
                            </td>
                            <td class="table-td text-right">
                                <span
                                    v-if="row.carry_forward > 0"
                                    class="font-semibold text-orange-600"
                                >+{{ row.carry_forward }}</span>
                                <span v-else class="text-gray-400">0</span>
                            </td>
                            <td class="table-td text-right font-semibold">
                                {{ row.today_required ?? '—' }}
                            </td>
                            <td class="table-td text-right">
                                <span :class="[
                                    'font-semibold',
                                    row.today_required && row.today_calls >= row.today_required
                                        ? 'text-green-600' : 'text-gray-800'
                                ]">{{ row.today_calls }}</span>
                            </td>
                            <td class="table-td">
                                <div v-if="row.today_required" class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div
                                            :class="['h-full rounded-full transition-all', barColor(pct(row.today_calls, row.today_required))]"
                                            :style="{ width: pct(row.today_calls, row.today_required) + '%' }"
                                        />
                                    </div>
                                    <span class="text-xs text-gray-500 w-10 text-right">
                                        {{ pct(row.today_calls, row.today_required) }}%
                                    </span>
                                </div>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="table-td">
                                <div class="flex gap-1">
                                    <button @click="openEdit(row)" class="p-1.5 rounded text-gray-400 hover:text-brand-600 hover:bg-gray-100" title="Edit">
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </button>
                                    <button v-if="row.daily_target" @click="remove(row.id)" class="p-1.5 rounded text-gray-400 hover:text-red-600 hover:bg-red-50" title="Remove target">
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Inline edit row -->
                        <tr v-else class="bg-brand-50">
                            <td class="table-td font-medium text-brand-700">{{ row.name }}</td>
                            <td class="table-td" colspan="4">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center gap-1.5">
                                        <label class="text-xs text-gray-500 whitespace-nowrap">Daily target</label>
                                        <input
                                            v-model="form.daily_target"
                                            type="number" min="1" max="9999"
                                            class="input w-24 py-1 text-sm"
                                            placeholder="e.g. 50"
                                            autofocus
                                        />
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <label class="text-xs text-gray-500 whitespace-nowrap">Count from</label>
                                        <input
                                            v-model="form.start_date"
                                            type="date"
                                            class="input w-36 py-1 text-sm"
                                        />
                                    </div>
                                    <p v-if="form.errors.daily_target" class="text-xs text-red-600">{{ form.errors.daily_target }}</p>
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

                    <tr v-if="!rows.length">
                        <td colspan="7" class="py-12 text-center text-sm text-gray-400">No agents found.</td>
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
            <span class="flex items-center gap-1.5 ml-4 text-orange-600 font-medium">Carry-Forward = unmet calls rolled from previous days</span>
        </div>
    </AppLayout>
</template>
