<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ board: Object, groups: Array, tasks: Array, counts: Object, users: Array, can: Object });

const filters = ref({ status: '', priority: '', assignee: '', search: '', overdue: false });

let debounce;
watch(filters, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(`/boards/${props.board.id}`, {
            status: filters.value.status || undefined,
            priority: filters.value.priority || undefined,
            assignee: filters.value.assignee || undefined,
            search: filters.value.search || undefined,
            overdue: filters.value.overdue || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}, { deep: true });

const showNew = ref(false);
const newForm = useForm({ board_id: props.board.id, group_id: '', title: '', priority: 'medium', due_date: '' });

function submitNew() {
    newForm.post('/tasks', { onSuccess: () => { showNew.value = false; newForm.reset('title', 'due_date'); } });
}

function open(task) {
    router.get(`/tasks/${task.id}`);
}

const statusColor = {
    not_started: 'bg-gray-100 text-gray-700',
    in_progress: 'bg-blue-100 text-blue-800',
    blocked:     'bg-red-100 text-red-800',
    completed:   'bg-green-100 text-green-800',
    cancelled:   'bg-gray-200 text-gray-500',
};

const priorityColor = {
    low: 'bg-gray-100 text-gray-600',
    medium: 'bg-amber-100 text-amber-800',
    high: 'bg-orange-100 text-orange-800',
    urgent: 'bg-red-100 text-red-800',
};
</script>

<template>
    <AppLayout>
        <template #title>{{ board.name }}</template>
        <template #header-actions>
            <button @click="showNew = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Task
            </button>
        </template>

        <div class="grid grid-cols-5 gap-3 mb-4">
            <div class="card py-3 text-center">
                <p class="text-xl font-semibold text-gray-900">{{ counts.total }}</p>
                <p class="text-xs text-gray-500">Total</p>
            </div>
            <div class="card py-3 text-center">
                <p class="text-xl font-semibold text-gray-900">{{ counts.open }}</p>
                <p class="text-xs text-gray-500">Open</p>
            </div>
            <div class="card py-3 text-center">
                <p class="text-xl font-semibold text-green-700">{{ counts.completed }}</p>
                <p class="text-xs text-gray-500">Completed</p>
            </div>
            <div class="card py-3 text-center">
                <p class="text-xl font-semibold text-red-700">{{ counts.overdue }}</p>
                <p class="text-xs text-gray-500">Overdue</p>
            </div>
            <div class="card py-3 text-center">
                <p class="text-xl font-semibold text-gray-900">{{ counts.mine }}</p>
                <p class="text-xs text-gray-500">My Tasks</p>
            </div>
        </div>

        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="label">Search</label>
                <input v-model="filters.search" class="input" placeholder="Title or description…" />
            </div>
            <div>
                <label class="label">Status</label>
                <select v-model="filters.status" class="input">
                    <option value="">All</option>
                    <option value="not_started">Not Started</option>
                    <option value="in_progress">In Progress</option>
                    <option value="blocked">Blocked</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div>
                <label class="label">Priority</label>
                <select v-model="filters.priority" class="input">
                    <option value="">All</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div>
                <label class="label">Assignee</label>
                <select v-model="filters.assignee" class="input">
                    <option value="">Anyone</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-600 pb-2">
                <input type="checkbox" v-model="filters.overdue" /> Overdue only
            </label>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Task</th>
                        <th class="table-th">Group</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Priority</th>
                        <th class="table-th">Assignees</th>
                        <th class="table-th">Due</th>
                        <th class="table-th">Progress</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="t in tasks" :key="t.id" class="hover:bg-gray-50 cursor-pointer" @click="open(t)">
                        <td class="table-td font-medium">{{ t.title }}</td>
                        <td class="table-td">{{ t.group?.name ?? '—' }}</td>
                        <td class="table-td"><span :class="['badge', statusColor[t.status]]">{{ t.status.replace('_', ' ') }}</span></td>
                        <td class="table-td"><span :class="['badge', priorityColor[t.priority]]">{{ t.priority }}</span></td>
                        <td class="table-td text-xs">{{ t.assignees?.map(a => a.name).join(', ') || '—' }}</td>
                        <td class="table-td text-xs">{{ t.due_date ? new Date(t.due_date).toLocaleDateString() : '—' }}</td>
                        <td class="table-td text-xs">{{ t.progress ?? 0 }}%</td>
                    </tr>
                    <tr v-if="!tasks.length">
                        <td colspan="7" class="table-td text-center text-gray-400 py-8">No tasks match these filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showNew" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">New Task</h3>
                <form @submit.prevent="submitNew" class="space-y-3">
                    <div>
                        <label class="label">Title</label>
                        <input v-model="newForm.title" class="input" required />
                    </div>
                    <div>
                        <label class="label">Group</label>
                        <select v-model="newForm.group_id" class="input">
                            <option value="">None</option>
                            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Priority</label>
                        <select v-model="newForm.priority" class="input">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Due Date</label>
                        <input v-model="newForm.due_date" type="date" class="input" />
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
