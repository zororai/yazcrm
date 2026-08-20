<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ locations: Array, isManager: Boolean });

const showForm = ref(false);
const editing = ref(null);
const form = useForm({ code: '', name: '', address: '' });

function openNew() {
    editing.value = null;
    form.reset();
    showForm.value = true;
}

function openEdit(l) {
    editing.value = l;
    form.code = l.code;
    form.name = l.name;
    form.address = l.address ?? '';
    showForm.value = true;
}

function submit() {
    if (editing.value) {
        form.put(`/locations/${editing.value.id}`, { onSuccess: () => { showForm.value = false; } });
    } else {
        form.post('/locations', { onSuccess: () => { showForm.value = false; } });
    }
}

function remove(l) {
    if (!confirm(`Delete location "${l.name}"?`)) return;
    form.delete(`/locations/${l.id}`);
}
</script>

<template>
    <AppLayout>
        <template #title>Locations</template>
        <template #header-actions>
            <button v-if="isManager" @click="openNew" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Location
            </button>
        </template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Code</th>
                        <th class="table-th">Name</th>
                        <th class="table-th">Stores</th>
                        <th class="table-th w-20" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="l in locations" :key="l.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium">{{ l.code }}</td>
                        <td class="table-td">{{ l.name }}</td>
                        <td class="table-td">{{ l.stores_count }}</td>
                        <td class="table-td text-right" v-if="isManager">
                            <button @click="openEdit(l)" class="text-gray-400 hover:text-blue-600 mr-2"><PencilIcon class="h-4 w-4" /></button>
                            <button @click="remove(l)" class="text-gray-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!locations.length">
                        <td colspan="4" class="table-td text-center text-gray-400 py-8">No locations yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">{{ editing ? 'Edit' : 'New' }} Location</h3>
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
                        <label class="label">Address</label>
                        <textarea v-model="form.address" class="input" rows="2"></textarea>
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
