<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    classes: Array, subjects: Array, assignments: Array, teachers: Array, currentYear: Number,
});

// ── Classes ──────────────────────────────────────────────────────────────────
const showClass = ref(false);
const editingClass = ref(null);
const classForm = useForm({ name: '', grade_level: '', academic_year: props.currentYear, class_teacher_id: '', is_active: true });

function openClass(c = null) {
    editingClass.value = c;
    classForm.clearErrors();
    Object.assign(classForm, c
        ? { name: c.name, grade_level: c.grade_level ?? '', academic_year: c.academic_year, class_teacher_id: c.class_teacher_id ?? '', is_active: c.is_active }
        : { name: '', grade_level: '', academic_year: props.currentYear, class_teacher_id: '', is_active: true });
    showClass.value = true;
}

function submitClass() {
    const opts = { preserveScroll: true, onSuccess: () => { showClass.value = false; } };
    classForm.transform(d => ({ ...d, class_teacher_id: d.class_teacher_id || null }));
    editingClass.value
        ? classForm.put(`/school/setup/classes/${editingClass.value.id}`, opts)
        : classForm.post('/school/setup/classes', opts);
}

function removeClass(c) {
    if (!confirm(`Delete class "${c.name}"? Its registers, marks and scheme of work will be deleted too.`)) return;
    classForm.delete(`/school/setup/classes/${c.id}`, { preserveScroll: true });
}

// ── Subjects ─────────────────────────────────────────────────────────────────
const showSubject = ref(false);
const editingSubject = ref(null);
const subjectForm = useForm({ name: '', code: '', is_active: true });

function openSubject(s = null) {
    editingSubject.value = s;
    subjectForm.clearErrors();
    Object.assign(subjectForm, s ? { name: s.name, code: s.code ?? '', is_active: s.is_active } : { name: '', code: '', is_active: true });
    showSubject.value = true;
}

function submitSubject() {
    const opts = { preserveScroll: true, onSuccess: () => { showSubject.value = false; } };
    editingSubject.value
        ? subjectForm.put(`/school/setup/subjects/${editingSubject.value.id}`, opts)
        : subjectForm.post('/school/setup/subjects', opts);
}

function removeSubject(s) {
    if (!confirm(`Delete subject "${s.name}"? Marks and topics recorded for it will be deleted too.`)) return;
    subjectForm.delete(`/school/setup/subjects/${s.id}`, { preserveScroll: true });
}

// ── Teaching assignments ─────────────────────────────────────────────────────
const assignForm = useForm({ school_class_id: '', school_subject_id: '', teacher_id: '' });

function submitAssignment() {
    assignForm.post('/school/setup/assignments', { preserveScroll: true, onSuccess: () => assignForm.reset('school_subject_id') });
}

function removeAssignment(a) {
    if (!confirm(`Remove ${a.teacher?.name} from ${a.subject?.name} in ${a.school_class?.name}?`)) return;
    assignForm.delete(`/school/setup/assignments/${a.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <template #title>School Setup</template>

        <p v-if="!teachers.length" class="mb-4 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
            No teachers yet. Give staff the <strong>School (Teacher)</strong> permission under Users, then they can be
            set as class or subject teachers here.
        </p>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Classes -->
            <div class="card p-0 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Classes</h3>
                    <button @click="openClass()" class="btn-primary btn-sm"><PlusIcon class="h-4 w-4" /> Class</button>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="table-th">Class</th>
                            <th class="table-th">Year</th>
                            <th class="table-th">Class teacher</th>
                            <th class="table-th">Pupils</th>
                            <th class="table-th w-20" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="c in classes" :key="c.id" class="hover:bg-gray-50" :class="{ 'opacity-50': !c.is_active }">
                            <td class="table-td font-medium">{{ c.name }} <span v-if="c.grade_level" class="text-gray-400 text-xs">· {{ c.grade_level }}</span></td>
                            <td class="table-td">{{ c.academic_year }}</td>
                            <td class="table-td">{{ c.class_teacher?.name ?? '—' }}</td>
                            <td class="table-td">{{ c.students_count }}</td>
                            <td class="table-td text-right whitespace-nowrap">
                                <button @click="openClass(c)" class="text-gray-400 hover:text-blue-600 mr-2"><PencilIcon class="h-4 w-4" /></button>
                                <button @click="removeClass(c)" class="text-gray-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                            </td>
                        </tr>
                        <tr v-if="!classes.length">
                            <td colspan="5" class="table-td text-center text-gray-400 py-8">No classes yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Subjects -->
            <div class="card p-0 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Subjects</h3>
                    <button @click="openSubject()" class="btn-primary btn-sm"><PlusIcon class="h-4 w-4" /> Subject</button>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="table-th">Subject</th>
                            <th class="table-th">Code</th>
                            <th class="table-th w-20" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="s in subjects" :key="s.id" class="hover:bg-gray-50" :class="{ 'opacity-50': !s.is_active }">
                            <td class="table-td font-medium">{{ s.name }}</td>
                            <td class="table-td">{{ s.code ?? '—' }}</td>
                            <td class="table-td text-right whitespace-nowrap">
                                <button @click="openSubject(s)" class="text-gray-400 hover:text-blue-600 mr-2"><PencilIcon class="h-4 w-4" /></button>
                                <button @click="removeSubject(s)" class="text-gray-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                            </td>
                        </tr>
                        <tr v-if="!subjects.length">
                            <td colspan="3" class="table-td text-center text-gray-400 py-8">No subjects yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Teaching assignments -->
        <div class="card p-0 overflow-hidden mt-6">
            <div class="px-4 py-3 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">Who teaches what</h3>
                <p class="text-xs text-gray-500 mt-1">
                    Class teachers can already handle every subject in their class. Add subject teachers here.
                </p>
            </div>
            <form @submit.prevent="submitAssignment" class="flex flex-wrap gap-2 items-end px-4 py-3 border-b border-gray-100">
                <div>
                    <label class="label">Class</label>
                    <select v-model="assignForm.school_class_id" class="input" required>
                        <option value="" disabled>Select…</option>
                        <option v-for="c in classes.filter(c => c.is_active)" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Subject</label>
                    <select v-model="assignForm.school_subject_id" class="input" required>
                        <option value="" disabled>Select…</option>
                        <option v-for="s in subjects.filter(s => s.is_active)" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Teacher</label>
                    <select v-model="assignForm.teacher_id" class="input" required>
                        <option value="" disabled>Select…</option>
                        <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" :disabled="assignForm.processing">Assign</button>
            </form>
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Class</th>
                        <th class="table-th">Subject</th>
                        <th class="table-th">Teacher</th>
                        <th class="table-th w-12" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="a in assignments" :key="a.id" class="hover:bg-gray-50">
                        <td class="table-td">{{ a.school_class?.name }}</td>
                        <td class="table-td">{{ a.subject?.name }}</td>
                        <td class="table-td">{{ a.teacher?.name }}</td>
                        <td class="table-td text-right">
                            <button @click="removeAssignment(a)" class="text-gray-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!assignments.length">
                        <td colspan="4" class="table-td text-center text-gray-400 py-8">No subject teachers assigned yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Class modal -->
        <div v-if="showClass" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">{{ editingClass ? 'Edit' : 'New' }} Class</h3>
                <form @submit.prevent="submitClass" class="space-y-3">
                    <div>
                        <label class="label">Name</label>
                        <input v-model="classForm.name" class="input" placeholder="e.g. Grade 3 Blue" required />
                        <p v-if="classForm.errors.name" class="text-xs text-red-600 mt-1">{{ classForm.errors.name }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Grade</label>
                            <input v-model="classForm.grade_level" class="input" placeholder="Grade 3" />
                        </div>
                        <div>
                            <label class="label">Year</label>
                            <input v-model.number="classForm.academic_year" type="number" class="input" required />
                        </div>
                    </div>
                    <div>
                        <label class="label">Class teacher</label>
                        <select v-model="classForm.class_teacher_id" class="input">
                            <option value="">— None —</option>
                            <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" v-model="classForm.is_active" class="rounded border-gray-300" /> Active
                    </label>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showClass = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="classForm.processing">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Subject modal -->
        <div v-if="showSubject" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">{{ editingSubject ? 'Edit' : 'New' }} Subject</h3>
                <form @submit.prevent="submitSubject" class="space-y-3">
                    <div>
                        <label class="label">Name</label>
                        <input v-model="subjectForm.name" class="input" placeholder="e.g. Mathematics" required />
                    </div>
                    <div>
                        <label class="label">Code</label>
                        <input v-model="subjectForm.code" class="input" placeholder="MATH" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" v-model="subjectForm.is_active" class="rounded border-gray-300" /> Active
                    </label>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showSubject = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="subjectForm.processing">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
