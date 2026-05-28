<script setup>
import { ref } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ExclamationTriangleIcon, CheckCircleIcon, TicketIcon,
    PlusIcon, XCircleIcon, PhoneIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({ cases: Object });

// ── Add form ──────────────────────────────────────────────────────────────────
const showAdd = ref(false);
const addForm = useForm({
    subject:        '',
    contact_number: '',
    description:    '',
});

function store() {
    addForm.post('/urgent-cases', {
        onSuccess: () => { showAdd.value = false; addForm.reset(); },
    });
}

function resolve(uc) {
    if (!confirm(`Mark "${uc.subject}" as resolved?`)) return;
    router.patch(`/urgent-cases/${uc.id}/resolve`);
}

function createTicket(uc) {
    if (!confirm(`Create a ticket for "${uc.subject}"?`)) return;
    router.post(`/urgent-cases/${uc.id}/ticket`);
}

const statusColor = {
    open:     'bg-red-100 text-red-700',
    resolved: 'bg-green-100 text-green-700',
};

function fmt(d) {
    return d ? new Date(d).toLocaleString() : '—';
}
</script>

<template>
    <AppLayout>
        <template #title>
            <span class="flex items-center gap-2">
                <ExclamationTriangleIcon class="h-5 w-5 text-red-500" />
                Urgent Cases
            </span>
        </template>
        <template #header-actions>
            <button @click="showAdd = !showAdd" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> Log Case
            </button>
        </template>

        <!-- ── Quick-log form ─────────────────────────────────────────────── -->
        <div v-if="showAdd" class="card mb-4 border-red-200 bg-red-50">
            <h3 class="text-sm font-semibold text-red-700 mb-3 flex items-center gap-2">
                <ExclamationTriangleIcon class="h-4 w-4" /> Log Urgent Case
            </h3>
            <div class="space-y-3">
                <div>
                    <label class="label">Subject *</label>
                    <input v-model="addForm.subject" class="input"
                        :class="{ 'border-red-500': addForm.errors.subject }"
                        placeholder="Brief description of the emergency" autofocus />
                    <p v-if="addForm.errors.subject" class="mt-1 text-xs text-red-600">{{ addForm.errors.subject }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Contact Number</label>
                        <input v-model="addForm.contact_number" class="input" placeholder="Caller's number" />
                    </div>
                    <div>
                        <label class="label">Notes</label>
                        <input v-model="addForm.description" class="input" placeholder="Additional details…" />
                    </div>
                </div>
                <div class="flex gap-2">
                    <button @click="store" :disabled="addForm.processing" class="btn-primary">
                        <CheckCircleIcon class="h-4 w-4" /> Save
                    </button>
                    <button @click="showAdd = false; addForm.reset();" class="btn-secondary">
                        <XCircleIcon class="h-4 w-4" /> Cancel
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Cases table ────────────────────────────────────────────────── -->
        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Subject</th>
                        <th class="table-th">Contact</th>
                        <th class="table-th">Logged By</th>
                        <th class="table-th">Time</th>
                        <th class="table-th text-center">Status</th>
                        <th class="table-th">Ticket</th>
                        <th class="table-th w-36">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!cases.data.length">
                        <td colspan="7" class="py-12 text-center text-sm text-gray-400">
                            No urgent cases.
                        </td>
                    </tr>
                    <tr v-for="uc in cases.data" :key="uc.id"
                        class="hover:bg-gray-50"
                        :class="uc.status === 'open' ? 'bg-red-50/40' : ''">
                        <td class="table-td font-medium max-w-xs">
                            <span v-if="uc.status === 'open'" class="inline-block h-2 w-2 rounded-full bg-red-500 mr-1.5 animate-pulse"></span>
                            {{ uc.subject }}
                            <p v-if="uc.description" class="text-xs text-gray-400 truncate mt-0.5">{{ uc.description }}</p>
                        </td>
                        <td class="table-td text-sm">
                            <span v-if="uc.contact_number" class="flex items-center gap-1 text-gray-600">
                                <PhoneIcon class="h-3.5 w-3.5 text-gray-400" />
                                {{ uc.contact_number }}
                            </span>
                            <span v-else class="text-gray-400">—</span>
                        </td>
                        <td class="table-td text-sm text-gray-600">{{ uc.agent?.name ?? '—' }}</td>
                        <td class="table-td text-xs text-gray-500 whitespace-nowrap">{{ fmt(uc.created_at) }}</td>
                        <td class="table-td text-center">
                            <span :class="['badge', statusColor[uc.status] ?? 'bg-gray-100 text-gray-600']">
                                {{ uc.status }}
                            </span>
                        </td>
                        <td class="table-td text-sm">
                            <Link v-if="uc.source_ticket" :href="`/tickets/${uc.source_ticket.id}`"
                                class="text-brand-600 hover:underline text-xs flex items-center gap-1">
                                <TicketIcon class="h-3.5 w-3.5" /> #{{ uc.source_ticket.id }}
                            </Link>
                            <Link v-else-if="uc.created_ticket" :href="`/tickets/${uc.created_ticket.id}`"
                                class="text-brand-600 hover:underline text-xs flex items-center gap-1">
                                <TicketIcon class="h-3.5 w-3.5" /> #{{ uc.created_ticket.id }}
                            </Link>
                            <span v-else class="text-gray-400 text-xs">no ticket</span>
                        </td>
                        <td class="table-td">
                            <div class="flex gap-1">
                                <!-- Open existing ticket -->
                                <Link v-if="uc.source_ticket || uc.created_ticket"
                                    :href="`/tickets/${(uc.source_ticket ?? uc.created_ticket).id}`"
                                    class="btn-secondary btn-sm py-1 text-xs">
                                    <TicketIcon class="h-3.5 w-3.5" /> Ticket
                                </Link>
                                <!-- Create ticket (no ticket yet) -->
                                <button v-else-if="uc.status === 'open'"
                                    @click="createTicket(uc)"
                                    class="btn-primary btn-sm py-1 text-xs">
                                    <TicketIcon class="h-3.5 w-3.5" /> Open Ticket
                                </button>
                                <!-- Resolve -->
                                <button v-if="uc.status === 'open'"
                                    @click="resolve(uc)"
                                    class="p-1.5 rounded text-gray-400 hover:text-green-600 hover:bg-green-50"
                                    title="Mark resolved">
                                    <CheckCircleIcon class="h-4 w-4" />
                                </button>
                                <span v-else class="text-xs text-gray-400">{{ uc.resolved_by?.name ?? '' }}</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="cases.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">Showing {{ cases.from }}–{{ cases.to }} of {{ cases.total }}</p>
                <div class="flex gap-1">
                    <Link v-for="link in cases.links" :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                        v-html="link.label" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
