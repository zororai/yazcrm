<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { PhoneIcon, ClockIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import ClassificationPanel from '@/Components/ClassificationPanel.vue';

const page = usePage();
const distressDomains          = computed(() => page.props.distressDomains ?? []);
const keyPops                  = computed(() => page.props.keyPops ?? []);
const modesOfCommunication     = computed(() => page.props.modesOfCommunication ?? []);
const projects                 = computed(() => page.props.projects ?? []);
const servicesRequestedList    = computed(() => page.props.servicesRequested ?? []);
const secondServicesRequested  = computed(() => page.props.secondServicesRequested ?? []);
const referredToList           = computed(() => page.props.referredTo ?? []);
const serviceCategories        = computed(() => page.props.serviceCategories ?? {});

const props = defineProps({
    call: Object,   // { call_id, db_call_id, caller, callee, duration, direction, client, recording_id }
});

const emit = defineEmits(['close', 'minimize']);

const form = useForm({
    subject: '', contact_number: props.call.caller ?? '', sisters_number: '', description: '',
    priority: 'medium', status: 'in_progress', follow_up_date: '',
    // The real calls.id row this ticket links to — NOT the Yeastar call_id
    // string, which the backend's `exists:calls,id` rule would reject.
    call_id: props.call.db_call_id ?? '',
    // CRM fields — same shape as the New Ticket form on /tickets
    mode_of_communication:    'phone',
    call_validity:            'valid',
    purpose_of_call:          '',
    project:                  '',
    action_status:            '',
    immediate_action_required: '',
    referral_uptake_date:     '',
    caller_age:               '',
    caller_gender:            '',
    caller_marital_status:    '',
    key_pops:                 '',
    is_repeat_caller:         '',
    province:                 '',
    district:                 '',
    location:                 '',
    services_requested_before: '',
    services_requested:       '',
    second_service_requested: '',
    number_of_services:       '',
    referred_to:              '',
    uptake_confirmed:         false,
    classification:           {},
    psychosocial_type:        '',
});

form.subject = props.call.client
    ? `Call with ${props.call.client.name} — follow-up required`
    : `Call from ${props.call.caller} — follow-up required`;

function submit() {
    form.post('/tickets', {
        onSuccess: () => emit('close'),
    });
}

// ── Referred To combobox (matches the New Ticket form) ──────────────────────
const referredToSearch   = ref('');
const showReferredToDrop = ref(false);

const filteredReferredTo = computed(() => {
    const q = referredToSearch.value.toLowerCase().trim();
    if (!q) return referredToList.value;
    return referredToList.value.filter(r => r.toLowerCase().includes(q));
});

function onReferredToInput(val) {
    referredToSearch.value  = val;
    form.referred_to        = val;
    showReferredToDrop.value = true;
}

function selectReferredTo(val) {
    form.referred_to         = val;
    referredToSearch.value   = val;
    showReferredToDrop.value = false;
}

function openReferredToDrop() {
    referredToSearch.value   = form.referred_to ?? '';
    showReferredToDrop.value = true;
}

function closeReferredToDrop() {
    setTimeout(() => { showReferredToDrop.value = false; }, 150);
}

// ── AI Draft Notes / Generate Summary from Recording ─────────────────────────
const draftingNotes = ref(false);
const aiStatus   = ref(null);   // null | 'loading' | 'done' | 'processing' | 'failed' | 'no_recording'
const aiMessage  = ref('');
let   aiPollTimer = null;
let   aiPollAttempts = 0;
const AI_POLL_MAX_ATTEMPTS = 6; // ~30s of retrying at 5s intervals

async function loadAiNotes({ silent = false } = {}) {
    if (!props.call.recording_id) {
        aiStatus.value  = 'no_recording';
        aiMessage.value = 'This call is still in progress — no recording to summarize yet.';
        return;
    }
    if (!silent) aiStatus.value = 'loading';
    aiMessage.value = '';
    try {
        const res  = await fetch(`/api/recordings/${props.call.recording_id}/ai-notes`, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();

        if (data.status === 'done' && data.ai_notes) {
            // Never overwrite notes the counsellor has already started typing.
            if (!form.description) form.description = data.ai_notes;
            aiStatus.value = 'done';
            stopAiPolling();
        } else if (data.status === 'processing' || data.status === 'pending') {
            aiStatus.value  = 'processing';
            aiMessage.value = 'Transcribing the recording and drafting a summary…';
            scheduleAiPoll();
        } else {
            aiStatus.value  = 'failed';
            aiMessage.value = 'Notes not ready yet. Try again shortly.';
        }
    } catch {
        aiStatus.value  = 'failed';
        aiMessage.value = 'Could not load AI notes.';
    }
}

function scheduleAiPoll() {
    if (aiPollAttempts >= AI_POLL_MAX_ATTEMPTS) return;
    aiPollAttempts++;
    aiPollTimer = setTimeout(() => loadAiNotes({ silent: true }), 5000);
}

function stopAiPolling() {
    clearTimeout(aiPollTimer);
    aiPollTimer = null;
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function fetchAiNotes(recordingId) {
    const res = await fetch(`/api/recordings/${recordingId}/ai-notes`, { headers: { 'Accept': 'application/json' } });
    return res.json();
}

const DRAFT_POLL_MAX_ATTEMPTS = 8;

// Same behavior as the New Ticket form's "AI Draft Notes"/"Generate Summary
// from Recording" button: if a recording is known, actively trigger
// transcription (many recordings never had it triggered automatically) and
// poll until a summary is ready; otherwise draft from the fields filled in.
async function draftNotes() {
    draftingNotes.value = true;
    try {
        if (props.call.recording_id) {
            let data = await fetchAiNotes(props.call.recording_id);

            if (data.status !== 'done' && data.status !== 'processing') {
                aiStatus.value  = 'processing';
                aiMessage.value = 'Starting transcription…';
                await fetch(`/api/recordings/${props.call.recording_id}/transcribe`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
                data = await fetchAiNotes(props.call.recording_id);
            }

            for (let attempt = 0; attempt < DRAFT_POLL_MAX_ATTEMPTS; attempt++) {
                if (data.status === 'done' && data.ai_notes) {
                    form.description = data.ai_notes;
                    aiStatus.value = 'done';
                    return;
                }
                if (data.status === 'failed') break;

                aiStatus.value  = 'processing';
                aiMessage.value = 'Transcribing the recording and drafting a summary…';
                await sleep(5000);
                data = await fetchAiNotes(props.call.recording_id);
            }

            aiStatus.value  = 'failed';
            aiMessage.value = data.status === 'failed'
                ? 'Transcription failed for this recording. You can type notes manually.'
                : 'Still transcribing — try the button again in a moment.';
            return;
        }

        const res = await fetch('/tickets/draft-notes', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({
                subject: form.subject,
                purpose_of_call: form.purpose_of_call,
                services_requested: form.services_requested,
                second_service_requested: form.second_service_requested,
                caller_age: form.caller_age,
                caller_gender: form.caller_gender,
                caller_marital_status: form.caller_marital_status,
                key_pops: form.key_pops,
                province: form.province,
                district: form.district,
                location: form.location,
                mode_of_communication: form.mode_of_communication,
                priority: form.priority,
                action_status: form.action_status,
                referred_to: form.referred_to,
                is_repeat_caller: form.is_repeat_caller,
                psychosocial_type: form.psychosocial_type,
                classification: form.classification,
            }),
        });
        const json = await res.json();
        if (json.note) form.description = json.note;
    } catch {
        // silently fail — counsellor can type manually
    } finally {
        draftingNotes.value = false;
    }
}

onMounted(() => {
    // Auto-attach the call's recording summary into Counsellor's Notes,
    // instead of requiring a manual click. If the recording isn't attached
    // yet (e.g. this ticket was opened from the 15s-in-progress prompt,
    // before the call actually ended), this just shows that state — the
    // "Reload AI Notes" button stays available to retry once it exists.
    loadAiNotes();
});

onUnmounted(() => {
    stopAiPolling();
});

function fmt(s) {
    if (!s) return '—';
    return `${Math.floor(s / 60)}m ${s % 60}s`;
}

const provinces = [
    'Bulawayo', 'Harare', 'Manicaland', 'Mashonaland Central',
    'Mashonaland East', 'Mashonaland West', 'Masvingo',
    'Matabeleland North', 'Matabeleland South', 'Midlands',
];
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col">

            <!-- Header — closing doesn't discard this: it goes back to the
                 floating queue on the side, since a ticket must still be logged. -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                <div class="flex items-center gap-2 text-gray-900">
                    <PhoneIcon class="h-5 w-5 text-brand-600" />
                    <h3 class="font-semibold">Call ended — log a ticket</h3>
                </div>
                <button type="button" @click="emit('minimize')" class="text-gray-400 hover:text-gray-600" title="Minimize — stays in the queue until a ticket is logged">
                    <XMarkIcon class="h-5 w-5" />
                </button>
            </div>

            <!-- Call summary -->
            <div class="bg-brand-50 border-b border-brand-100 px-6 py-2.5 flex items-center gap-4 text-sm flex-shrink-0">
                <div>
                    <span class="text-gray-500">From</span>
                    <span class="ml-1 font-medium text-gray-900">
                        {{ call.client ? call.client.name : call.caller }}
                    </span>
                    <span v-if="call.client" class="ml-1 text-gray-400 font-mono text-xs">({{ call.caller }})</span>
                </div>
                <div class="flex items-center gap-1 text-gray-500 ml-auto">
                    <ClockIcon class="h-4 w-4" />
                    {{ fmt(call.duration) }}
                </div>
            </div>

            <!-- Scrollable body — identical field set to the New Ticket form -->
            <form @submit.prevent="submit" class="overflow-y-auto flex-1 px-6 py-4 space-y-5">

                <!-- ── Basic Info ── -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Basic Info</h4>
                    <div class="space-y-3">
                        <div>
                            <label class="label">Name *</label>
                            <input v-model="form.subject" class="input" :class="{ 'border-red-500': form.errors.subject }" required autofocus />
                            <p v-if="form.errors.subject" class="mt-1 text-xs text-red-600">{{ form.errors.subject }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">Contact Number *</label>
                                <input v-model="form.contact_number" class="input" :class="{ 'border-red-500': form.errors.contact_number }" required />
                                <p v-if="form.errors.contact_number" class="mt-1 text-xs text-red-600">{{ form.errors.contact_number }}</p>
                            </div>
                            <div>
                                <label class="label">Sisters Number</label>
                                <input v-model="form.sisters_number" class="input" placeholder="Sister's contact number" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">Priority</label>
                                <select v-model="form.priority" class="input">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Case Status</label>
                                <select v-model="form.status" class="input">
                                    <option value="in_progress">In Progress</option>
                                    <option value="ongoing">On Going</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="label">Follow-up Date</label>
                            <input v-model="form.follow_up_date" type="date" class="input w-48" />
                        </div>
                    </div>
                </div>

                <!-- ── Call Details ── -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Call Details</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">Mode of Communication</label>
                            <select v-model="form.mode_of_communication" class="input">
                                <option value="">— select —</option>
                                <option v-for="m in modesOfCommunication" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Call Validity</label>
                            <select v-model="form.call_validity" class="input">
                                <option value="">— select —</option>
                                <option value="valid">Valid</option>
                                <option value="invalid">Invalid</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="label">Purpose of Call</label>
                            <select v-model="form.purpose_of_call" class="input">
                                <option value="">— select —</option>
                                <option v-for="d in distressDomains" :key="d" :value="d">{{ d }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Project</label>
                            <select v-model="form.project" class="input">
                                <option value="">— select —</option>
                                <option v-for="p in projects" :key="p" :value="p">{{ p }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Action Status</label>
                            <select v-model="form.action_status" class="input">
                                <option value="">— select —</option>
                                <option value="yes">Yes</option>
                                <option value="ongoing">Ongoing</option>
                                <option value="pending">Pending</option>
                                <option value="no">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Immediate Action Required</label>
                            <select v-model="form.immediate_action_required" class="input">
                                <option value="">— select —</option>
                                <option value="1">Yes</option>
                                <option value="0">No</option>
                            </select>
                            <p v-if="form.immediate_action_required == 1" class="mt-1 text-xs text-red-600 font-medium">
                                This will create an urgent case visible to all agents.
                            </p>
                        </div>
                        <div v-if="form.immediate_action_required == 1">
                            <label class="label">Referral Uptake Date</label>
                            <input v-model="form.referral_uptake_date" type="date" class="input" />
                            <p class="mt-1 text-xs text-gray-400">Expected date for referral to be acted on.</p>
                        </div>
                    </div>
                </div>

                <!-- ── Caller Info ── -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Caller Information</h4>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="label">Age</label>
                            <input v-model="form.caller_age" type="number" min="1" max="120" class="input" />
                        </div>
                        <div>
                            <label class="label">Gender</label>
                            <select v-model="form.caller_gender" class="input">
                                <option value="">— select —</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                                <option value="prefer_not_to_say">Prefer not to say</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Marital Status</label>
                            <select v-model="form.caller_marital_status" class="input">
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
                            <select v-model="form.key_pops" class="input">
                                <option value="">— select —</option>
                                <option v-for="kp in keyPops" :key="kp" :value="kp">{{ kp }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Repeat Caller</label>
                            <select v-model="form.is_repeat_caller" class="input">
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
                            <select v-model="form.province" class="input">
                                <option value="">— select —</option>
                                <option v-for="p in provinces" :key="p" :value="p">{{ p }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">District</label>
                            <input v-model="form.district" class="input" />
                        </div>
                        <div>
                            <label class="label">Location</label>
                            <input v-model="form.location" class="input" />
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
                                <input v-model="form.services_requested_before" class="input" placeholder="Describe previous services…" />
                            </div>
                            <div>
                                <label class="label">Services Requested</label>
                                <select v-model="form.services_requested" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="s in servicesRequestedList" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Second Service Requested</label>
                                <select v-model="form.second_service_requested" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="s in secondServicesRequested" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">No. of Services</label>
                                <input v-model="form.number_of_services" type="number" min="0" class="input" />
                            </div>
                            <div class="relative">
                                <label class="label">Referred To</label>
                                <input
                                    :value="referredToSearch || form.referred_to"
                                    @input="onReferredToInput($event.target.value)"
                                    @focus="openReferredToDrop"
                                    @blur="closeReferredToDrop"
                                    class="input"
                                    placeholder="Type or search…"
                                    autocomplete="off"
                                />
                                <ul v-if="showReferredToDrop && filteredReferredTo.length"
                                    class="absolute z-30 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                    <li v-for="r in filteredReferredTo" :key="r">
                                        <button type="button"
                                            @mousedown.prevent="selectReferredTo(r)"
                                            class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-brand-50 hover:text-brand-700">
                                            {{ r }}
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div>
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                                <input type="checkbox" v-model="form.uptake_confirmed" class="rounded border-gray-300 text-brand-600" />
                                Confirming Uptake of Services
                            </label>
                        </div>
                    </div>
                </div>

                <!-- ── Classification ── -->
                <div v-if="form.services_requested">
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Classification</h4>
                    <ClassificationPanel
                        :service="form.services_requested"
                        :service-categories="serviceCategories"
                        v-model="form.classification"
                        :psychosocial-type="form.psychosocial_type"
                        @update:psychosocial-type="v => form.psychosocial_type = v"
                    />
                </div>

                <!-- ── Notes ── -->
                <div>
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Notes</h4>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="label">
                                Counsellor's Notes
                                <span v-if="aiStatus === 'done'" class="text-green-600 font-normal">— auto-filled from call recording summary</span>
                                <span v-else class="text-gray-400 font-normal">(optional)</span>
                            </label>
                            <button
                                type="button"
                                @click="draftNotes"
                                :disabled="draftingNotes"
                                :title="call.recording_id ? 'Generate a summary from the found call recording' : 'Draft a note from the fields filled in so far'"
                                :class="[
                                    'flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg shadow-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed',
                                    call.recording_id
                                        ? 'bg-gradient-to-r from-brand-600 to-indigo-600 text-white hover:from-brand-700 hover:to-indigo-700'
                                        : 'bg-brand-50 text-brand-700 hover:bg-brand-100',
                                ]"
                            >
                                <svg v-if="!draftingNotes" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                </svg>
                                {{ draftingNotes ? 'Drafting…' : (call.recording_id ? 'Generate Summary from Recording' : 'AI Draft Notes') }}
                            </button>
                        </div>
                        <p v-if="aiMessage" class="mb-1 text-xs" :class="{
                            'text-green-600': aiStatus === 'done',
                            'text-gray-400':  aiStatus === 'no_recording',
                            'text-amber-600': aiStatus === 'processing' || aiStatus === 'failed',
                        }">
                            {{ aiMessage }}
                        </p>
                        <textarea v-model="form.description" class="input h-24 resize-none" placeholder="What was discussed on this call…" />
                    </div>
                </div>
            </form>

            <!-- Footer — Create Ticket is the only way to close this modal -->
            <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                <button type="button" @click="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Creating…' : 'Create Ticket' }}
                </button>
            </div>
        </div>
    </div>
</template>
