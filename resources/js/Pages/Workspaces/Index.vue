<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, FolderIcon } from '@heroicons/vue/24/outline';

defineProps({ workspaces: Array });

const showNew = ref(false);
const newForm = useForm({ name: '', description: '' });

function submitNew() {
    newForm.post('/workspaces', { onSuccess: () => { showNew.value = false; newForm.reset(); } });
}

function open(workspace) {
    router.get(`/workspaces/${workspace.id}`);
}
</script>

<template>
    <AppLayout>
        <template #title>Workspaces</template>
        <template #header-actions>
            <button @click="showNew = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Workspace
            </button>
        </template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Workspace</th>
                        <th class="table-th">Owner</th>
                        <th class="table-th">Boards</th>
                        <th class="table-th w-10" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="w in workspaces" :key="w.id" class="hover:bg-gray-50 cursor-pointer" @click="open(w)">
                        <td class="table-td font-medium">{{ w.name }}</td>
                        <td class="table-td">{{ w.owner?.name ?? '—' }}</td>
                        <td class="table-td">{{ w.boards_count }}</td>
                        <td class="table-td text-right"><FolderIcon class="h-4 w-4 text-gray-400 inline-block" /></td>
                    </tr>
                    <tr v-if="!workspaces.length">
                        <td colspan="4" class="table-td text-center text-gray-400 py-8">No workspaces yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showNew" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">New Workspace</h3>
                <form @submit.prevent="submitNew" class="space-y-3">
                    <div>
                        <label class="label">Name</label>
                        <input v-model="newForm.name" class="input" required />
                    </div>
                    <div>
                        <label class="label">Description</label>
                        <textarea v-model="newForm.description" class="input" rows="2"></textarea>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showNew = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="newForm.processing">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
