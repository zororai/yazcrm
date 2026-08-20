<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { TrashIcon, ArrowUturnLeftIcon, ArchiveBoxIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    task: Object,
    progress: Number,
    can: Object,
    activityLogs: { type: Array, default: () => [] },
});

const editForm = useForm({
    title:       props.task.title,
    description: props.task.description ?? '',
    start_date:  props.task.start_date ? String(props.task.start_date).slice(0, 10) : '',
    due_date:    props.task.due_date ? String(props.task.due_date).slice(0, 10) : '',
});

function saveEdits() {
    editForm.put(`/tasks/${props.task.id}`);
}

function changeStatus(e) {
    router.post(`/tasks/${props.task.id}/status`, { status: e.target.value });
}

function changePriority(e) {
    router.post(`/tasks/${props.task.id}/priority`, { priority: e.target.value });
}

function reopenTask() {
    const reason = prompt('Why are you reopening this task?');
    if (!reason) return;
    router.post(`/tasks/${props.task.id}/reopen`, { reason });
}

function archiveTask() {
    router.post(`/tasks/${props.task.id}/archive`);
}

function restoreTask() {
    router.post(`/tasks/${props.task.id}/restore`);
}

function deleteTask() {
    if (!confirm('Permanently delete this task?')) return;
    router.delete(`/tasks/${props.task.id}`);
}

const commentForm = useForm({ comment: '' });
function addComment() {
    commentForm.post(`/tasks/${props.task.id}/comments`, { onSuccess: () => commentForm.reset() });
}

const statusColor = {
    not_started: 'bg-gray-100 text-gray-700',
    in_progress: 'bg-blue-100 text-blue-800',
    blocked:     'bg-red-100 text-red-800',
    completed:   'bg-green-100 text-green-800',
    cancelled:   'bg-gray-200 text-gray-500',
};
</script>

<template>
    <AppLayout>
        <template #title>{{ task.title }}</template>
        <template #header-actions>
            <div class="flex gap-2">
                <button v-if="!task.is_archived && can.archive" @click="archiveTask" class="btn-secondary btn-sm">
                    <ArchiveBoxIcon class="h-4 w-4" /> Archive
                </button>
                <button v-if="task.is_archived && can.restore" @click="restoreTask" class="btn-secondary btn-sm">
                    <ArrowUturnLeftIcon class="h-4 w-4" /> Restore
                </button>
                <button v-if="task.status === 'completed' && can.changeStatus" @click="reopenTask" class="btn-secondary btn-sm">
                    <ArrowUturnLeftIcon class="h-4 w-4" /> Reopen
                </button>
                <button v-if="can.delete" @click="deleteTask" class="btn-danger btn-sm"><TrashIcon class="h-4 w-4" /> Delete</button>
            </div>
        </template>

        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2 space-y-4">
                <div class="card space-y-3">
                    <div class="flex items-center gap-3">
                        <span :class="['badge', statusColor[task.status]]">{{ task.status.replace('_', ' ') }}</span>
                        <span class="text-xs text-gray-400">Progress: {{ progress }}%</span>
                    </div>
                    <div>
                        <label class="label">Title</label>
                        <input v-model="editForm.title" class="input" :disabled="!can.update" />
                    </div>
                    <div>
                        <label class="label">Description</label>
                        <textarea v-model="editForm.description" class="input" rows="4" :disabled="!can.update"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">Start Date</label>
                            <input v-model="editForm.start_date" type="date" class="input" :disabled="!can.update" />
                        </div>
                        <div>
                            <label class="label">Due Date</label>
                            <input v-model="editForm.due_date" type="date" class="input" :disabled="!can.update" />
                        </div>
                    </div>
                    <div v-if="can.update" class="flex justify-end">
                        <button @click="saveEdits" class="btn-secondary btn-sm" :disabled="editForm.processing">Save</button>
                    </div>
                </div>

                <div class="card">
                    <h3 class="font-semibold text-gray-900 mb-3">Subtasks</h3>
                    <ul v-if="task.subtasks?.length" class="space-y-1 text-sm">
                        <li v-for="s in task.subtasks" :key="s.id" class="flex items-center gap-2">
                            <span :class="['badge', statusColor[s.status]]">{{ s.status.replace('_', ' ') }}</span>
                            {{ s.title }}
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-400">No subtasks.</p>
                </div>

                <div class="card">
                    <h3 class="font-semibold text-gray-900 mb-3">Updates</h3>
                    <div class="space-y-3 mb-4">
                        <div v-for="c in task.comments" :key="c.id" class="text-sm border-b border-gray-50 pb-2">
                            <p class="font-medium text-gray-900">{{ c.user?.name }}</p>
                            <p class="text-gray-600">{{ c.comment }}</p>
                            <p class="text-xs text-gray-400">{{ new Date(c.created_at).toLocaleString() }}</p>
                        </div>
                        <p v-if="!task.comments?.length" class="text-sm text-gray-400">No updates yet.</p>
                    </div>
                    <form @submit.prevent="addComment" class="flex gap-2">
                        <input v-model="commentForm.comment" class="input flex-1" placeholder="Add an update…" />
                        <button type="submit" class="btn-primary btn-sm" :disabled="commentForm.processing">Post</button>
                    </form>
                </div>

                <details v-if="can.manage && activityLogs.length" class="card">
                    <summary class="font-semibold text-gray-900 cursor-pointer">History</summary>
                    <ul class="mt-3 space-y-2 text-sm text-gray-600">
                        <li v-for="log in activityLogs" :key="log.id">
                            <span class="font-medium text-gray-900">{{ log.user?.name ?? 'Unknown' }}</span>
                            {{ log.action }}
                            <span v-if="log.old_status && log.new_status">({{ log.old_status }} → {{ log.new_status }})</span>
                            <span class="text-gray-400">— {{ new Date(log.created_at).toLocaleString() }}</span>
                            <p v-if="log.reason" class="text-xs text-gray-500 italic">Reason: {{ log.reason }}</p>
                        </li>
                    </ul>
                </details>
            </div>

            <div class="space-y-4">
                <div class="card space-y-3">
                    <div>
                        <label class="label">Status</label>
                        <select :value="task.status" @change="changeStatus" class="input" :disabled="!can.changeStatus">
                            <option value="not_started">Not Started</option>
                            <option value="in_progress">In Progress</option>
                            <option value="blocked">Blocked</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Priority</label>
                        <select :value="task.priority" @change="changePriority" class="input" :disabled="!can.update">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div class="card">
                    <h3 class="font-semibold text-gray-900 mb-2 text-sm">Assignees</h3>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li v-for="a in task.assignees" :key="a.id">{{ a.name }}</li>
                        <li v-if="!task.assignees?.length" class="text-gray-400">Unassigned</li>
                    </ul>
                </div>

                <div class="card text-sm text-gray-600 space-y-1">
                    <p><span class="text-gray-400">Board:</span> {{ task.board?.name }}</p>
                    <p><span class="text-gray-400">Group:</span> {{ task.group?.name ?? '—' }}</p>
                    <p><span class="text-gray-400">Created by:</span> {{ task.creator?.name }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
