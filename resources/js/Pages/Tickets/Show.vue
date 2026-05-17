<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ ticket: Object, agents: Array });

const updateForm = useForm({
    status:   props.ticket.status,
    priority: props.ticket.priority,
    agent_id: props.ticket.agent_id ?? '',
});

function save() {
    updateForm.put(`/tickets/${props.ticket.id}`);
}

function destroy() {
    if (!confirm('Delete this ticket?')) return;
    router.delete(`/tickets/${props.ticket.id}`);
}

const priorityColor = {
    low: 'bg-gray-100 text-gray-600', medium: 'bg-blue-100 text-blue-800',
    high: 'bg-orange-100 text-orange-800', urgent: 'bg-red-100 text-red-800',
};
const statusColor = {
    open: 'bg-yellow-100 text-yellow-800', in_progress: 'bg-blue-100 text-blue-800',
    resolved: 'bg-green-100 text-green-800', closed: 'bg-gray-100 text-gray-600',
};
</script>

<template>
    <AppLayout>
        <template #title>#{{ ticket.id }} — {{ ticket.subject }}</template>
        <template #header-actions>
            <button @click="destroy" class="btn-danger btn-sm">
                <TrashIcon class="h-4 w-4" /> Delete
            </button>
        </template>

        <div class="max-w-4xl space-y-6">
            <Link href="/tickets" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeftIcon class="h-4 w-4" /> Back to tickets
            </Link>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Detail -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="card">
                        <div class="flex items-start justify-between mb-3">
                            <h2 class="text-lg font-semibold text-gray-900">{{ ticket.subject }}</h2>
                            <div class="flex gap-2">
                                <span :class="['badge', priorityColor[ticket.priority]]">{{ ticket.priority }}</span>
                                <span :class="['badge', statusColor[ticket.status]]">{{ ticket.status.replace('_', ' ') }}</span>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ ticket.description ?? 'No description.' }}</p>
                    </div>

                    <div v-if="ticket.call" class="card">
                        <h3 class="font-semibold text-gray-800 mb-2">Related Call</h3>
                        <Link :href="`/calls/${ticket.call.id}`" class="text-brand-600 hover:underline text-sm">
                            {{ ticket.call.caller }} → {{ ticket.call.callee }} ({{ new Date(ticket.call.started_at).toLocaleString() }})
                        </Link>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-4">
                    <!-- Client -->
                    <div class="card">
                        <h3 class="font-semibold text-gray-800 mb-2 text-sm">Client</h3>
                        <template v-if="ticket.client">
                            <Link :href="`/clients/${ticket.client.id}`" class="font-medium text-brand-600 hover:underline">{{ ticket.client.name }}</Link>
                            <p class="text-xs text-gray-500 mt-0.5">{{ ticket.client.phone }}</p>
                        </template>
                        <p v-else class="text-sm text-gray-400">No client.</p>
                    </div>

                    <!-- Update form -->
                    <div class="card">
                        <h3 class="font-semibold text-gray-800 mb-3 text-sm">Update</h3>
                        <form @submit.prevent="save" class="space-y-3">
                            <div>
                                <label class="label">Status</label>
                                <select v-model="updateForm.status" class="input">
                                    <option value="open">Open</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Priority</label>
                                <select v-model="updateForm.priority" class="input">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Assign Agent</label>
                                <select v-model="updateForm.agent_id" class="input">
                                    <option value="">— none —</option>
                                    <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.name }}</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-primary w-full justify-center" :disabled="updateForm.processing">
                                Save Changes
                            </button>
                        </form>
                    </div>

                    <!-- Meta -->
                    <div class="card text-xs text-gray-500 space-y-1">
                        <div>Created: {{ new Date(ticket.created_at).toLocaleString() }}</div>
                        <div v-if="ticket.resolved_at">Resolved: {{ new Date(ticket.resolved_at).toLocaleString() }}</div>
                        <div>Agent: {{ ticket.agent?.name ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
