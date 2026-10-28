<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ClassificationPanel from '@/Components/ClassificationPanel.vue';
import { ArrowLeftIcon, PencilSquareIcon, XMarkIcon, MicrophoneIcon } from '@heroicons/vue/24/outline';
import { PROVINCES, PROVINCE_DISTRICTS } from '@/provinceDistricts.js';

const props = defineProps({
    ticket:                Object,
    staff:                 Array,
    keyPops:               Array,
    modesOfCommunication:  Array,
    projects:              Array,
    servicesRequested:     Array,
    secondServicesRequested: Array,
    referredTo:            Array,
    serviceCategories:     Object,
});

const editing = ref(false);

const noteForm = useForm({ body: '', email_user_ids: [], email_to: '' });

// Optionally email the note: pick staff and/or type other addresses.
const emailNote   = ref(false);
const staffSearch = ref('');
const staffMatches = computed(() => {
    const q = staffSearch.value.trim().toLowerCase();
    if (!q) return [];
    return (props.staff ?? [])
        .filter(u => !noteForm.email_user_ids.includes(u.id))
        .filter(u => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q))
        .slice(0, 8);
});
const pickedStaff = computed(() => (props.staff ?? []).filter(u => noteForm.email_user_ids.includes(u.id)));
function pickStaff(u)   { noteForm.email_user_ids.push(u.id); staffSearch.value = ''; }
function unpickStaff(u) { noteForm.email_user_ids = noteForm.email_user_ids.filter(id => id !== u.id); }

function addNote() {
    if (!emailNote.value) { noteForm.email_user_ids = []; noteForm.email_to = ''; }
    noteForm.post(`/tickets/${props.ticket.id}/notes`, {
        preserveScroll: true,
        onSuccess: () => { noteForm.reset(); emailNote.value = false; },
    });
}

function removeNote(note) {
    if (!confirm('Remove this note?')) return;
    useForm({}).delete(`/tickets/${props.ticket.id}/notes/${note.id}`, { preserveScroll: true });
}

function initials(name) {
    if (!name) return '?';
    return name.split(' ').map(p => p[0]).slice(0, 2).join('').toUpperCase();
}

const editForm = useForm({
    subject:                   props.ticket.subject ?? '',
    description:               props.ticket.description ?? '',
    status:                    props.ticket.status ?? 'open',
    priority:                  props.ticket.priority ?? 'medium',
    // Call details
    mode_of_communication:     props.ticket.mode_of_communication ?? '',
    call_validity:             props.ticket.call_validity ?? '',
    purpose_of_call:           props.ticket.purpose_of_call ?? '',
    project:                   props.ticket.project ?? '',
    immediate_action_required: props.ticket.immediate_action_required ? '1' : '0',
    action_status:             props.ticket.action_status ?? '',
    // Caller info
    contact_number:            props.ticket.contact_number ?? '',
    sisters_number:            props.ticket.sisters_number ?? '',
    caller_age:                props.ticket.caller_age ?? '',
    caller_gender:             props.ticket.caller_gender ?? '',
    caller_marital_status:     props.ticket.caller_marital_status ?? '',
    key_pops:                  props.ticket.key_pops ?? '',
    is_repeat_caller:          props.ticket.is_repeat_caller ? '1' : '0',
    // Location
    province:                  props.ticket.province ?? '',
    district:                  props.ticket.district ?? '',
    location:                  props.ticket.location ?? '',
    // Services
    services_requested_before: props.ticket.services_requested_before ?? '',
    services_requested:        props.ticket.services_requested ?? '',
    second_service_requested:  props.ticket.second_service_requested ?? '',
    services_requested_before_other: props.ticket.services_requested_before_other ?? '',
    services_requested_other:        props.ticket.services_requested_other ?? '',
    second_service_requested_other:  props.ticket.second_service_requested_other ?? '',
    number_of_services:        props.ticket.number_of_services ?? '',
    referred_to:               props.ticket.referred_to ?? '',
    uptake_confirmed:          props.ticket.uptake_confirmed ? true : false,
    referral_uptake_date:      props.ticket.referral_uptake_date ?? '',
    classification:            props.ticket.classification ?? {},
    psychosocial_type:         props.ticket.psychosocial_type ?? '',
    follow_up_date:            props.ticket.follow_up_date ? String(props.ticket.follow_up_date).slice(0, 10) : '',
    log_note:                  '',
});

// "Other (Specify):" in a service dropdown opens a text box for the detail.
const isOtherService = (v) => !!v && v.trim().toLowerCase().startsWith('other');
// High / Urgent tickets need a follow-up date and a log note.
const isHighPriority = (p) => p === 'high' || p === 'urgent';
const raisingPriority = computed(() => isHighPriority(editForm.priority) && !isHighPriority(props.ticket.priority));

// e.g. "Other (Specify): Legal aid" when a detail was given.
function serviceWithDetail(field) {
    const value = props.ticket[field], detail = props.ticket[`${field}_other`];
    return isOtherService(value) && detail ? `${value.replace(/[:\s]+$/, '')}: ${detail}` : value;
}

function save() {
    editForm.put(`/tickets/${props.ticket.id}`, {
        onSuccess: () => { editing.value = false; editForm.log_note = ''; },
    });
}

function cancelEdit() {
    editForm.reset();
    editing.value = false;
}

const provinces = PROVINCES;

const priorityColor = {
    low: 'bg-gray-100 text-gray-600', medium: 'bg-blue-100 text-blue-800',
    high: 'bg-orange-100 text-orange-800', urgent: 'bg-red-100 text-red-800',
};
const statusColor = {
    open: 'bg-yellow-100 text-yellow-800', in_progress: 'bg-blue-100 text-blue-800',
    ongoing: 'bg-purple-100 text-purple-800',
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
            <div class="flex items-center justify-between">
                <Link href="/tickets" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                    <ArrowLeftIcon class="h-4 w-4" /> Back to tickets
                </Link>
                <div class="flex gap-2">
                    <Link v-if="ticket.contact_number" :href="`/recordings?search=${ticket.contact_number}`"
                        class="btn-secondary btn-sm inline-flex items-center gap-1">
                        <MicrophoneIcon class="h-4 w-4" /> Recordings
                    </Link>
                    <button v-if="!editing" @click="editing = true"
                        class="btn-primary btn-sm inline-flex items-center gap-1">
                        <PencilSquareIcon class="h-4 w-4" /> Edit Case
                    </button>
                    <template v-else>
                        <button @click="cancelEdit" class="btn-secondary btn-sm inline-flex items-center gap-1">
                            <XMarkIcon class="h-4 w-4" /> Cancel
                        </button>
                        <button @click="save" class="btn-primary btn-sm" :disabled="editForm.processing">
                            Save Changes
                        </button>
                    </template>
                </div>
            </div>

            <!-- ── VIEW MODE ── -->
            <div v-if="!editing" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
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

                    <div class="card">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-gray-800 text-sm">Log Notes <span class="font-normal text-gray-400">— who has worked on this case</span></h3>
                            <span v-if="ticket.status === 'ongoing'" class="badge bg-purple-100 text-purple-800">
                                Ongoing — multiple counsellors can log updates here
                            </span>
                        </div>

                        <form @submit.prevent="addNote" class="mb-4 space-y-2">
                            <textarea v-model="noteForm.body" rows="2" class="input"
                                placeholder="Add an update for this case (visible to whoever picks it up next)..."></textarea>
                            <p v-if="noteForm.errors.body" class="text-xs text-red-500">{{ noteForm.errors.body }}</p>

                            <div v-if="emailNote" class="rounded-lg border border-gray-200 p-3 space-y-2">
                                <div>
                                    <label class="label">Email to staff</label>
                                    <div v-if="pickedStaff.length" class="flex flex-wrap gap-1.5 mb-1.5">
                                        <span v-for="u in pickedStaff" :key="u.id" class="inline-flex items-center gap-1 rounded-full bg-brand-50 text-brand-700 text-xs px-2 py-0.5">
                                            {{ u.name }}
                                            <button type="button" @click="unpickStaff(u)" class="hover:text-red-500" :aria-label="`Remove ${u.name}`">✕</button>
                                        </span>
                                    </div>
                                    <div class="relative">
                                        <input v-model="staffSearch" class="input" placeholder="Type a name or email…" autocomplete="off" />
                                        <div v-if="staffMatches.length" class="absolute z-10 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg max-h-56 overflow-y-auto">
                                            <button v-for="u in staffMatches" :key="u.id" type="button" @click="pickStaff(u)"
                                                class="block w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50">
                                                {{ u.name }} <span class="text-xs text-gray-400">{{ u.email }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="label">Other email addresses <span class="text-gray-400 font-normal">— separate with commas</span></label>
                                    <input v-model="noteForm.email_to" class="input" placeholder="e.g. partner@clinic.org, supervisor@example.org" />
                                    <p v-if="noteForm.errors.email_to" class="mt-1 text-xs text-red-500">{{ noteForm.errors.email_to }}</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                    <input type="checkbox" v-model="emailNote" class="rounded border-gray-300" />
                                    Email this note
                                </label>
                                <button type="submit" class="btn-primary btn-sm"
                                    :disabled="noteForm.processing || !noteForm.body.trim() || (emailNote && !noteForm.email_user_ids.length && !noteForm.email_to.trim())">
                                    {{ emailNote ? 'Add & Email Note' : 'Add Note' }}
                                </button>
                            </div>
                        </form>

                        <div v-if="ticket.notes && ticket.notes.length" class="space-y-3">
                            <div v-for="note in ticket.notes" :key="note.id" class="flex gap-3">
                                <div class="h-8 w-8 shrink-0 rounded-full bg-brand-100 text-brand-700 text-xs font-semibold flex items-center justify-center">
                                    {{ initials(note.user?.name) }}
                                </div>
                                <div class="flex-1 bg-gray-50 rounded-lg px-3 py-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-semibold text-gray-700">{{ note.user?.name ?? 'Unknown' }}</span>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-gray-400">{{ new Date(note.created_at).toLocaleString() }}</span>
                                            <button @click="removeNote(note)" class="text-xs text-gray-300 hover:text-red-500">✕</button>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap mt-0.5">{{ note.body }}</p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400 italic">No log notes yet.</p>
                    </div>

                    <div class="card">
                        <h3 class="font-semibold text-gray-800 mb-3 text-sm">Call Details</h3>
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                            <div><dt class="text-gray-400 text-xs">Mode of Communication</dt><dd class="font-medium capitalize">{{ label(ticket.mode_of_communication) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">Call Validity</dt><dd class="font-medium capitalize">{{ label(ticket.call_validity) }}</dd></div>
                            <div class="col-span-2"><dt class="text-gray-400 text-xs">Purpose of Call</dt><dd class="font-medium">{{ label(ticket.purpose_of_call) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">Project</dt><dd class="font-medium">{{ label(ticket.project) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">Immediate Action Required</dt>
                                <dd :class="ticket.immediate_action_required ? 'text-red-600 font-semibold' : 'font-medium'">{{ label(ticket.immediate_action_required) }}</dd>
                            </div>
                            <div><dt class="text-gray-400 text-xs">Action Status</dt><dd class="font-medium capitalize">{{ label(ticket.action_status) }}</dd></div>
                        </dl>
                    </div>

                    <div class="card">
                        <h3 class="font-semibold text-gray-800 mb-3 text-sm">Caller Information</h3>
                        <dl class="grid grid-cols-3 gap-x-6 gap-y-2 text-sm">
                            <div><dt class="text-gray-400 text-xs">Contact Number</dt><dd class="font-medium">{{ label(ticket.contact_number) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">Sisters Number</dt><dd class="font-medium">{{ label(ticket.sisters_number) }}</dd></div>
                            <div></div>
                            <div><dt class="text-gray-400 text-xs">Age</dt><dd class="font-medium">{{ label(ticket.caller_age) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">Gender</dt><dd class="font-medium capitalize">{{ label(ticket.caller_gender) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">Marital Status</dt><dd class="font-medium capitalize">{{ label(ticket.caller_marital_status) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">Key Pops</dt><dd class="font-medium">{{ label(ticket.key_pops) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">New / Repeat</dt><dd class="font-medium">{{ ticket.is_repeat_caller ? 'Repeat' : 'New' }}</dd></div>
                        </dl>
                    </div>

                    <div class="card">
                        <h3 class="font-semibold text-gray-800 mb-3 text-sm">Location</h3>
                        <dl class="grid grid-cols-3 gap-x-6 gap-y-2 text-sm">
                            <div><dt class="text-gray-400 text-xs">Province</dt><dd class="font-medium">{{ label(ticket.province) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">District</dt><dd class="font-medium">{{ label(ticket.district) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">Location</dt><dd class="font-medium">{{ label(ticket.location) }}</dd></div>
                        </dl>
                    </div>

                    <div class="card">
                        <h3 class="font-semibold text-gray-800 mb-3 text-sm">Services</h3>
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                            <div class="col-span-2"><dt class="text-gray-400 text-xs">Services Requested Before</dt><dd class="font-medium">{{ label(serviceWithDetail('services_requested_before')) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">Services Requested</dt><dd class="font-medium">{{ label(serviceWithDetail('services_requested')) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">Second Service</dt><dd class="font-medium">{{ label(serviceWithDetail('second_service_requested')) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">No. of Services</dt><dd class="font-medium">{{ label(ticket.number_of_services) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">Referred To</dt><dd class="font-medium">{{ label(ticket.referred_to) }}</dd></div>
                            <div><dt class="text-gray-400 text-xs">Referral Uptake Date</dt><dd class="font-medium">{{ label(ticket.referral_uptake_date) }}</dd></div>
                            <div class="col-span-2"><dt class="text-gray-400 text-xs">Uptake Confirmed</dt><dd class="font-medium">{{ label(ticket.uptake_confirmed) }}</dd></div>
                        </dl>
                    </div>

                    <div v-if="ticket.call" class="card">
                        <h3 class="font-semibold text-gray-800 mb-2 text-sm">Related Call</h3>
                        <Link :href="`/calls/${ticket.call.id}`" class="text-brand-600 hover:underline text-sm">
                            {{ ticket.call.caller }} → {{ ticket.call.callee }} ({{ new Date(ticket.call.started_at).toLocaleString() }})
                        </Link>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="card">
                        <h3 class="font-semibold text-gray-800 mb-2 text-sm">Client</h3>
                        <template v-if="ticket.client">
                            <Link :href="`/clients/${ticket.client.id}`" class="font-medium text-brand-600 hover:underline">{{ ticket.client.name }}</Link>
                            <p class="text-xs text-gray-500 mt-0.5">{{ ticket.client.phone }}</p>
                        </template>
                        <p v-else class="text-sm text-gray-400">No client.</p>
                    </div>

                    <div class="card text-xs text-gray-500 space-y-1">
                        <div>Created: {{ new Date(ticket.created_at).toLocaleString() }}</div>
                        <div v-if="ticket.resolved_at">Resolved: {{ new Date(ticket.resolved_at).toLocaleString() }}</div>
                        <div>Agent: {{ ticket.agent?.name ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <!-- ── EDIT MODE ── -->
            <form v-else @submit.prevent="save" class="space-y-6">

                <!-- Subject & Notes -->
                <div class="card space-y-4">
                    <h3 class="font-semibold text-gray-800 text-sm">Case Details</h3>
                    <div>
                        <label class="label">Subject</label>
                        <input v-model="editForm.subject" class="input" required />
                        <p v-if="editForm.errors.subject" class="text-xs text-red-500 mt-1">{{ editForm.errors.subject }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Status</label>
                            <select v-model="editForm.status" class="input">
                                <option value="open">Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Priority</label>
                            <select v-model="editForm.priority" class="input">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="label">Follow-up Date <span v-if="isHighPriority(editForm.priority)" class="text-red-500">*</span></label>
                        <input v-model="editForm.follow_up_date" type="date" class="input w-48" />
                        <p v-if="editForm.errors.follow_up_date" class="mt-1 text-xs text-red-600">{{ editForm.errors.follow_up_date }}</p>
                    </div>
                    <div>
                        <label class="label">Counsellor's Notes</label>
                        <textarea v-model="editForm.description" rows="4" class="input resize-none" />
                    </div>
                    <div v-if="raisingPriority">
                        <label class="label">Log Note <span v-if="!ticket.notes?.length" class="text-red-500">*</span> <span class="text-gray-400 font-normal">— saved under your name in the Log Notes</span></label>
                        <textarea v-model="editForm.log_note" rows="3" class="input resize-none" placeholder="Why this case is now High / Urgent and what happens next…" />
                        <p v-if="editForm.errors.log_note" class="mt-1 text-xs text-red-600">{{ editForm.errors.log_note }}</p>
                    </div>
                </div>

                <!-- Call Details -->
                <div class="card space-y-4">
                    <h3 class="font-semibold text-gray-800 text-sm">Call Details</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Mode of Communication</label>
                            <select v-model="editForm.mode_of_communication" class="input">
                                <option value="">— select —</option>
                                <option v-for="m in modesOfCommunication" :key="m" :value="m">{{ m }}</option>
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
                            <input v-model="editForm.purpose_of_call" class="input" list="purposes-list" />
                            <datalist id="purposes-list">
                                <option value="Responding to Call" />
                                <option value="Tracking" />
                                <option value="Referral" />
                                <option value="Information" />
                                <option value="Crisis" />
                            </datalist>
                        </div>
                        <div>
                            <label class="label">Project</label>
                            <select v-model="editForm.project" class="input">
                                <option value="">— select —</option>
                                <option v-for="p in projects" :key="p" :value="p">{{ p }}</option>
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
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Referral Uptake Date</label>
                            <input v-model="editForm.referral_uptake_date" type="date" class="input" />
                        </div>
                    </div>
                </div>

                <!-- Caller Information -->
                <div class="card space-y-4">
                    <h3 class="font-semibold text-gray-800 text-sm">Caller Information</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="label">Contact Number</label>
                            <input v-model="editForm.contact_number" class="input" />
                        </div>
                        <div>
                            <label class="label">Sisters Number</label>
                            <input v-model="editForm.sisters_number" class="input" />
                        </div>
                        <div></div>
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
                                <option v-for="kp in keyPops" :key="kp" :value="kp">{{ kp }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Repeat Caller</label>
                            <select v-model="editForm.is_repeat_caller" class="input">
                                <option value="0">New</option>
                                <option value="1">Repeat</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Location -->
                <div class="card space-y-4">
                    <h3 class="font-semibold text-gray-800 text-sm">Location</h3>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="label">Province</label>
                            <select v-model="editForm.province" @change="editForm.district = ''" class="input">
                                <option value="">— select —</option>
                                <option v-for="p in provinces" :key="p" :value="p">{{ p }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">District</label>
                            <select v-model="editForm.district" class="input" :disabled="!editForm.province">
                                <option value="">{{ editForm.province ? '— select —' : 'Select a province first' }}</option>
                                <option v-for="d in PROVINCE_DISTRICTS[editForm.province] ?? []" :key="d" :value="d">{{ d }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Location</label>
                            <input v-model="editForm.location" class="input" />
                        </div>
                    </div>
                </div>

                <!-- Services -->
                <div class="card space-y-4">
                    <h3 class="font-semibold text-gray-800 text-sm">Services</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="label">Services Requested Before</label>
                            <select v-model="editForm.services_requested_before" class="input">
                                <option value="">— select —</option>
                                <option v-if="ticket.services_requested_before && !servicesRequested.includes(ticket.services_requested_before)" :value="ticket.services_requested_before">{{ ticket.services_requested_before }}</option>
                                <option v-for="s in servicesRequested" :key="s" :value="s">{{ s }}</option>
                            </select>
                            <input v-if="isOtherService(editForm.services_requested_before)" v-model="editForm.services_requested_before_other" class="input mt-2" placeholder="Specify the other service…" />
                            <p v-if="editForm.errors.services_requested_before_other" class="mt-1 text-xs text-red-600">{{ editForm.errors.services_requested_before_other }}</p>
                        </div>
                        <div>
                            <label class="label">Services Requested</label>
                            <select v-model="editForm.services_requested" class="input">
                                <option value="">— select —</option>
                                <option v-for="s in servicesRequested" :key="s" :value="s">{{ s }}</option>
                            </select>
                            <input v-if="isOtherService(editForm.services_requested)" v-model="editForm.services_requested_other" class="input mt-2" placeholder="Specify the other service…" />
                            <p v-if="editForm.errors.services_requested_other" class="mt-1 text-xs text-red-600">{{ editForm.errors.services_requested_other }}</p>
                        </div>
                        <div>
                            <label class="label">Second Service Requested</label>
                            <select v-model="editForm.second_service_requested" class="input">
                                <option value="">— select —</option>
                                <option v-for="s in secondServicesRequested" :key="s" :value="s">{{ s }}</option>
                            </select>
                            <input v-if="isOtherService(editForm.second_service_requested)" v-model="editForm.second_service_requested_other" class="input mt-2" placeholder="Specify the other service…" />
                            <p v-if="editForm.errors.second_service_requested_other" class="mt-1 text-xs text-red-600">{{ editForm.errors.second_service_requested_other }}</p>
                        </div>
                        <div>
                            <label class="label">No. of Services</label>
                            <input v-model="editForm.number_of_services" type="number" min="0" class="input" />
                        </div>
                        <div>
                            <label class="label">Referred To</label>
                            <select v-model="editForm.referred_to" class="input">
                                <option value="">— select —</option>
                                <option v-for="r in referredTo" :key="r" :value="r">{{ r }}</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                                <input type="checkbox" v-model="editForm.uptake_confirmed" class="rounded border-gray-300 text-brand-600" />
                                Uptake Confirmed
                            </label>
                        </div>
                    </div>

                    <!-- Classification -->
                    <div v-if="editForm.services_requested" class="mt-4 pt-4 border-t border-gray-100">
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Classification</h4>
                        <ClassificationPanel
                            :service="editForm.services_requested"
                            :service-categories="serviceCategories"
                            v-model="editForm.classification"
                            :psychosocial-type="editForm.psychosocial_type"
                            @update:psychosocial-type="v => editForm.psychosocial_type = v"
                        />
                    </div>
                </div>

                <!-- Sticky save bar -->
                <div class="sticky bottom-0 bg-white border-t border-gray-200 px-0 py-3 flex justify-end gap-3 -mx-4 px-4">
                    <button type="button" @click="cancelEdit" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="editForm.processing">Save Changes</button>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
