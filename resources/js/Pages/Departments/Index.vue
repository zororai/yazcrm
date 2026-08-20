<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ departments: Array, users: Array, isManager: Boolean });

const showForm = ref(false);
const editing = ref(null);
const form = useForm({ code: '', name: '', manager_id: '' });

function openNew() {
    editing.value = null;
    form.reset();
    showForm.value = true;
}

function openEdit(d) {
    editing.value = d;
    form.code = d.code;
    form.name = d.name;
    form.manager_id = d.manager_id ?? '';
    showForm.value = true;
}

function submit() {
    if (editing.value) {
        form.put(`/departments/${editing.value.id}`, { onSuccess: () => { showForm.value = false; } });
    } else {
        form.post('/departments', { onSuccess: () => { showForm.value = false; } });
    }
}

function remove(d) {
    if (!confirm(`Delete department "${d.name}"?`)) return;
    form.delete(`/departments/${d.id}`);
}
</script>

<template>
    <AppLayout>
        <template #title>Departments</template>
        <template #header-actions>
            <button v-if="isManager" @click="openNew" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Department
            </button>
        </template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Code</th>
                        <th class="table-th">Name</th>
                        <th class="table-th">Manager</th>
                        <th class="table-th w-20" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="d in departments" :key="d.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium">{{ d.code }}</td>
                        <td class="table-td">{{ d.name }}</td>
                        <td class="table-td">{{ d.manager?.name ?? '—' }}</td>
                        <td class="table-td text-right" v-if="isManager">
                            <button @click="openEdit(d)" class="text-gray-400 hover:text-blue-600 mr-2"><PencilIcon class="h-4 w-4" /></button>
                            <button @click="remove(d)" class="text-gray-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!departments.length">
                        <td colspan="4" class="table-td text-center text-gray-400 py-8">No departments yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">{{ editing ? 'Edit' : 'New' }} Department</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="label">Code</label>
                        <input v-model="form.code" class="input" required />
                    </div>
                    <div>
                        <label class="label">Name</label>
                        <input v-model="form.name" class="input" required />
                    </div>
                    <div>
                        <label class="label">Manager</label>
                        <select v-model="form.manager_id" class="input">
                            <option value="">None</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
