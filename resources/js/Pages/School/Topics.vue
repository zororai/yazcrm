<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ options: Array, filters: Object, topics: Array, today: String });

const STATUS = {
    planned:     { label: 'Planned',     cls: 'bg-gray-100 text-gray-700' },
    in_progress: { label: 'In progress', cls: 'bg-blue-100 text-blue-700' },
    completed:   { label: 'Taught',      cls: 'bg-green-100 text-green-700' },
    skipped:     { label: 'Skipped',     cls: 'bg-amber-100 text-amber-700' },
};

const picker = ref({
    class_id: props.filters.classId ?? '',
    subject_id: props.filters.subjectId ?? '',
    year: props.filters.year,
    term: props.filters.term,
});
const subjectsForClass = computed(() => props.options.find(c => c.id === picker.value.class_id)?.subjects ?? []);
const reload = () => router.get('/courses/topics', picker.value, { preserveScroll: true, replace: true });
function changeClass() {
    picker.value.subject_id = subjectsForClass.value[0]?.id ?? '';
    reload();
}

// Coverage = taught ÷ (all topics except skipped)
const planned = computed(() => props.topics.filter(t => t.status !== 'skipped').length);
const completed = computed(() => props.topics.filter(t => t.status === 'completed').length);
const percent = computed(() => planned.value ? Math.round(completed.value / planned.value * 100) : 0);
const isOverdue = t => t.planned_date && t.planned_date < props.today && !['completed', 'skipped'].includes(t.status);
const overdue = computed(() => props.topics.filter(isOverdue).length);

// ── Add / edit ───────────────────────────────────────────────────────────────
const blank = { week: '', topic: '', objectives: '', planned_date: '', status: 'planned', notes: '' };
const showForm = ref(false);
const editing = ref(null);
const form = useForm({ ...blank });

function openNew() {
    editing.value = null;
    form.clearErrors();
    const lastWeek = Math.max(0, ...props.topics.map(t => t.week ?? 0));
    Object.assign(form, { ...blank, week: lastWeek + 1 });
    showForm.value = true;
}

function openEdit(t) {
    editing.value = t;
    form.clearErrors();
    Object.assign(form, Object.fromEntries(Object.keys(blank).map(k => [k, t[k] ?? ''])));
    showForm.value = true;
}

function submit() {
    const opts = { preserveScroll: true, onSuccess: () => { showForm.value = false; } };
    const clean = d => ({ ...d, week: d.week || null, planned_date: d.planned_date || null });
    if (editing.value) {
        form.transform(clean).put(`/courses/topics/${editing.value.id}`, opts);
    } else {
        form.transform(d => ({
            ...clean(d),
            school_class_id: props.filters.classId,
            school_subject_id: props.filters.subjectId,
            academic_year: props.filters.year,
            term: props.filters.term,
        })).post('/courses/topics', opts);
    }
}

function setStatus(t, status) {
    router.put(`/courses/topics/${t.id}`, { status }, { preserveScroll: true });
}

function remove(t) {
    if (!confirm(`Remove topic "${t.topic}"?`)) return;
    router.delete(`/courses/topics/${t.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <template #title>Course Plan</template>
        <template #header-actions>
            <button v-if="filters.subjectId" @click="openNew" class="btn-primary btn-sm"><PlusIcon class="h-4 w-4" /> Add Topic</button>
        </template>

        <p v-if="!options.length" class="card text-gray-500">
            You have no courses yet. Ask the course admin to set you as a lead or module facilitator in Course Setup.
        </p>

        <template v-else>
            <div class="flex flex-wrap gap-2 mb-4 items-end">
                <div>
                    <label class="label">Course</label>
                    <select v-model="picker.class_id" @change="changeClass" class="input w-auto">
                        <option v-for="c in options" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Module</label>
                    <select v-model="picker.subject_id" @change="reload" class="input w-auto">
                        <option v-for="s in subjectsForClass" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Year</label>
                    <input v-model.number="picker.year" @change="reload" type="number" class="input w-24" />
                </div>
                <div>
                    <label class="label">Term</label>
                    <select v-model.number="picker.term" @change="reload" class="input w-auto">
                        <option v-for="t in [1, 2, 3]" :key="t" :value="t">Term {{ t }}</option>
                    </select>
                </div>
            </div>

            <!-- Coverage -->
            <div class="card mb-4">
                <div class="flex justify-between text-sm mb-2">
                    <span class="font-semibold text-gray-900">Course plan coverage</span>
                    <span class="text-gray-500">
                        {{ completed }} of {{ planned }} topics covered
                        <span v-if="overdue" class="text-red-600 font-medium">· {{ overdue }} behind schedule</span>
                    </span>
                </div>
                <div class="h-3 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all"
                        :class="percent >= 75 ? 'bg-green-500' : percent >= 40 ? 'bg-amber-500' : 'bg-red-500'"
                        :style="{ width: percent + '%' }" />
                </div>
                <div class="text-right text-xs text-gray-500 mt-1">{{ percent }}%</div>
            </div>

            <div class="card p-0 overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="table-th w-16">Week</th>
                            <th class="table-th">Topic</th>
                            <th class="table-th">Planned</th>
                            <th class="table-th">Status</th>
                            <th class="table-th">Facilitator</th>
                            <th class="table-th w-20" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="t in topics" :key="t.id" :class="{ 'bg-red-50/50': isOverdue(t) }">
                            <td class="table-td">{{ t.week ?? '—' }}</td>
                            <td class="table-td">
                                <div class="font-medium">{{ t.topic }}</div>
                                <div v-if="t.objectives" class="text-xs text-gray-500 whitespace-pre-line">{{ t.objectives }}</div>
                                <div v-if="t.notes" class="text-xs text-gray-400 italic mt-1">“{{ t.notes }}”</div>
                            </td>
                            <td class="table-td whitespace-nowrap">
                                {{ t.planned_date ?? '—' }}
                                <span v-if="isOverdue(t)" class="block text-xs text-red-600">overdue</span>
                            </td>
                            <td class="table-td">
                                <select :value="t.status" @change="setStatus(t, $event.target.value)"
                                    class="badge border-0 cursor-pointer" :class="STATUS[t.status].cls">
                                    <option v-for="(s, k) in STATUS" :key="k" :value="k">{{ s.label }}</option>
                                </select>
                                <span v-if="t.completed_date" class="block text-xs text-gray-400 mt-1">{{ t.completed_date }}</span>
                            </td>
                            <td class="table-td text-sm text-gray-500">{{ t.teacher?.name ?? '—' }}</td>
                            <td class="table-td text-right whitespace-nowrap">
                                <button @click="openEdit(t)" class="text-gray-400 hover:text-blue-600 mr-2"><PencilIcon class="h-4 w-4" /></button>
                                <button @click="remove(t)" class="text-gray-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                            </td>
                        </tr>
                        <tr v-if="!topics.length">
                            <td colspan="6" class="table-td text-center text-gray-400 py-8">
                                No topics planned for this term yet — add the topics you are going to cover.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="font-semibold text-gray-900 mb-4">{{ editing ? 'Edit' : 'New' }} Topic</h3>
                <form @submit.prevent="submit" class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Week</label>
                        <input v-model.number="form.week" type="number" min="1" max="20" class="input" />
                    </div>
                    <div>
                        <label class="label">Planned date</label>
                        <input v-model="form.planned_date" type="date" class="input" />
                    </div>
                    <div class="col-span-2">
                        <label class="label">Topic</label>
                        <input v-model="form.topic" class="input" required />
                    </div>
                    <div class="col-span-2">
                        <label class="label">Objectives</label>
                        <textarea v-model="form.objectives" class="input" rows="3" placeholder="By the end of the session participants should be able to…" />
                    </div>
                    <template v-if="editing">
                        <div class="col-span-2">
                            <label class="label">Status</label>
                            <select v-model="form.status" class="input">
                                <option v-for="(s, k) in STATUS" :key="k" :value="k">{{ s.label }}</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="label">Notes / reflection</label>
                            <textarea v-model="form.notes" class="input" rows="2" placeholder="How did it go? Anything to revisit?" />
                        </div>
                    </template>
                    <div class="col-span-2 flex gap-2 justify-end pt-1">
                        <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
