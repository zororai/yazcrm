<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ categories: Array });

const showForm = ref(false);
const editing = ref(null);
const form = useForm({ name: '', parent_id: '' });

function openNew() {
    editing.value = null;
    form.reset();
    showForm.value = true;
}

function openEdit(c) {
    editing.value = c;
    form.name = c.name;
    form.parent_id = c.parent_id ?? '';
    showForm.value = true;
}

function submit() {
    if (editing.value) {
        form.put(`/registry/categories/${editing.value.id}`, { onSuccess: () => { showForm.value = false; } });
    } else {
        form.post('/registry/categories', { onSuccess: () => { showForm.value = false; } });
    }
}

function remove(c) {
    if (!confirm(`Delete category "${c.name}"?`)) return;
    form.delete(`/registry/categories/${c.id}`);
}
</script>

<template>
    <AppLayout>
        <template #title>IT Asset Categories</template>
        <template #header-actions>
            <Link href="/registry" class="btn-secondary btn-sm">Back to Register</Link>
            <button @click="openNew" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Category
            </button>
        </template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Name</th>
                        <th class="table-th">Parent</th>
                        <th class="table-th">Assets</th>
                        <th class="table-th w-20" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="c in categories" :key="c.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium">{{ c.name }}</td>
                        <td class="table-td">{{ c.parent?.name ?? '—' }}</td>
                        <td class="table-td">{{ c.assets_count }}</td>
                        <td class="table-td text-right">
                            <button @click="openEdit(c)" class="text-gray-400 hover:text-blue-600 mr-2"><PencilIcon class="h-4 w-4" /></button>
                            <button @click="remove(c)" class="text-gray-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!categories.length">
                        <td colspan="4" class="table-td text-center text-gray-400 py-8">No categories yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">{{ editing ? 'Edit' : 'New' }} Category</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="label">Name</label>
                        <input v-model="form.name" class="input" required />
                    </div>
                    <div>
                        <label class="label">Parent Category</label>
                        <select v-model="form.parent_id" class="input">
                            <option value="">None</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id" :disabled="editing && c.id === editing.id">{{ c.name }}</option>
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
