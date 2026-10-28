<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ students: Object, classes: Array, filters: Object });

const page = usePage();
const user = page.props.auth?.user;
const isSchoolAdmin = user?.role === 'admin' || (user?.nav_permissions ?? []).includes('school_admin');

const filters = ref({ class_id: props.filters.class_id ?? '', search: props.filters.search ?? '' });
let debounce;
watch(filters, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        const q = Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v));
        router.get('/school/students', q, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
}, { deep: true });

const blank = {
    admission_number: '', first_name: '', surname: '', gender: '', date_of_birth: '',
    school_class_id: '', guardian_name: '', guardian_phone: '', is_active: true,
};
const showForm = ref(false);
const editing = ref(null);
const form = useForm({ ...blank });

function openNew() {
    editing.value = null;
    form.clearErrors();
    Object.assign(form, { ...blank, school_class_id: filters.value.class_id || '' });
    showForm.value = true;
}

function openEdit(s) {
    editing.value = s;
    form.clearErrors();
    Object.assign(form, Object.fromEntries(Object.keys(blank).map(k => [k, s[k] ?? ''])));
    form.is_active = s.is_active;
    showForm.value = true;
}

function submit() {
    const opts = { preserveScroll: true, onSuccess: () => { showForm.value = false; } };
    form.transform(d => ({ ...d, gender: d.gender || null, school_class_id: d.school_class_id || null, date_of_birth: d.date_of_birth || null }));
    editing.value ? form.put(`/school/students/${editing.value.id}`, opts) : form.post('/school/students', opts);
}

function remove(s) {
    if (!confirm(`Delete ${s.full_name}? Their attendance and marks will be deleted too.`)) return;
    form.delete(`/school/students/${s.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <template #title>Students</template>
        <template #header-actions>
            <button @click="openNew" class="btn-primary btn-sm"><PlusIcon class="h-4 w-4" /> New Student</button>
        </template>

        <div class="flex flex-wrap gap-2 mb-4">
            <select v-model="filters.class_id" class="input w-auto">
                <option value="">All my classes</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <input v-model="filters.search" class="input w-64" placeholder="Search name or admission no." />
        </div>

        <div class="card p-0 overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Adm. No.</th>
                        <th class="table-th">Name</th>
                        <th class="table-th">Sex</th>
                        <th class="table-th">Class</th>
                        <th class="table-th">Guardian</th>
                        <th class="table-th w-20" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in students.data" :key="s.id" class="hover:bg-gray-50" :class="{ 'opacity-50': !s.is_active }">
                        <td class="table-td">{{ s.admission_number }}</td>
                        <td class="table-td font-medium">
                            <Link :href="`/school/students/${s.id}`" class="text-brand-600 hover:underline">{{ s.full_name }}</Link>
                        </td>
                        <td class="table-td">{{ s.gender ?? '—' }}</td>
                        <td class="table-td">{{ s.school_class?.name ?? '—' }}</td>
                        <td class="table-td">
                            {{ s.guardian_name ?? '—' }}
                            <span v-if="s.guardian_phone" class="text-gray-400 text-xs block">{{ s.guardian_phone }}</span>
                        </td>
                        <td class="table-td text-right whitespace-nowrap">
                            <button @click="openEdit(s)" class="text-gray-400 hover:text-blue-600 mr-2"><PencilIcon class="h-4 w-4" /></button>
                            <button v-if="isSchoolAdmin" @click="remove(s)" class="text-gray-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!students.data.length">
                        <td colspan="6" class="table-td text-center text-gray-400 py-8">No students found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="students.last_page > 1" class="flex gap-1 mt-4 flex-wrap">
            <template v-for="l in students.links" :key="l.label">
                <Link v-if="l.url" :href="l.url" preserve-scroll
                    class="px-3 py-1 rounded text-sm border"
                    :class="l.active ? 'bg-brand-600 text-white border-brand-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                    v-html="l.label" />
            </template>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                <h3 class="font-semibold text-gray-900 mb-4">{{ editing ? 'Edit' : 'New' }} Student</h3>
                <form @submit.prevent="submit" class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="label">Admission number</label>
                        <input v-model="form.admission_number" class="input" required />
                        <p v-if="form.errors.admission_number" class="text-xs text-red-600 mt-1">{{ form.errors.admission_number }}</p>
                    </div>
                    <div>
                        <label class="label">First name</label>
                        <input v-model="form.first_name" class="input" required />
                    </div>
                    <div>
                        <label class="label">Surname</label>
                        <input v-model="form.surname" class="input" required />
                    </div>
                    <div>
                        <label class="label">Sex</label>
                        <select v-model="form.gender" class="input">
                            <option value="">—</option>
                            <option value="F">Female</option>
                            <option value="M">Male</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Date of birth</label>
                        <input v-model="form.date_of_birth" type="date" class="input" />
                    </div>
                    <div class="col-span-2">
                        <label class="label">Class</label>
                        <select v-model="form.school_class_id" class="input" :required="!isSchoolAdmin">
                            <option value="">— Not placed —</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Guardian name</label>
                        <input v-model="form.guardian_name" class="input" />
                    </div>
                    <div>
                        <label class="label">Guardian phone</label>
                        <input v-model="form.guardian_phone" class="input" />
                    </div>
                    <label class="col-span-2 flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300" /> Currently enrolled
                    </label>
                    <div class="col-span-2 flex gap-2 justify-end pt-1">
                        <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
