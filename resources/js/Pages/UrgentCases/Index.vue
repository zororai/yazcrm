<script setup>
import { ref, computed } from 'vue';
import { router, useForm, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ExclamationTriangleIcon, CheckCircleIcon, TicketIcon,
    PlusIcon, XCircleIcon, PhoneIcon, PencilSquareIcon, XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    cases: Object,
    keyPops: Array,
    modesOfCommunication: Array,
    projects: Array,
    servicesRequested: Array,
    secondServicesRequested: Array,
    referredTo: Array,
});

const distressDomains = computed(() => usePage().props.distressDomains ?? []);

const provinces = [
    'Bulawayo', 'Harare', 'Manicaland', 'Mashonaland Central',
    'Mashonaland East', 'Mashonaland West', 'Masvingo',
    'Matabeleland North', 'Matabeleland South', 'Midlands',
];

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

// ── Edit ticket modal ─────────────────────────────────────────────────────────
const editTicket    = ref(null);   // the ticket object
const editUrgentId  = ref(null);   // the urgent case id (for context)

const editForm = useForm({
    subject:                   '',
    contact_number:            '',
    sisters_number:            '',
    description:               '',
    status:                    'open',
    priority:                  'medium',
    follow_up_date:            '',
    mode_of_communication:     '',
    call_validity:             '',
    purpose_of_call:           '',
    immediate_action_required: '',
    action_status:             '',
    caller_age:                '',
    caller_gender:             '',
    caller_marital_status:     '',
    key_pops:                  '',
    province:                  '',
    district:                  '',
    location:                  '',
    is_repeat_caller:          '',
    project:                   '',
    services_requested_before: '',
    services_requested:        '',
    second_service_requested:  '',
    number_of_services:        '',
    referred_to:               '',
    uptake_confirmed:          false,
    referral_uptake_date:      '',
});

function openEdit(uc) {
    const t = uc.source_ticket ?? uc.created_ticket;
    if (!t) return;
    editTicket.value   = t;
    editUrgentId.value = uc.id;

    editForm.subject                   = t.subject                   ?? '';
    editForm.contact_number            = t.contact_number            ?? '';
    editForm.sisters_number            = t.sisters_number            ?? '';
    editForm.description               = t.description               ?? '';
    editForm.status                    = t.status                    ?? 'open';
    editForm.priority                  = t.priority                  ?? 'medium';
    editForm.follow_up_date            = t.follow_up_date            ?? '';
    editForm.mode_of_communication     = t.mode_of_communication     ?? '';
    editForm.call_validity             = t.call_validity             ?? '';
    editForm.purpose_of_call           = t.purpose_of_call           ?? '';
    editForm.immediate_action_required = t.immediate_action_required ? '1' : (t.immediate_action_required === false ? '0' : '');
    editForm.action_status             = t.action_status             ?? '';
    editForm.caller_age                = t.caller_age                ?? '';
    editForm.caller_gender             = t.caller_gender             ?? '';
    editForm.caller_marital_status     = t.caller_marital_status     ?? '';
    editForm.key_pops                  = t.key_pops                  ?? '';
    editForm.province                  = t.province                  ?? '';
    editForm.district                  = t.district                  ?? '';
    editForm.location                  = t.location                  ?? '';
    editForm.is_repeat_caller          = t.is_repeat_caller ? '1' : (t.is_repeat_caller === false ? '0' : '');
    editForm.project                   = t.project                   ?? '';
    editForm.services_requested_before = t.services_requested_before ?? '';
    editForm.services_requested        = t.services_requested        ?? '';
    editForm.second_service_requested  = t.second_service_requested  ?? '';
    editForm.number_of_services        = t.number_of_services        ?? '';
    editForm.referred_to               = t.referred_to               ?? '';
    editForm.uptake_confirmed          = !!t.uptake_confirmed;
    editForm.referral_uptake_date      = t.referral_uptake_date      ?? '';
}

function closeEdit() {
    editTicket.value  = null;
    editUrgentId.value = null;
    editForm.reset();
}

function saveTicket() {
    editForm.put(`/tickets/${editTicket.value.id}`, {
        onSuccess: () => closeEdit(),
    });
}

// ── Actions ───────────────────────────────────────────────────────────────────
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
                        <th class="table-th w-44">Actions</th>
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
                                <!-- Edit ticket -->
                                <button v-if="uc.source_ticket || uc.created_ticket"
                                    @click="openEdit(uc)"
                                    class="btn-primary btn-sm py-1 text-xs"
                                    title="Edit ticket">
                                    <PencilSquareIcon class="h-3.5 w-3.5" /> Edit
                                </button>
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

    <!-- ── Edit Ticket Modal ──────────────────────────────────────────────────── -->
    <div v-if="editTicket" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                <h3 class="font-semibold text-gray-900">
                    Edit Ticket #{{ editTicket.id }}
                </h3>
                <button @click="closeEdit" class="text-gray-400 hover:text-gray-600">
                    <XMarkIcon class="h-5 w-5" />
                </button>
            </div>

            <!-- Scrollable body -->
            <form @submit.prevent="saveTicket" class="overflow-y-auto flex-1 px-6 py-4 space-y-5">

                <!-- ── Basic Info ── -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Basic Info</h4>
                    <div class="space-y-3">
                        <div>
                            <label class="label">Name *</label>
                            <input v-model="editForm.subject" class="input"
                                :class="{ 'border-red-500': editForm.errors.subject }" required />
                            <p v-if="editForm.errors.subject" class="mt-1 text-xs text-red-600">{{ editForm.errors.subject }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">Contact Number</label>
                                <input v-model="editForm.contact_number" class="input" />
                            </div>
                            <div>
                                <label class="label">Sisters Number</label>
                                <input v-model="editForm.sisters_number" class="input" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">Priority</label>
                                <select v-model="editForm.priority" class="input">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Status</label>
                                <select v-model="editForm.status" class="input">
                                    <option value="open">Open</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="label">Follow-up Date</label>
                            <input v-model="editForm.follow_up_date" type="date" class="input w-48" />
                        </div>
                    </div>
                </div>

                <!-- ── Call Details ── -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Call Details</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">Mode of Communication</label>
                            <select v-model="editForm.mode_of_communication" class="input">
                                <option value="">— select —</option>
                                <option v-for="m in props.modesOfCommunication" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Call Validity</label>
                            <select v-model="editForm.call_validity" class="input">
                                <option value="">— select —</option>
                                <option value="valid">Valid</option>
                                <option value="invalid">Invalid</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="label">Purpose of Call</label>
                            <select v-model="editForm.purpose_of_call" class="input">
                                <option value="">— select —</option>
                                <option v-for="d in distressDomains" :key="d" :value="d">{{ d }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Project</label>
                            <select v-model="editForm.project" class="input">
                                <option value="">— select —</option>
                                <option v-for="p in props.projects" :key="p" :value="p">{{ p }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Action Status</label>
                            <select v-model="editForm.action_status" class="input">
                                <option value="">— select —</option>
                                <option value="yes">Yes</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="pending">Pending</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Immediate Action Required</label>
                            <select v-model="editForm.immediate_action_required" class="input">
                                <option value="">— select —</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                        </div>
                        <div v-if="editForm.immediate_action_required == 1">
                            <label class="label">Referral Uptake Date</label>
                            <input v-model="editForm.referral_uptake_date" type="date" class="input" />
                        </div>
                    </div>
                </div>

                <!-- ── Caller Info ── -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Caller Information</h4>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="label">Age</label>
                            <input v-model="editForm.caller_age" type="number" min="1" max="120" class="input" />
                        </div>
                        <div>
                            <label class="label">Gender</label>
                            <select v-model="editForm.caller_gender" class="input">
                                <option value="">— select —</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                                <option value="prefer_not_to_say">Prefer not to say</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Marital Status</label>
                            <select v-model="editForm.caller_marital_status" class="input">
                                <option value="">— select —</option>
                                <option value="single">Single</option>
                                <option value="married">Married</option>
                                <option value="divorced">Divorced</option>
                                <option value="widowed">Widowed</option>
                                <option value="cohabiting">Cohabiting</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Key Pops</label>
                            <select v-model="editForm.key_pops" class="input">
                                <option value="">— select —</option>
                                <option v-for="kp in props.keyPops" :key="kp" :value="kp">{{ kp }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Repeat Caller</label>
                            <select v-model="editForm.is_repeat_caller" class="input">
                                <option value="">— select —</option>
                                <option value="1">Repeat Call</option>
                                <option value="0">New</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── Location ── -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Location</h4>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="label">Province</label>
                            <select v-model="editForm.province" class="input">
                                <option value="">— select —</option>
                                <option v-for="p in provinces" :key="p" :value="p">{{ p }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">District</label>
                            <input v-model="editForm.district" class="input" />
                        </div>
                        <div>
                            <label class="label">Location</label>
                            <input v-model="editForm.location" class="input" />
                        </div>
                    </div>
                </div>

                <!-- ── Services ── -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Services</h4>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">Services Requested Before</label>
                                <input v-model="editForm.services_requested_before" class="input" placeholder="Describe previous services…" />
                            </div>
                            <div>
                                <label class="label">Services Requested</label>
                                <select v-model="editForm.services_requested" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="s in props.servicesRequested" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Second Service Requested</label>
                                <select v-model="editForm.second_service_requested" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="s in props.secondServicesRequested" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">No. of Services</label>
                                <input v-model="editForm.number_of_services" type="number" min="0" class="input" />
                            </div>
                            <div>
                                <label class="label">Referred To</label>
                                <select v-model="editForm.referred_to" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="r in props.referredTo" :key="r" :value="r">{{ r }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                                <input type="checkbox" v-model="editForm.uptake_confirmed" class="rounded border-gray-300 text-brand-600" />
                                Confirming Uptake of Services
                            </label>
                        </div>
                    </div>
                </div>

                <!-- ── Notes ── -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Notes</h4>
                    <div>
                        <label class="label">Counsellor's Notes</label>
                        <textarea v-model="editForm.description" class="input h-24 resize-none" placeholder="Notes from the session…" />
                    </div>
                </div>
            </form>

            <!-- Footer -->
            <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                <button type="button" @click="closeEdit" class="btn-secondary">Cancel</button>
                <button type="button" @click="saveTicket" class="btn-primary" :disabled="editForm.processing">
                    {{ editForm.processing ? 'Saving…' : 'Save Ticket' }}
                </button>
            </div>
        </div>
    </div>
</template>
