<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ classes: Array, classId: Number, date: String, students: Array });

const STATUSES = [
    { key: 'present', label: 'P', title: 'Present', on: 'bg-green-600 text-white border-green-600' },
    { key: 'late',    label: 'L', title: 'Late',    on: 'bg-amber-500 text-white border-amber-500' },
    { key: 'absent',  label: 'A', title: 'Absent',  on: 'bg-red-600 text-white border-red-600' },
    { key: 'excused', label: 'E', title: 'Excused', on: 'bg-gray-500 text-white border-gray-500' },
];

const picker = ref({ class_id: props.classId ?? '', date: props.date });
watch(picker, q => router.get('/courses/register', q, { preserveScroll: true, replace: true }), { deep: true });

const form = useForm({ class_id: props.classId, date: props.date, entries: [] });
const rows = ref([]);
watch(() => props.students, s => { rows.value = s.map(r => ({ ...r })); }, { immediate: true });

const alreadyTaken = computed(() => props.students.some(s => s.status));
const totals = computed(() => Object.fromEntries(STATUSES.map(s => [s.key, rows.value.filter(r => r.status === s.key).length])));
const unmarked = computed(() => rows.value.filter(r => !r.status).length);

function setAll(status) {
    rows.value.forEach(r => { if (!r.status) r.status = status; });
}

function toggle(row, status) {
    row.status = row.status === status ? null : status;
}

function save() {
    form.class_id = props.classId;
    form.date = props.date;
    form.entries = rows.value.map(r => ({ id: r.id, status: r.status, note: r.note }));
    form.post('/courses/register', { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <template #title>Attendance Register</template>

        <div class="flex flex-wrap gap-2 mb-4 items-end">
            <div>
                <label class="label">Course</label>
                <select v-model="picker.class_id" class="input w-auto">
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <div>
                <label class="label">Date</label>
                <input v-model="picker.date" type="date" class="input w-auto" :max="new Date().toISOString().slice(0, 10)" />
            </div>
            <span v-if="alreadyTaken" class="badge bg-green-100 text-green-700 mb-2">Register taken — editing</span>
        </div>

        <p v-if="!classes.length" class="card text-gray-500">
            You have no courses yet. Ask the course admin to set you as a lead or module facilitator in Course Setup.
        </p>

        <template v-else>
            <div class="flex flex-wrap gap-2 mb-3 text-sm">
                <button @click="setAll('present')" class="btn-secondary btn-sm">Mark rest present</button>
                <span v-for="s in STATUSES" :key="s.key" class="badge bg-gray-100 text-gray-700">{{ s.title }}: {{ totals[s.key] }}</span>
                <span v-if="unmarked" class="badge bg-amber-100 text-amber-700">Unmarked: {{ unmarked }}</span>
            </div>

            <div class="card p-0 overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="table-th w-10">#</th>
                            <th class="table-th">Participant</th>
                            <th class="table-th">Status</th>
                            <th class="table-th">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="(r, i) in rows" :key="r.id">
                            <td class="table-td text-gray-400">{{ i + 1 }}</td>
                            <td class="table-td">
                                <div class="font-medium">{{ r.full_name }}</div>
                                <div class="text-xs text-gray-400">{{ r.admission_number }}</div>
                            </td>
                            <td class="table-td whitespace-nowrap">
                                <button v-for="s in STATUSES" :key="s.key" type="button" :title="s.title"
                                    @click="toggle(r, s.key)"
                                    class="w-8 h-8 mr-1 rounded-full border text-sm font-semibold transition"
                                    :class="r.status === s.key ? s.on : 'border-gray-200 text-gray-500 hover:bg-gray-50'">
                                    {{ s.label }}
                                </button>
                            </td>
                            <td class="table-td">
                                <input v-if="r.status && r.status !== 'present'" v-model="r.note" class="input py-1 text-sm" placeholder="Reason" />
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="4" class="table-td text-center text-gray-400 py-8">No participants in this course yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end mt-4">
                <button @click="save" class="btn-primary" :disabled="form.processing || !rows.length">Save Register</button>
            </div>
        </template>
    </AppLayout>
</template>
