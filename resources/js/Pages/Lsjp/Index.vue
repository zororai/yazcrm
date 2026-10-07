<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import LsjpPersonForm from '@/Components/LsjpPersonForm.vue';
import { PlusIcon, CheckCircleIcon, ExclamationTriangleIcon, ClockIcon, MinusCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ people: Array, summary: Object, filters: Object, options: Object });

// ── Filters ──────────────────────────────────────────────────────────────────
const filters = ref({ ...props.filters });
let debounce;
watch(filters, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        const q = Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v));
        router.get('/lsjp', q, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
}, { deep: true });

// ── Add person ───────────────────────────────────────────────────────────────
const showAdd = ref(false);
const blank = {
    full_name: '', id_number: '', age: '', sex: '', phone: '', province: '', district: '', location: '',
    key_population: '', current_activity: '', skill_trained: '', project: '', training_completed_on: '', notes: '',
};
const form = useForm({ ...blank });
function openAdd() {
    form.reset();
    form.clearErrors();
    showAdd.value = true;
}
function submit() {
    form.post('/lsjp', { onSuccess: () => { showAdd.value = false; } });
}

// Check-up state → badge style + icon (state is never shown by colour alone).
const stateStyle = {
    done:     { cls: 'bg-green-100 text-green-800', icon: CheckCircleIcon,         text: 'Done' },
    overdue:  { cls: 'bg-red-100 text-red-800',     icon: ExclamationTriangleIcon, text: 'Overdue' },
    due_soon: { cls: 'bg-amber-100 text-amber-800', icon: ClockIcon,               text: 'Due soon' },
    upcoming: { cls: 'bg-gray-100 text-gray-500',   icon: MinusCircleIcon,         text: 'Upcoming' },
};
const sexLabel = { male: 'M', female: 'F', other: 'Other' };
</script>

<template>
    <AppLayout>
        <template #title>LSJP Register</template>
        <template #subtitle>Livelihood Skills &amp; Job Preparation — progress after training</template>
        <template #header-actions>
            <button @click="openAdd" class="btn-primary btn-sm"><PlusIcon class="h-4 w-4" /> Add Person</button>
        </template>

        <!-- Summary -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="card">
                <div class="text-sm text-gray-500">People trained</div>
                <div class="text-2xl font-semibold mt-1">{{ summary.people }}</div>
                <div class="text-xs text-gray-400">{{ summary.complete }} completed all 3 check-ups</div>
            </div>
            <button type="button" class="card text-left hover:ring-2 hover:ring-red-200" @click="filters.follow_up = 'overdue'">
                <div class="text-sm text-gray-500">Overdue check-ups</div>
                <div class="text-2xl font-semibold mt-1 text-red-600">{{ summary.overdue }}</div>
                <div class="text-xs text-gray-400">Show who needs a visit →</div>
            </button>
            <button type="button" class="card text-left hover:ring-2 hover:ring-amber-200" @click="filters.follow_up = 'due_soon'">
                <div class="text-sm text-gray-500">Due in the next 14 days</div>
                <div class="text-2xl font-semibold mt-1 text-amber-600">{{ summary.due_soon }}</div>
                <div class="text-xs text-gray-400">Plan upcoming visits →</div>
            </button>
            <div class="card">
                <div class="text-sm text-gray-500">Check-ups done</div>
                <div class="text-2xl font-semibold mt-1">{{ summary.done }}</div>
                <div class="text-xs text-gray-400">of {{ summary.people * 3 }} scheduled</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="label">Search</label>
                <input v-model="filters.search" class="input" placeholder="Name, ID or phone" />
            </div>
            <div>
                <label class="label">Follow-up</label>
                <select v-model="filters.follow_up" class="input">
                    <option value="">Everyone</option>
                    <option value="overdue">Has an overdue check-up</option>
                    <option value="due_soon">Check-up due in 14 days</option>
                    <option value="complete">All 3 check-ups done</option>
                </select>
            </div>
            <div>
                <label class="label">Project</label>
                <select v-model="filters.project" class="input">
                    <option value="">All projects</option>
                    <option v-for="p in options.projects" :key="p" :value="p">{{ p }}</option>
                </select>
            </div>
            <div>
                <label class="label">District</label>
                <select v-model="filters.district" class="input">
                    <option value="">All districts</option>
                    <option v-for="d in options.districts" :key="d" :value="d">{{ d }}</option>
                </select>
            </div>
        </div>

        <!-- Register -->
        <div class="card p-0 overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Name</th>
                        <th class="table-th">Age / Sex</th>
                        <th class="table-th">Location</th>
                        <th class="table-th">Key Population</th>
                        <th class="table-th">Business / Activity</th>
                        <th class="table-th">Skill · Trained</th>
                        <th v-for="(label, m) in options.checkupMonths" :key="m" class="table-th">{{ label }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="p in people" :key="p.id" class="hover:bg-gray-50 cursor-pointer" @click="router.get(`/lsjp/${p.id}`)">
                        <td class="table-td">
                            <div class="font-medium">{{ p.full_name }}</div>
                            <div class="text-xs text-gray-400">{{ p.id_number || 'No ID' }}</div>
                        </td>
                        <td class="table-td whitespace-nowrap">{{ p.age ?? '—' }}<span v-if="p.sex"> · {{ sexLabel[p.sex] }}</span></td>
                        <td class="table-td">
                            <div>{{ p.location || '—' }}</div>
                            <div class="text-xs text-gray-400">{{ p.district }}</div>
                        </td>
                        <td class="table-td">{{ p.key_population || '—' }}</td>
                        <td class="table-td">{{ p.current_activity || '—' }}</td>
                        <td class="table-td">
                            <div>{{ p.skill_trained || '—' }}</div>
                            <div class="text-xs text-gray-400">{{ p.training_completed_on }}<span v-if="p.project"> · {{ p.project }}</span></div>
                        </td>
                        <td v-for="s in p.schedule" :key="s.month" class="table-td whitespace-nowrap">
                            <span :class="['badge inline-flex items-center gap-1', stateStyle[s.state].cls]" :title="`Due ${s.due_date}`">
                                <component :is="stateStyle[s.state].icon" class="h-3.5 w-3.5" />
                                {{ stateStyle[s.state].text }}
                            </span>
                            <div class="text-[11px] text-gray-400 mt-0.5">{{ s.state === 'done' ? s.conducted_on : `due ${s.due_date}` }}</div>
                        </td>
                    </tr>
                    <tr v-if="!people.length">
                        <td colspan="9" class="table-td text-center text-gray-400 py-8">No one matches. Use "Add Person" to register someone after training.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Add person -->
        <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col">
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">Add Person to LSJP Register</h3>
                    <p class="text-xs text-gray-400 mt-1">Register someone after they've been taught the skill.</p>
                </div>
                <form @submit.prevent="submit" class="overflow-y-auto flex-1 px-6 py-4">
                    <LsjpPersonForm :form="form" :province-districts="options.provinceDistricts" />
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="showAdd = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="submit" class="btn-primary" :disabled="form.processing">Add Person</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
