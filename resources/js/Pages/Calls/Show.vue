<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon, LinkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ call: Object, clients: Array });

const showLinkModal = ref(false);
const linkForm = useForm({ client_id: props.call.client?.id ?? '' });

function linkClient() {
    linkForm.post(`/calls/${props.call.id}/link-client`, {
        onSuccess: () => { showLinkModal.value = false; },
    });
}

function fmt(s) {
    if (!s) return '—';
    return `${Math.floor(s / 60)}m ${s % 60}s`;
}

const statusColor = {
    answered: 'bg-green-100 text-green-800',
    missed:   'bg-red-100 text-red-800',
    voicemail:'bg-yellow-100 text-yellow-800',
};
</script>

<template>
    <AppLayout>
        <template #title>Call Detail</template>

        <div class="max-w-4xl space-y-6">
            <Link href="/calls" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeftIcon class="h-4 w-4" /> Back to calls
            </Link>

            <!-- Main info -->
            <div class="card">
                <div class="flex items-start justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ call.caller }} → {{ call.callee }}
                    </h2>
                    <span :class="['badge', statusColor[call.status] ?? 'bg-gray-100 text-gray-700']">
                        {{ call.status }}
                    </span>
                </div>
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div><dt class="text-gray-500">Direction</dt><dd class="font-medium capitalize">{{ call.direction }}</dd></div>
                    <div><dt class="text-gray-500">Duration</dt><dd class="font-medium">{{ fmt(call.duration) }}</dd></div>
                    <div><dt class="text-gray-500">Extension</dt><dd class="font-medium">{{ call.extension_number ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Agent</dt><dd class="font-medium">{{ call.agent?.name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Started</dt><dd class="font-medium">{{ new Date(call.started_at).toLocaleString() }}</dd></div>
                    <div><dt class="text-gray-500">Ended</dt><dd class="font-medium">{{ call.ended_at ? new Date(call.ended_at).toLocaleString() : '—' }}</dd></div>
                </dl>
            </div>

            <!-- Client -->
            <div class="card">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-800">Client</h3>
                    <button @click="showLinkModal = true" class="btn-secondary btn-sm">
                        <LinkIcon class="h-4 w-4" /> {{ call.client ? 'Change' : 'Link client' }}
                    </button>
                </div>
                <template v-if="call.client">
                    <p class="font-medium text-gray-900">{{ call.client.name }}</p>
                    <p class="text-sm text-gray-500">{{ call.client.phone }}</p>
                    <Link :href="`/clients/${call.client.id}`" class="text-sm text-brand-600 hover:underline mt-1 inline-block">
                        View client profile →
                    </Link>
                </template>
                <p v-else class="text-sm text-gray-400">No client linked.</p>
            </div>

            <!-- Recording -->
            <div v-if="call.recording" class="card">
                <h3 class="font-semibold text-gray-800 mb-3">Recording</h3>
                <audio controls class="w-full" :src="`/recordings/${call.recording.id}/download`" />
            </div>

            <!-- Related ticket -->
            <div v-if="call.ticket" class="card">
                <h3 class="font-semibold text-gray-800 mb-3">Related Ticket</h3>
                <Link :href="`/tickets/${call.ticket.id}`" class="text-brand-600 hover:underline font-medium">
                    #{{ call.ticket.id }} — {{ call.ticket.subject }}
                </Link>
            </div>
        </div>

        <!-- Link client modal -->
        <div v-if="showLinkModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Link Client</h3>
                <form @submit.prevent="linkClient">
                    <label class="label">Select client</label>
                    <select v-model="linkForm.client_id" class="input mb-4" required>
                        <option value="">— choose —</option>
                        <option v-for="c in clients" :key="c.id" :value="c.id">
                            {{ c.name }} ({{ c.phone }})
                        </option>
                    </select>
                    <div class="flex gap-2 justify-end">
                        <button type="button" @click="showLinkModal = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="linkForm.processing">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
