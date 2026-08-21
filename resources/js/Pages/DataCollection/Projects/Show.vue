<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ project: Object, forms: Array, isManager: Boolean });

const showForm = ref(false);
const form = useForm({ code: '', name: '', description: '' });

function submit() {
    form.post(`/data-collection/projects/${props.project.id}/forms`, {
        onSuccess: () => { showForm.value = false; form.reset(); },
    });
}

function open(f) {
    router.get(`/data-collection/forms/${f.id}`);
}

const statusColor = {
    draft: 'bg-gray-100 text-gray-700',
    published: 'bg-green-100 text-green-800',
    unpublished: 'bg-amber-100 text-amber-800',
    archived: 'bg-gray-200 text-gray-500',
};
</script>

<template>
    <AppLayout>
        <template #title>{{ project.name }}</template>
        <template #header-actions>
            <button v-if="isManager" @click="showForm = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Form
            </button>
        </template>

        <div class="card mb-4 text-sm text-gray-600">
            <p><span class="text-gray-400">Code:</span> {{ project.code }}</p>
            <p v-if="project.description" class="mt-1">{{ project.description }}</p>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Code</th>
                        <th class="table-th">Name</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Current Version</th>
                        <th class="table-th">Versions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="f in forms" :key="f.id" class="hover:bg-gray-50 cursor-pointer" @click="open(f)">
                        <td class="table-td font-medium">{{ f.code }}</td>
                        <td class="table-td">{{ f.name }}</td>
                        <td class="table-td"><span :class="['badge', statusColor[f.status]]">{{ f.status }}</span></td>
                        <td class="table-td">{{ f.current_version ? `v${f.current_version.version_number}` : '—' }}</td>
                        <td class="table-td">{{ f.versions_count }}</td>
                    </tr>
                    <tr v-if="!forms.length">
                        <td colspan="5" class="table-td text-center text-gray-400 py-8">No forms in this project yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">New Form</h3>
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
                        <label class="label">Description</label>
                        <textarea v-model="form.description" class="input" rows="2"></textarea>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
