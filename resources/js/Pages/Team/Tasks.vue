<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ tasks: Array, members: Array, counts: Object });

const filters = ref({ status: '', priority: '', member: '', overdue: false });

let debounce;
watch(filters, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/team/tasks', {
            status: filters.value.status || undefined,
            priority: filters.value.priority || undefined,
            member: filters.value.member || undefined,
            overdue: filters.value.overdue || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}, { deep: true });

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
        <template #title>My Team's Tasks</template>

        <div class="grid grid-cols-4 gap-3 mb-4">
            <div class="card py-3 text-center">
                <p class="text-xl font-semibold text-gray-900">{{ counts.total }}</p>
                <p class="text-xs text-gray-500">Total</p>
            </div>
            <div class="card py-3 text-center">
                <p class="text-xl font-semibold text-gray-900">{{ counts.open }}</p>
                <p class="text-xs text-gray-500">Open</p>
            </div>
            <div class="card py-3 text-center">
                <p class="text-xl font-semibold text-red-700">{{ counts.overdue }}</p>
                <p class="text-xs text-gray-500">Overdue</p>
            </div>
            <div class="card py-3 text-center">
                <p class="text-xl font-semibold text-green-700">{{ counts.completed }}</p>
                <p class="text-xs text-gray-500">Completed</p>
            </div>
        </div>

        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="label">Team Member</label>
                <select v-model="filters.member" class="input">
                    <option value="">Everyone</option>
                    <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
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
            <label class="flex items-center gap-2 text-sm text-gray-600 pb-2">
                <input type="checkbox" v-model="filters.overdue" /> Overdue only
            </label>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Task</th>
                        <th class="table-th">Board</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Priority</th>
                        <th class="table-th">Assignees</th>
                        <th class="table-th">Due</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="t in tasks" :key="t.id" class="hover:bg-gray-50 cursor-pointer" @click="open(t)">
                        <td class="table-td font-medium">{{ t.title }}</td>
                        <td class="table-td">{{ t.board?.name }}</td>
                        <td class="table-td"><span :class="['badge', statusColor[t.status]]">{{ t.status.replace('_', ' ') }}</span></td>
                        <td class="table-td"><span :class="['badge', priorityColor[t.priority]]">{{ t.priority }}</span></td>
                        <td class="table-td text-xs">{{ t.assignees?.map(a => a.name).join(', ') || '—' }}</td>
                        <td class="table-td text-xs">{{ t.due_date ? new Date(t.due_date).toLocaleDateString() : '—' }}</td>
                    </tr>
                    <tr v-if="!tasks.length">
                        <td colspan="6" class="table-td text-center text-gray-400 py-8">No tasks for your team.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
