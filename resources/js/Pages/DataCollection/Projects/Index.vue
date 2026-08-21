<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ projects: Array, users: Array, isManager: Boolean });

const showForm = ref(false);
const form = useForm({ code: '', name: '', description: '', start_date: '', end_date: '', owner_id: '' });

function submit() {
    form.post('/data-collection/projects', { onSuccess: () => { showForm.value = false; form.reset(); } });
}

function open(p) {
    router.get(`/data-collection/projects/${p.id}`);
}

const statusColor = {
    draft: 'bg-gray-100 text-gray-700',
    active: 'bg-green-100 text-green-800',
    completed: 'bg-blue-100 text-blue-800',
    archived: 'bg-gray-200 text-gray-500',
};
</script>

<template>
    <AppLayout>
        <template #title>Data Collection Projects</template>
        <template #header-actions>
            <button v-if="isManager" @click="showForm = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Project
            </button>
        </template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Code</th>
                        <th class="table-th">Name</th>
                        <th class="table-th">Owner</th>
                        <th class="table-th">Forms</th>
                        <th class="table-th">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="p in projects" :key="p.id" class="hover:bg-gray-50 cursor-pointer" @click="open(p)">
                        <td class="table-td font-medium">{{ p.code }}</td>
                        <td class="table-td">{{ p.name }}</td>
                        <td class="table-td">{{ p.owner?.name ?? '—' }}</td>
                        <td class="table-td">{{ p.forms_count }}</td>
                        <td class="table-td"><span :class="['badge', statusColor[p.status]]">{{ p.status }}</span></td>
                    </tr>
                    <tr v-if="!projects.length">
                        <td colspan="5" class="table-td text-center text-gray-400 py-8">No data collection projects yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">New Project</h3>
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
                        <label class="label">Owner</label>
                        <select v-model="form.owner_id" class="input">
                            <option value="">None</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Start Date</label>
                            <input v-model="form.start_date" type="date" class="input" />
                        </div>
                        <div>
                            <label class="label">End Date</label>
                            <input v-model="form.end_date" type="date" class="input" />
                        </div>
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
