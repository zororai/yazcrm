<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, TableCellsIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ workspace: Object, boards: Array, can: Object });

const showNew = ref(false);
const newForm = useForm({ workspace_id: props.workspace.id, name: '', description: '' });

function submitNew() {
    newForm.post('/boards', { onSuccess: () => { showNew.value = false; newForm.reset('name', 'description'); } });
}

function open(board) {
    router.get(`/boards/${board.id}`);
}
</script>

<template>
    <AppLayout>
        <template #title>{{ workspace.name }}</template>
        <template #header-actions>
            <button @click="showNew = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Board
            </button>
        </template>

        <p v-if="workspace.description" class="text-sm text-gray-500 mb-4">{{ workspace.description }}</p>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Board</th>
                        <th class="table-th">Owner</th>
                        <th class="table-th">Tasks</th>
                        <th class="table-th w-10" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="b in boards" :key="b.id" class="hover:bg-gray-50 cursor-pointer" @click="open(b)">
                        <td class="table-td font-medium">{{ b.name }}</td>
                        <td class="table-td">{{ b.owner?.name ?? '—' }}</td>
                        <td class="table-td">{{ b.tasks_count }}</td>
                        <td class="table-td text-right"><TableCellsIcon class="h-4 w-4 text-gray-400 inline-block" /></td>
                    </tr>
                    <tr v-if="!boards.length">
                        <td colspan="4" class="table-td text-center text-gray-400 py-8">No boards yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showNew" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">New Board</h3>
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
