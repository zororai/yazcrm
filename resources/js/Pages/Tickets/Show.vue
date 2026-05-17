<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ ticket: Object });

const updateForm = useForm({
    status:   props.ticket.status,
    priority: props.ticket.priority,
});

function save() {
    updateForm.put(`/tickets/${props.ticket.id}`);
}

const priorityColor = {
    low: 'bg-gray-100 text-gray-600', medium: 'bg-blue-100 text-blue-800',
    high: 'bg-orange-100 text-orange-800', urgent: 'bg-red-100 text-red-800',
};
const statusColor = {
    open: 'bg-yellow-100 text-yellow-800', in_progress: 'bg-blue-100 text-blue-800',
    resolved: 'bg-green-100 text-green-800', closed: 'bg-gray-100 text-gray-600',
};

function label(val) {
    if (val === null || val === undefined || val === '') return '—';
    if (val === true  || val === 1) return 'Yes';
    if (val === false || val === 0) return 'No';
    return String(val).replace(/_/g, ' ');
}
</script>

<template>
    <AppLayout>
        <template #title>#{{ ticket.id }} — {{ ticket.subject }}</template>

        <div class="max-w-5xl space-y-6">
            <Link href="/tickets" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeftIcon class="h-4 w-4" /> Back to tickets
            </Link>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: detail panels -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Subject & description -->
                    <div class="card">
                        <div class="flex items-start justify-between mb-3">
                            <h2 class="text-lg font-semibold text-gray-900">{{ ticket.subject }}</h2>
                            <div class="flex gap-2">
                                <span :class="['badge', priorityColor[ticket.priority]]">{{ ticket.priority }}</span>
                                <span :class="['badge', statusColor[ticket.status]]">{{ ticket.status.replace('_', ' ') }}</span>
                            </div>
                        </div>
                        <p v-if="ticket.description" class="text-sm text-gray-600 whitespace-pre-wrap">{{ ticket.description }}</p>
                        <p v-else class="text-sm text-gray-400 italic">No counsellor's notes.</p>
                    </div>

                    <!-- Call details -->
                    <div class="card">
                        <h3 class="font-semibold text-gray-800 mb-3 text-sm">Call Details</h3>
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                            <div>
                                <dt class="text-gray-400 text-xs">Mode of Communication</dt>
                                <dd class="font-medium capitalize">{{ label(ticket.mode_of_communication) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400 text-xs">Call Validity</dt>
                                <dd class="font-medium capitalize">{{ label(ticket.call_validity) }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-gray-400 text-xs">Purpose of Call</dt>
                                <dd class="font-medium">{{ label(ticket.purpose_of_call) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400 text-xs">Project</dt>
                                <dd class="font-medium">{{ label(ticket.project) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400 text-xs">Immediate Action Required</dt>
                                <dd :class="ticket.immediate_action_required ? 'text-red-600 font-semibold' : 'font-medium'">
                                    {{ label(ticket.immediate_action_required) }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Caller info -->
                    <div class="card">
                        <h3 class="font-semibold text-gray-800 mb-3 text-sm">Caller Information</h3>
                        <dl class="grid grid-cols-3 gap-x-6 gap-y-2 text-sm">
                            <div>
                                <dt class="text-gray-400 text-xs">Age</dt>
                                <dd class="font-medium">{{ label(ticket.caller_age) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400 text-xs">Gender</dt>
                                <dd class="font-medium capitalize">{{ label(ticket.caller_gender) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400 text-xs">Marital Status</dt>
                                <dd class="font-medium capitalize">{{ label(ticket.caller_marital_status) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400 text-xs">Key Pops</dt>
                                <dd class="font-medium">{{ label(ticket.key_pops) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400 text-xs">New / Repeat</dt>
                                <dd class="font-medium">{{ ticket.is_repeat_caller ? 'Repeat' : 'New' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Location -->
                    <div class="card">
                        <h3 class="font-semibold text-gray-800 mb-3 text-sm">Location</h3>
                        <dl class="grid grid-cols-3 gap-x-6 gap-y-2 text-sm">
                            <div>
                                <dt class="text-gray-400 text-xs">Province</dt>
                                <dd class="font-medium">{{ label(ticket.province) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400 text-xs">District</dt>
                                <dd class="font-medium">{{ label(ticket.district) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400 text-xs">Location</dt>
                                <dd class="font-medium">{{ label(ticket.location) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Services -->
                    <div class="card">
                        <h3 class="font-semibold text-gray-800 mb-3 text-sm">Services</h3>
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                            <div>
                                <dt class="text-gray-400 text-xs">Services Requested</dt>
                                <dd class="font-medium">{{ label(ticket.services_requested) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400 text-xs">Second Service</dt>
                                <dd class="font-medium">{{ label(ticket.second_service_requested) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400 text-xs">No. of Services</dt>
                                <dd class="font-medium">{{ label(ticket.number_of_services) }}</dd>
                            </div>
                            <div>
                                <dt class="text-gray-400 text-xs">Referred To</dt>
                                <dd class="font-medium">{{ label(ticket.referred_to) }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-gray-400 text-xs">Uptake Confirmed</dt>
                                <dd class="font-medium">{{ label(ticket.uptake_confirmed) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Related call -->
                    <div v-if="ticket.call" class="card">
                        <h3 class="font-semibold text-gray-800 mb-2 text-sm">Related Call</h3>
                        <Link :href="`/calls/${ticket.call.id}`" class="text-brand-600 hover:underline text-sm">
                            {{ ticket.call.caller }} → {{ ticket.call.callee }} ({{ new Date(ticket.call.started_at).toLocaleString() }})
                        </Link>
                    </div>
                </div>

                <!-- Right sidebar -->
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
