<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ options: Array, filters: Object, students: Array, assessments: Array });

const picker = ref({
    class_id: props.filters.classId ?? '',
    subject_id: props.filters.subjectId ?? '',
    year: props.filters.year,
    term: props.filters.term,
    assessment: props.filters.assessment,
});
const subjectsForClass = computed(() => props.options.find(c => c.id === picker.value.class_id)?.subjects ?? []);

function reload() {
    router.get('/courses/results', picker.value, { preserveScroll: true, replace: true });
}
function changeClass() {
    picker.value.subject_id = subjectsForClass.value[0]?.id ?? '';
    reload();
}

const rows = ref([]);
watch(() => props.students, s => { rows.value = s.map(r => ({ ...r })); }, { immediate: true });

const entered = computed(() => rows.value.filter(r => r.mark !== null && r.mark !== ''));
const average = computed(() => entered.value.length
    ? (entered.value.reduce((s, r) => s + Number(r.mark), 0) / entered.value.length).toFixed(1) : null);
const passRate = computed(() => entered.value.length
    ? Math.round(entered.value.filter(r => Number(r.mark) >= 50).length / entered.value.length * 100) : null);

const form = useForm({});
function save() {
    form.transform(() => ({
        class_id: props.filters.classId,
        subject_id: props.filters.subjectId,
        year: props.filters.year,
        term: props.filters.term,
        assessment: props.filters.assessment,
        entries: rows.value.map(r => ({ id: r.id, mark: r.mark === '' ? null : r.mark, comment: r.comment })),
    })).post('/courses/results', { preserveScroll: true });
}

const newAssessment = ref('');
function addAssessment() {
    if (!newAssessment.value.trim()) return;
    picker.value.assessment = newAssessment.value.trim();
    newAssessment.value = '';
    reload();
}
</script>

<template>
    <AppLayout>
        <template #title>Marks &amp; Results</template>

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
                <div>
                    <label class="label">Assessment</label>
                    <select v-model="picker.assessment" @change="reload" class="input w-auto">
                        <option v-for="a in assessments" :key="a" :value="a">{{ a }}</option>
                        <option v-if="!assessments.includes(picker.assessment)" :value="picker.assessment">{{ picker.assessment }}</option>
                    </select>
                </div>
                <form @submit.prevent="addAssessment" class="flex gap-1">
                    <input v-model="newAssessment" class="input w-40" placeholder="New test name…" />
                    <button class="btn-secondary btn-sm" type="submit">Add</button>
                </form>
            </div>

            <p v-if="!subjectsForClass.length" class="card text-gray-500">No modules set up for this course yet.</p>

            <template v-else>
                <div class="flex flex-wrap gap-2 mb-3 text-sm">
                    <span class="badge bg-gray-100 text-gray-700">Entered: {{ entered.length }} / {{ rows.length }}</span>
                    <span v-if="average !== null" class="badge bg-blue-100 text-blue-700">Course average: {{ average }}%</span>
                    <span v-if="passRate !== null" class="badge bg-green-100 text-green-700">Pass rate (≥50%): {{ passRate }}%</span>
                </div>

                <div class="card p-0 overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="table-th w-10">#</th>
                                <th class="table-th">Participant</th>
                                <th class="table-th w-32">Mark (%)</th>
                                <th class="table-th">Comment</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="(r, i) in rows" :key="r.id">
                                <td class="table-td text-gray-400">{{ i + 1 }}</td>
                                <td class="table-td">
                                    <Link :href="`/courses/participants/${r.id}`" class="font-medium hover:underline">{{ r.full_name }}</Link>
                                    <div class="text-xs text-gray-400">{{ r.admission_number }}</div>
                                </td>
                                <td class="table-td">
                                    <input v-model="r.mark" type="number" min="0" max="100" step="0.5"
                                        class="input py-1 text-sm w-24"
                                        :class="{ 'text-red-600 font-semibold': r.mark !== null && r.mark !== '' && Number(r.mark) < 50 }" />
                                </td>
                                <td class="table-td"><input v-model="r.comment" class="input py-1 text-sm" /></td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="4" class="table-td text-center text-gray-400 py-8">No participants in this course yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="Object.keys(form.errors).length" class="text-sm text-red-600 mt-2">
                    Some marks are invalid — marks must be between 0 and 100.
                </p>

                <div class="flex justify-end mt-4">
                    <button @click="save" class="btn-primary" :disabled="form.processing || !rows.length">Save Marks</button>
                </div>
            </template>
        </template>
    </AppLayout>
</template>
