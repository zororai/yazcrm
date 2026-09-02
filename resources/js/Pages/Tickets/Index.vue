<script setup>
import { ref, watch, computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';

const distressDomains = computed(() => usePage().props.distressDomains ?? []);
import AppLayout from '@/Layouts/AppLayout.vue';
import ClassificationPanel from '@/Components/ClassificationPanel.vue';
import { PlusIcon, MagnifyingGlassIcon, XMarkIcon, ArrowUpTrayIcon, NoSymbolIcon, PencilIcon, TrashIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';
import { debounce } from 'lodash-es';

const props    = defineProps({ tickets: Object, clients: Array, agents: Array, filters: Object, keyPops: Array, modesOfCommunication: Array, projects: Array, servicesRequested: Array, secondServicesRequested: Array, servicesRequestedBefore: Array, referredTo: Array, serviceCategories: Object });
const isAdmin  = computed(() => usePage().props.auth.user?.role === 'admin');
const search         = ref(props.filters.search         ?? '');
const status         = ref(props.filters.status         ?? '');
const priority       = ref(props.filters.priority       ?? '');
const agentId        = ref(props.filters.agent_id       ?? '');
const gender         = ref(props.filters.gender         ?? '');
const service        = ref(props.filters.service        ?? '');
const project        = ref(props.filters.project        ?? '');
const province       = ref(props.filters.province       ?? '');
const district       = ref(props.filters.district       ?? '');
const location       = ref(props.filters.location       ?? '');
const referredTo     = ref(props.filters.referred_to    ?? '');
const repeatCaller   = ref(props.filters.repeat_caller  ?? '');
const callDirection  = ref(props.filters.call_direction ?? '');
const ageGroup       = ref(props.filters.age_group      ?? '');
const dateFrom       = ref(props.filters.date_from      ?? '');
const dateTo         = ref(props.filters.date_to        ?? '');
const showFilters    = ref(false);
const showAdd        = ref(false);

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (search.value)       params.set('search',        search.value);
    if (status.value)       params.set('status',        status.value);
    if (priority.value)     params.set('priority',      priority.value);
    if (agentId.value)      params.set('agent_id',      agentId.value);
    if (gender.value)       params.set('gender',        gender.value);
    if (service.value)      params.set('service',       service.value);
    if (project.value)      params.set('project',       project.value);
    if (province.value)     params.set('province',      province.value);
    if (district.value)     params.set('district',      district.value);
    if (location.value)     params.set('location',      location.value);
    if (referredTo.value)   params.set('referred_to',   referredTo.value);
    if (repeatCaller.value) params.set('repeat_caller', repeatCaller.value);
    if (callDirection.value)params.set('call_direction',callDirection.value);
    if (ageGroup.value)     params.set('age_group',     ageGroup.value);
    if (dateFrom.value)     params.set('date_from',     dateFrom.value);
    if (dateTo.value)       params.set('date_to',       dateTo.value);
    const qs = params.toString();
    return '/tickets/export' + (qs ? '?' + qs : '');
});

const activeFilterCount = computed(() =>
    [agentId, gender, service, project, province, district, location, referredTo, repeatCaller, callDirection, ageGroup, dateFrom, dateTo]
        .filter(r => r.value).length
);

function apply() {
    router.get('/tickets', {
        search:          search.value         || undefined,
        status:          status.value         || undefined,
        priority:        priority.value       || undefined,
        agent_id:        agentId.value        || undefined,
        gender:          gender.value         || undefined,
        service:         service.value        || undefined,
        project:         project.value        || undefined,
        province:        province.value       || undefined,
        district:        district.value       || undefined,
        location:        location.value       || undefined,
        referred_to:     referredTo.value     || undefined,
        repeat_caller:   repeatCaller.value   || undefined,
        call_direction:  callDirection.value  || undefined,
        age_group:       ageGroup.value       || undefined,
        date_from:       dateFrom.value       || undefined,
        date_to:         dateTo.value         || undefined,
    }, { preserveState: true, replace: true });
}

function clearFilters() {
    agentId.value = gender.value = service.value = project.value =
    province.value = district.value = location.value = referredTo.value =
    repeatCaller.value = callDirection.value = ageGroup.value =
    dateFrom.value = dateTo.value = '';
    apply();
}

const debouncedApply = debounce(apply, 350);
watch(search, debouncedApply);
watch([status, priority, agentId, gender, service, project, province, referredTo, repeatCaller, callDirection, ageGroup, dateFrom, dateTo], apply);
watch([location, district], debounce(apply, 350));

// ── Blocked Numbers ──────────────────────────────────────────────────────────
const showBlocked       = ref(false);
const blockedTab        = ref('requests'); // 'requests' | 'active'
const blockedLoading    = ref(false);
const blockedList       = ref([]);
const blockedTotal      = ref(0);
const blockedSearch     = ref('');
const blockedPage       = ref(1);
const showBlockedForm   = ref(false);
const editingBlocked    = ref(null);
const blockedForm       = ref({ name: '', numbers: '', limit_type: 'inbound' });
const blockedSaving     = ref(false);
const blockedError      = ref('');

// ── Requests ──────────────────────────────────────────────────────────────────
const reqList       = ref([]);
const reqLoading    = ref(false);
const reqError      = ref('');
const showReqForm   = ref(false);
const reqForm       = ref({ name: '', numbers: '', limit_type: 'inbound' });
const reqSaving     = ref(false);
const rejectingId   = ref(null);
const rejectReason  = ref('');
const pendingCount  = computed(() => reqList.value.filter(r => r.status === 'pending').length);

const SESSION_EXPIRED_MESSAGE = 'Your session has expired. The page will refresh automatically.';

function csrfHeader() {
    return { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content };
}

// Handles a stale/expired CSRF token uniformly: shows a message, then reloads
// so the user gets a fresh token instead of the request silently failing.
function handleExpiredSession(setError) {
    setError(SESSION_EXPIRED_MESSAGE);
    setTimeout(() => window.location.reload(), 1500);
}

async function loadRequests() {
    reqLoading.value = true;
    reqError.value   = '';
    try {
        const res  = await fetch('/blocked-number-requests');
        reqList.value = await res.json();
    } catch {
        reqError.value = 'Failed to load requests.';
    } finally {
        reqLoading.value = false;
    }
}

async function submitRequest() {
    reqSaving.value = true;
    reqError.value  = '';
    try {
        const res  = await fetch('/blocked-number-requests', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...csrfHeader() },
            body: JSON.stringify(reqForm.value),
        });

        if (res.status === 419) {
            handleExpiredSession(v => reqError.value = v);
            return;
        }

        let data;
        try {
            data = await res.json();
        } catch {
            reqError.value = `Unexpected server response (status ${res.status}).`;
            return;
        }

        if (res.status >= 400) { reqError.value = data.message || Object.values(data.errors ?? {})[0]?.[0] || 'Error.'; return; }
        showReqForm.value = false;
        reqForm.value = { name: '', numbers: '', limit_type: 'inbound' };
        loadRequests();
    } catch {
        reqError.value = 'Network error. Check your internet connection and try again.';
    } finally {
        reqSaving.value = false;
    }
}

async function approveRequest(id) {
    try {
        const res = await fetch(`/blocked-number-requests/${id}/approve`, {
            method: 'POST',
            headers: csrfHeader(),
        });
        if (res.status === 419) { handleExpiredSession(v => reqError.value = v); return; }
        const data = await res.json();
        if (data.ok) loadRequests();
        else alert(data.message || 'Approve failed.');
    } catch {
        reqError.value = 'Network error. Check your internet connection and try again.';
    }
}

async function rejectRequest(id) {
    try {
        const res = await fetch(`/blocked-number-requests/${id}/reject`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', ...csrfHeader() },
            body: JSON.stringify({ reason: rejectReason.value }),
        });
        if (res.status === 419) { handleExpiredSession(v => reqError.value = v); return; }
        const data = await res.json();
        if (data.ok) { rejectingId.value = null; rejectReason.value = ''; loadRequests(); }
        else alert(data.message || 'Reject failed.');
    } catch {
        reqError.value = 'Network error. Check your internet connection and try again.';
    }
}

async function deleteRequest(id) {
    if (!confirm('Delete this request?')) return;
    try {
        const res = await fetch(`/blocked-number-requests/${id}`, {
            method: 'DELETE',
            headers: csrfHeader(),
        });
        if (res.status === 419) { handleExpiredSession(v => reqError.value = v); return; }
        loadRequests();
    } catch {
        reqError.value = 'Network error. Check your internet connection and try again.';
    }
}

async function loadBlocked() {
    blockedLoading.value = true;
    blockedError.value   = '';
    try {
        const params = new URLSearchParams({ page: blockedPage.value });
        if (blockedSearch.value) params.set('search', blockedSearch.value);
        const res  = await fetch(`/blocked-numbers?${params}`);
        const data = await res.json();
        blockedList.value  = data.data  ?? [];
        blockedTotal.value = data.total ?? 0;
    } catch {
        blockedError.value = 'Failed to load blocked numbers.';
    } finally {
        blockedLoading.value = false;
    }
}

function openBlocked() {
    showBlocked.value = true;
    blockedTab.value  = 'requests';
    loadRequests();
}

watch(blockedTab, (t) => { if (t === 'active') loadBlocked(); });

const debouncedBlockedSearch = debounce(() => { blockedPage.value = 1; loadBlocked(); }, 350);
watch(blockedSearch, debouncedBlockedSearch);

function openAddBlocked() {
    editingBlocked.value = null;
    blockedForm.value    = { name: '', numbers: '', limit_type: 'inbound' };
    showBlockedForm.value = true;
}

function openEditBlocked(row) {
    editingBlocked.value  = row;
    blockedForm.value     = { name: row.name, numbers: row.number_list ?? '', limit_type: row.limit_type ?? 'inbound' };
    showBlockedForm.value = true;
}

async function saveBlocked() {
    blockedSaving.value = true;
    blockedError.value  = '';
    try {
        const url    = editingBlocked.value ? `/blocked-numbers/${editingBlocked.value.id}` : '/blocked-numbers';
        const method = editingBlocked.value ? 'PUT' : 'POST';
        const res    = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', ...csrfHeader() },
            body: JSON.stringify(blockedForm.value),
        });
        if (res.status === 419) { handleExpiredSession(v => blockedError.value = v); return; }
        const data = await res.json();
        if (!data.ok) { blockedError.value = data.message || 'Error saving.'; return; }
        showBlockedForm.value = false;
        loadBlocked();
    } catch {
        blockedError.value = 'Network error.';
    } finally {
        blockedSaving.value = false;
    }
}

async function deleteBlocked(id) {
    if (!confirm('Delete this blocked number rule?')) return;
    try {
        const res = await fetch(`/blocked-numbers/${id}`, {
            method: 'DELETE',
            headers: csrfHeader(),
        });
        if (res.status === 419) { handleExpiredSession(v => blockedError.value = v); return; }
        const data = await res.json();
        if (data.ok) loadBlocked();
        else alert(data.message || 'Delete failed.');
    } catch {
        blockedError.value = 'Network error.';
    }
}


const showContactDrop = ref(false);
const contactResults  = ref([]);

const debouncedContactSearch = debounce(async (q) => {
    if (!q || q.length < 2) { contactResults.value = []; return; }
    const res = await fetch(`/calls/number-search?q=${encodeURIComponent(q)}`);
    contactResults.value = await res.json();
}, 300);

function onContactInput() { debouncedContactSearch(addForm.contact_number); }
function selectContact(num) {
    addForm.contact_number = num;
    showContactDrop.value = false;
    contactResults.value = [];
    lookupRecordingForNumber(num);
}

// ── Auto-attach today's call recording once the agent finishes entering the number ──
const recordingLookup = ref(null); // null | 'loading' | 'found' | 'processing' | 'not_found'
const recordingLookupMessage = ref('');
const foundRecordingId = ref(null); // set whenever a recording exists, independent of transcription status
let recordingLookupPollTimer = null;

async function lookupRecordingForNumber(number) {
    clearTimeout(recordingLookupPollTimer);
    if (!number || number.length < 6) {
        recordingLookup.value = null;
        foundRecordingId.value = null;
        return;
    }

    recordingLookup.value = 'loading';
    try {
        const res  = await fetch(`/calls/find-recording?number=${encodeURIComponent(number)}`);
        const data = await res.json();

        if (!data.found) {
            recordingLookup.value = 'not_found';
            recordingLookupMessage.value = "No recording found for this number today.";
            foundRecordingId.value = null;
            return;
        }

        foundRecordingId.value = data.recording_id;

        if (data.status === 'done' && data.ai_notes) {
            if (!addForm.description) addForm.description = data.ai_notes;
            recordingLookup.value = 'found';
            recordingLookupMessage.value = "Today's call recording found — notes auto-filled.";
        } else if (data.status === 'processing' || data.status === 'pending') {
            recordingLookup.value = 'processing';
            recordingLookupMessage.value = "Recording found — transcribing and drafting a summary…";
            recordingLookupPollTimer = setTimeout(() => lookupRecordingForNumber(number), 5000);
        } else {
            recordingLookup.value = 'found';
            recordingLookupMessage.value = 'Recording found, but no summary is available yet.';
        }
    } catch {
        recordingLookup.value = null;
    }
}

function onContactBlur() {
    setTimeout(() => { showContactDrop.value = false; }, 150);
    lookupRecordingForNumber(addForm.contact_number);
}

// ── Referred To combobox ──────────────────────────────────────────────────────
const referredToSearch   = ref('');
const showReferredToDrop = ref(false);

const filteredReferredTo = computed(() => {
    const q = referredToSearch.value.toLowerCase().trim();
    if (!q) return props.referredTo ?? [];
    return (props.referredTo ?? []).filter(r => r.toLowerCase().includes(q));
});

function onReferredToInput(val) {
    referredToSearch.value  = val;
    addForm.referred_to     = val;   // allow free text
    showReferredToDrop.value = true;
}

function selectReferredTo(val) {
    addForm.referred_to      = val;
    referredToSearch.value   = val;
    showReferredToDrop.value = false;
}

function openReferredToDrop() {
    referredToSearch.value   = addForm.referred_to ?? '';
    showReferredToDrop.value = true;
}

function closeReferredToDrop() {
    setTimeout(() => { showReferredToDrop.value = false; }, 150);
}

function openAdd() {
    addForm.reset();
    recordingLookup.value = null;
    recordingLookupMessage.value = '';
    foundRecordingId.value = null;
    clearTimeout(recordingLookupPollTimer);
    showAdd.value = true;
}

const addForm = useForm({
    subject: '', contact_number: '', sisters_number: '', description: '', priority: 'medium', status: 'in_progress', follow_up_date: '',
    // CRM fields
    mode_of_communication:    'phone',
    call_validity:            'valid',
    purpose_of_call:          '',
    immediate_action_required: '',
    action_status:             '',
    caller_age:               '',
    caller_gender:            '',
    caller_marital_status:    '',
    key_pops:                 '',
    province:                 '',
    district:                 '',
    location:                 '',
    is_repeat_caller:         '',
    project:                   '',
    services_requested_before: '',
    services_requested:        '',
    second_service_requested: '',
    number_of_services:       '',
    referred_to:              '',
    uptake_confirmed:         false,
    referral_uptake_date:     '',
    classification:           {},
    psychosocial_type:        '',
});

function store() {
    addForm.post('/tickets', { onSuccess: () => { showAdd.value = false; addForm.reset(); } });
}

const draftingNotes = ref(false);
const DRAFT_POLL_MAX_ATTEMPTS = 8; // ~40s of retrying at 5s intervals

async function fetchAiNotes(recordingId) {
    const res = await fetch(`/api/recordings/${recordingId}/ai-notes`, {
        headers: { 'Accept': 'application/json' },
    });
    return res.json();
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// If a recording was found for this number (see lookupRecordingForNumber),
// "AI Draft Notes"/"Generate Summary from Recording" pulls the real summary
// from that recording's transcript. Many recordings never actually had
// transcription triggered (e.g. ones created by the periodic CDR sync
// rather than the real-time call-end webhook) — clicking this button used
// to just check status and give up if it wasn't already 'done', which did
// nothing for those. It now actively kicks off transcription and polls
// until a summary is ready, rather than passively waiting on a job that
// may never have been dispatched in the first place.
async function draftNotes() {
    draftingNotes.value = true;
    try {
        if (foundRecordingId.value) {
            let data = await fetchAiNotes(foundRecordingId.value);

            if (data.status !== 'done' && data.status !== 'processing') {
                // Not already running — actively trigger it instead of just checking.
                recordingLookup.value = 'processing';
                recordingLookupMessage.value = 'Starting transcription…';
                await fetch(`/api/recordings/${foundRecordingId.value}/transcribe`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
            }

            for (let attempt = 0; attempt < DRAFT_POLL_MAX_ATTEMPTS; attempt++) {
                if (data.status === 'done' && data.ai_notes) {
                    addForm.description = data.ai_notes;
                    recordingLookup.value = 'found';
                    recordingLookupMessage.value = "Today's call recording found — notes auto-filled.";
                    return;
                }
                if (data.status === 'failed') break;

                recordingLookup.value = 'processing';
                recordingLookupMessage.value = 'Transcribing the recording and drafting a summary…';
                await sleep(5000);
                data = await fetchAiNotes(foundRecordingId.value);
            }

            recordingLookup.value = 'not_found';
            recordingLookupMessage.value = data.status === 'failed'
                ? 'Transcription failed for this recording. You can type notes manually.'
                : 'Still transcribing — try the button again in a moment.';
            return;
        }

        const res = await fetch('/tickets/draft-notes', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({
                subject: addForm.subject,
                purpose_of_call: addForm.purpose_of_call,
                services_requested: addForm.services_requested,
                second_service_requested: addForm.second_service_requested,
                caller_age: addForm.caller_age,
                caller_gender: addForm.caller_gender,
                caller_marital_status: addForm.caller_marital_status,
                key_pops: addForm.key_pops,
                province: addForm.province,
                district: addForm.district,
                location: addForm.location,
                mode_of_communication: addForm.mode_of_communication,
                priority: addForm.priority,
                action_status: addForm.action_status,
                referred_to: addForm.referred_to,
                is_repeat_caller: addForm.is_repeat_caller,
                psychosocial_type: addForm.psychosocial_type,
                classification: addForm.classification,
            }),
        });
        const json = await res.json();
        if (json.note) addForm.description = json.note;
    } catch {
        // silently fail — counsellor can type manually
    } finally {
        draftingNotes.value = false;
    }
}

function deleteTicket(ticket) {
    if (!confirm(`Delete ticket #${ticket.id} "${ticket.subject}"? This cannot be undone.`)) return;
    router.delete(`/tickets/${ticket.id}`, {
        preserveScroll: false,
        onError: (errors) => {
            const msg = Object.values(errors)[0] ?? 'Delete failed. Please refresh and try again.';
            alert(msg);
        },
    });
}

function setReferralDate(ticket, date) {
    router.put(`/tickets/${ticket.id}`,
        { referral_uptake_date: date || null },
        { preserveState: true, preserveScroll: true }
    );
}

const provinces = [
    'Bulawayo', 'Harare', 'Manicaland', 'Mashonaland Central',
    'Mashonaland East', 'Mashonaland West', 'Masvingo',
    'Matabeleland North', 'Matabeleland South', 'Midlands',
];

const priorityColor = {
    low:    'bg-gray-100 text-gray-600',
    medium: 'bg-blue-100 text-blue-800',
    high:   'bg-orange-100 text-orange-800',
    urgent: 'bg-red-100 text-red-800',
};
const statusColor = {
    open:        'bg-yellow-100 text-yellow-800',
    in_progress: 'bg-blue-100 text-blue-800',
    ongoing:     'bg-purple-100 text-purple-800',
    resolved:    'bg-green-100 text-green-800',
    closed:      'bg-gray-100 text-gray-600',
};
</script>

<template>
    <AppLayout>
        <template #title>Tickets</template>
        <template #header-actions>
            <button @click="openBlocked" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                <NoSymbolIcon class="h-4 w-4" /> Blocked Numbers
            </button>
            <Link v-if="isAdmin" href="/tickets/import" class="btn-secondary btn-sm">
                <ArrowUpTrayIcon class="h-4 w-4" /> Import
            </Link>
            <button @click="openAdd" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Ticket
            </button>
        </template>

        <!-- ── Filter bar ── -->
        <div class="card mb-4 space-y-3">
            <!-- Row 1: search + status + priority + toggle -->
            <div class="flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-48">
                    <label class="label">Search</label>
                    <div class="relative">
                        <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                        <input v-model="search" class="input pl-9" placeholder="Subject, description, phone…" />
                    </div>
                </div>
                <div>
                    <label class="label">Status</label>
                    <select v-model="status" class="input w-36">
                        <option value="">All</option>
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div>
                    <label class="label">Priority</label>
                    <select v-model="priority" class="input w-32">
                        <option value="">All</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button @click="showFilters = !showFilters"
                        class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M3 4h18M7 8h10M11 12h2"/></svg>
                        More Filters
                        <span v-if="activeFilterCount"
                            class="bg-brand-600 text-white text-xs rounded-full px-1.5 py-0.5 font-bold">
                            {{ activeFilterCount }}
                        </span>
                    </button>
                    <a :href="exportUrl"
                        class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                        <ArrowDownTrayIcon class="h-4 w-4" /> Export CSV
                    </a>
                    <button v-if="activeFilterCount" @click="clearFilters"
                        class="btn-secondary btn-sm text-red-500 hover:text-red-700 inline-flex items-center gap-1">
                        <XMarkIcon class="h-3.5 w-3.5" /> Clear
                    </button>
                </div>
            </div>

            <!-- Row 2: advanced filters (collapsible) -->
            <div v-if="showFilters" class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2 border-t border-gray-100">
                <!-- Agent (admin only) -->
                <div v-if="isAdmin">
                    <label class="label">Agent</label>
                    <select v-model="agentId" class="input">
                        <option value="">All Agents</option>
                        <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                </div>
                <!-- Date range -->
                <div>
                    <label class="label">Date From</label>
                    <input type="date" v-model="dateFrom" class="input" />
                </div>
                <div>
                    <label class="label">Date To</label>
                    <input type="date" v-model="dateTo" class="input" />
                </div>
                <!-- Gender -->
                <div>
                    <label class="label">Gender</label>
                    <select v-model="gender" class="input">
                        <option value="">All</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                        <option value="prefer_not_to_say">Prefer not to say</option>
                    </select>
                </div>
                <!-- Age group -->
                <div>
                    <label class="label">Age Group</label>
                    <select v-model="ageGroup" class="input">
                        <option value="">All Ages</option>
                        <option value="under_18">Under 18</option>
                        <option value="18_24">18 – 24</option>
                        <option value="25_34">25 – 34</option>
                        <option value="35_44">35 – 44</option>
                        <option value="45_plus">45+</option>
                    </select>
                </div>
                <!-- Repeat caller -->
                <div>
                    <label class="label">Caller Type</label>
                    <select v-model="repeatCaller" class="input">
                        <option value="">All</option>
                        <option value="0">New Callers</option>
                        <option value="1">Repeat Callers</option>
                    </select>
                </div>
                <!-- Call direction (inbound / outbound via linked call) -->
                <div>
                    <label class="label">Call Direction</label>
                    <select v-model="callDirection" class="input">
                        <option value="">All</option>
                        <option value="inbound">Inbound</option>
                        <option value="outbound">Outbound</option>
                    </select>
                </div>
                <!-- Service requested -->
                <div>
                    <label class="label">Service Requested</label>
                    <select v-model="service" class="input">
                        <option value="">All Services</option>
                        <option v-for="s in servicesRequested" :key="s" :value="s">{{ s }}</option>
                    </select>
                </div>
                <!-- Project -->
                <div>
                    <label class="label">Project</label>
                    <select v-model="project" class="input">
                        <option value="">All Projects</option>
                        <option v-for="p in projects" :key="p" :value="p">{{ p }}</option>
                    </select>
                </div>
                <!-- Province / Location -->
                <div>
                    <label class="label">Province</label>
                    <select v-model="province" class="input">
                        <option value="">All Provinces</option>
                        <option value="Bulawayo">Bulawayo</option>
                        <option value="Harare">Harare</option>
                        <option value="Manicaland">Manicaland</option>
                        <option value="Mashonaland Central">Mashonaland Central</option>
                        <option value="Mashonaland East">Mashonaland East</option>
                        <option value="Mashonaland West">Mashonaland West</option>
                        <option value="Masvingo">Masvingo</option>
                        <option value="Matabeleland North">Matabeleland North</option>
                        <option value="Matabeleland South">Matabeleland South</option>
                        <option value="Midlands">Midlands</option>
                    </select>
                </div>
                <!-- Location -->
                <div>
                    <label class="label">Location</label>
                    <input v-model="location" type="text" class="input" placeholder="Search location…" />
                </div>
                <!-- District -->
                <div>
                    <label class="label">District</label>
                    <input v-model="district" type="text" class="input" placeholder="Search district…" />
                </div>
                <!-- Referred To -->
                <div>
                    <label class="label">Referred To</label>
                    <select v-model="referredTo" class="input">
                        <option value="">All Referrals</option>
                        <option v-for="r in props.referredTo" :key="r" :value="r">{{ r }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Name</th>
                        <th class="table-th">Purpose</th>
                        <th class="table-th">Priority</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Ref. Uptake Date</th>
                        <th class="table-th">Agent</th>
                        <th class="table-th">Created</th>
                        <th class="table-th w-16" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!tickets.data.length">
                        <td colspan="8" class="py-12 text-center text-sm text-gray-400">No tickets found.</td>
                    </tr>
                    <tr v-for="t in tickets.data" :key="t.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium max-w-xs truncate">
                            <Link :href="`/tickets/${t.id}`" class="text-brand-600 hover:underline">{{ t.subject }}</Link>
                        </td>
                        <td class="table-td text-xs max-w-xs truncate">{{ t.purpose_of_call ?? '—' }}</td>
                        <td class="table-td">
                            <span :class="['badge', priorityColor[t.priority]]">{{ t.priority }}</span>
                        </td>
                        <td class="table-td">
                            <span :class="['badge', statusColor[t.status]]">{{ t.status.replace('_', ' ') }}</span>
                        </td>
                        <td class="table-td">
                            <template v-if="t.immediate_action_required">
                                <input
                                    type="date"
                                    :value="t.referral_uptake_date ?? ''"
                                    @change="setReferralDate(t, $event.target.value)"
                                    :class="[
                                        'block w-36 rounded border px-2 py-1 text-xs focus:outline-none focus:ring-1',
                                        t.referral_uptake_date
                                            ? 'border-green-300 bg-green-50 text-green-800 focus:ring-green-400'
                                            : 'border-red-300 bg-red-50 text-red-700 focus:ring-red-400'
                                    ]"
                                    title="Expected referral uptake date"
                                />
                                <p v-if="!t.referral_uptake_date" class="mt-0.5 text-[10px] text-red-400">Enter expected date</p>
                            </template>
                            <span v-else class="text-gray-300 text-xs">—</span>
                        </td>
                        <td class="table-td">{{ t.agent?.name ?? '—' }}</td>
                        <td class="table-td text-xs">{{ new Date(t.created_at).toLocaleDateString() }}</td>
                        <td class="table-td">
                            <div class="flex items-center gap-1.5">
                                <Link :href="`/tickets/${t.id}`" class="btn-secondary btn-sm">View</Link>
                                <Link v-if="t.contact_number" :href="`/recordings?search=${t.contact_number}`" class="text-brand-600 hover:underline text-xs font-medium" title="Find recordings for this number">
                                    Recordings
                                </Link>
                                <button v-if="isAdmin"
                                    @click="deleteTicket(t)"
                                    class="btn-sm inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-medium
                                           text-red-600 hover:bg-red-50 border border-red-200 hover:border-red-400 transition-colors">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="tickets.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">Showing {{ tickets.from }}–{{ tickets.to }} of {{ tickets.total }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in tickets.links" :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                        preserve-state v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- New ticket modal -->
        <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col">
                <!-- Modal header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">New Ticket</h3>
                    <button @click="showAdd = false" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <!-- Scrollable body -->
                <form @submit.prevent="store" class="overflow-y-auto flex-1 px-6 py-4 space-y-5">

                    <!-- ── Basic Info ── -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Basic Info</h4>
                        <div class="space-y-3">
                            <div>
                                <label class="label">Name *</label>
                                <input v-model="addForm.subject" class="input" :class="{ 'border-red-500': addForm.errors.subject }" required />
                                <p v-if="addForm.errors.subject" class="mt-1 text-xs text-red-600">{{ addForm.errors.subject }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="relative">
                                    <label class="label">Contact Number *</label>
                                    <input
                                        v-model="addForm.contact_number"
                                        @input="onContactInput"
                                        @focus="showContactDrop = true"
                                        @blur="onContactBlur"
                                        class="input"
                                        :class="{ 'border-red-500': addForm.errors.contact_number }"
                                        placeholder="Type or search number…"
                                        autocomplete="off"
                                        required
                                    />
                                    <p v-if="recordingLookupMessage" class="mt-1 text-xs" :class="{
                                        'text-green-600': recordingLookup === 'found',
                                        'text-amber-600': recordingLookup === 'processing',
                                        'text-gray-400':  recordingLookup === 'not_found',
                                    }">
                                        {{ recordingLookupMessage }}
                                    </p>
                                    <audio v-if="foundRecordingId" controls preload="none"
                                        class="mt-1.5 h-8 w-full"
                                        :src="`/api/recordings/${foundRecordingId}/download`" />
                                    <ul v-if="showContactDrop && contactResults.length"
                                        class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                        <li v-for="num in contactResults" :key="num">
                                            <button type="button"
                                                @mousedown.prevent="selectContact(num)"
                                                class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                {{ num }}
                                            </button>
                                        </li>
                                    </ul>
                                    <p v-if="addForm.errors.contact_number" class="mt-1 text-xs text-red-600">{{ addForm.errors.contact_number }}</p>
                                </div>
                                <div>
                                    <label class="label">Sisters Number</label>
                                    <input
                                        v-model="addForm.sisters_number"
                                        class="input"
                                        placeholder="Sister's contact number"
                                    />
                                    <p v-if="addForm.errors.sisters_number" class="mt-1 text-xs text-red-600">{{ addForm.errors.sisters_number }}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="label">Priority</label>
                                    <select v-model="addForm.priority" class="input">
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Case Status</label>
                                    <select v-model="addForm.status" class="input">
                                        <option value="in_progress">In Progress</option>
                                        <option value="ongoing">On Going</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="label">Follow-up Date</label>
                                <input v-model="addForm.follow_up_date" type="date" class="input w-48" />
                            </div>
                        </div>
                    </div>

                    <!-- ── Call Details ── -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Call Details</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">Mode of Communication</label>
                                <select v-model="addForm.mode_of_communication" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="m in props.modesOfCommunication" :key="m" :value="m">{{ m }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Call Validity</label>
                                <select v-model="addForm.call_validity" class="input">
                                    <option value="">— select —</option>
                                    <option value="valid">Valid</option>
                                    <option value="invalid">Invalid</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="label">Purpose of Call</label>
                                <select v-model="addForm.purpose_of_call" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="d in distressDomains" :key="d" :value="d">{{ d }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Project</label>
                                <select v-model="addForm.project" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="p in props.projects" :key="p" :value="p">{{ p }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Action Status</label>
                                <select v-model="addForm.action_status" class="input">
                                    <option value="">— select —</option>
                                    <option value="yes">Yes</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="pending">Pending</option>
                                    <option value="no">No</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Immediate Action Required</label>
                                <select v-model="addForm.immediate_action_required" class="input">
                                    <option value="">— select —</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                                <p v-if="addForm.immediate_action_required == 1"
                                    class="mt-1 text-xs text-red-600 font-medium">
                                    This will create an urgent case visible to all agents.
                                </p>
                            </div>
                            <div v-if="addForm.immediate_action_required == 1">
                                <label class="label">Referral Uptake Date</label>
                                <input v-model="addForm.referral_uptake_date" type="date" class="input" />
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
                                <input v-model="addForm.caller_age" type="number" min="1" max="120" class="input" />
                            </div>
                            <div>
                                <label class="label">Gender</label>
                                <select v-model="addForm.caller_gender" class="input">
                                    <option value="">— select —</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                    <option value="prefer_not_to_say">Prefer not to say</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Marital Status</label>
                                <select v-model="addForm.caller_marital_status" class="input">
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
                                <select v-model="addForm.key_pops" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="kp in props.keyPops" :key="kp" :value="kp">{{ kp }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Repeat Caller</label>
                                <select v-model="addForm.is_repeat_caller" class="input">
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
                                <select v-model="addForm.province" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="p in provinces" :key="p" :value="p">{{ p }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">District</label>
                                <input v-model="addForm.district" class="input" />
                            </div>
                            <div>
                                <label class="label">Location</label>
                                <input v-model="addForm.location" class="input" />
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
                                    <input v-model="addForm.services_requested_before" class="input" placeholder="Describe previous services…" />
                                </div>
                                <div>
                                    <label class="label">Services Requested</label>
                                    <select v-model="addForm.services_requested" class="input">
                                        <option value="">— select —</option>
                                        <option v-for="s in props.servicesRequested" :key="s" :value="s">{{ s }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Second Service Requested</label>
                                    <select v-model="addForm.second_service_requested" class="input">
                                        <option value="">— select —</option>
                                        <option v-for="s in props.secondServicesRequested" :key="s" :value="s">{{ s }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">No. of Services</label>
                                    <input v-model="addForm.number_of_services" type="number" min="0" class="input" />
                                </div>
                                <div class="relative">
                                    <label class="label">Referred To</label>
                                    <input
                                        :value="referredToSearch || addForm.referred_to"
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
                                    <input type="checkbox" v-model="addForm.uptake_confirmed" class="rounded border-gray-300 text-brand-600" />
                                    Confirming Uptake of Services
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- ── Classification ── -->
                    <div v-if="addForm.services_requested">
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Classification</h4>
                        <ClassificationPanel
                            :service="addForm.services_requested"
                            :service-categories="serviceCategories"
                            v-model="addForm.classification"
                            :psychosocial-type="addForm.psychosocial_type"
                            @update:psychosocial-type="v => addForm.psychosocial_type = v"
                        />
                    </div>

                    <!-- ── Notes ── -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Notes</h4>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="label">
                                    Counsellor's Notes
                                    <span v-if="recordingLookup === 'found'" class="text-green-600 font-normal">— auto-filled from today's call recording</span>
                                </label>
                                <button
                                    type="button"
                                    @click="draftNotes"
                                    :disabled="draftingNotes"
                                    :title="foundRecordingId ? 'Generate a summary from the found call recording' : 'Draft a note from the fields filled in so far'"
                                    class="flex items-center gap-1.5 text-xs font-medium text-brand-600 hover:text-brand-700 disabled:opacity-50"
                                >
                                    <svg v-if="!draftingNotes" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                                    </svg>
                                    {{ draftingNotes ? 'Drafting…' : (foundRecordingId ? 'Generate Summary from Recording' : 'AI Draft Notes') }}
                                </button>
                            </div>
                            <textarea v-model="addForm.description" class="input h-24 resize-none" placeholder="Notes from the session…" />
                        </div>
                    </div>
                </form>

                <!-- Modal footer -->
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="showAdd = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="store" class="btn-primary" :disabled="addForm.processing">
                        {{ addForm.processing ? 'Creating…' : 'Create Ticket' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Blocked Numbers Modal ─────────────────────────────────────────── -->
        <div v-if="showBlocked" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl flex flex-col max-h-[90vh]">

                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b">
                    <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                        <NoSymbolIcon class="h-5 w-5 text-red-500" /> Blocked Numbers
                    </h3>
                    <button @click="showBlocked = false; showBlockedForm = false; showReqForm = false" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <!-- Tabs -->
                <div class="flex border-b text-sm font-medium">
                    <button @click="blockedTab = 'requests'"
                        class="px-5 py-2.5 border-b-2 transition-colors"
                        :class="blockedTab === 'requests' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        Block Requests
                        <span v-if="pendingCount" class="ml-1.5 bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5">{{ pendingCount }}</span>
                    </button>
                    <button v-if="isAdmin" @click="blockedTab = 'active'"
                        class="px-5 py-2.5 border-b-2 transition-colors"
                        :class="blockedTab === 'active' ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        Active Rules
                    </button>
                </div>

                <!-- ── TAB: Requests ── -->
                <div v-if="blockedTab === 'requests'" class="flex flex-col flex-1 overflow-hidden">

                    <!-- Submit request form -->
                    <div v-if="showReqForm" class="px-6 py-4 border-b bg-gray-50 space-y-3">
                        <h4 class="text-sm font-medium text-gray-700">Request a Block</h4>
                        <div>
                            <label class="label">Rule Name</label>
                            <input v-model="reqForm.name" class="input" placeholder="e.g. Harassing caller" />
                        </div>
                        <div>
                            <label class="label">Phone Number(s) <span class="text-gray-400 text-xs">(comma-separated)</span></label>
                            <input v-model="reqForm.numbers" class="input" placeholder="e.g. 0771234567" />
                        </div>
                        <div>
                            <label class="label">Block Direction</label>
                            <select v-model="reqForm.limit_type" class="input w-40">
                                <option value="inbound">Inbound</option>
                                <option value="outbound">Outbound</option>
                                <option value="both">Both</option>
                            </select>
                        </div>
                        <p v-if="reqError" class="text-red-600 text-sm">{{ reqError }}</p>
                        <div class="flex gap-2">
                            <button @click="submitRequest" :disabled="reqSaving" class="btn-primary btn-sm">{{ reqSaving ? 'Submitting…' : 'Submit Request' }}</button>
                            <button @click="showReqForm = false; reqError = ''" class="btn-secondary btn-sm">Cancel</button>
                        </div>
                    </div>

                    <!-- Toolbar -->
                    <div class="px-6 py-3 flex justify-between items-center border-b">
                        <span class="text-sm text-gray-500">{{ reqList.length }} request(s)</span>
                        <button v-if="!showReqForm" @click="showReqForm = true" class="btn-primary btn-sm inline-flex items-center gap-1">
                            <PlusIcon class="h-4 w-4" /> Request Block
                        </button>
                    </div>

                    <!-- Requests list -->
                    <div class="overflow-y-auto flex-1 px-6 py-3">
                        <p v-if="reqLoading" class="text-sm text-gray-500 text-center py-6">Loading…</p>
                        <p v-else-if="reqError && !reqList.length" class="text-sm text-red-500 text-center py-6">{{ reqError }}</p>
                        <p v-else-if="!reqList.length" class="text-sm text-gray-400 text-center py-6">No block requests yet.</p>
                        <div v-else class="space-y-3">
                            <div v-for="r in reqList" :key="r.id"
                                class="border rounded-xl p-4 text-sm"
                                :class="{
                                    'border-amber-200 bg-amber-50': r.status === 'pending',
                                    'border-green-200 bg-green-50': r.status === 'approved',
                                    'border-red-200 bg-red-50':    r.status === 'rejected',
                                }">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-800">{{ r.name }}</p>
                                        <p class="text-gray-500 text-xs mt-0.5">{{ r.numbers }}</p>
                                        <div class="flex gap-3 mt-1 text-xs text-gray-400">
                                            <span>{{ r.limit_type }}</span>
                                            <span>by {{ r.requested_by?.name }}</span>
                                            <span>{{ new Date(r.created_at).toLocaleDateString() }}</span>
                                        </div>
                                        <p v-if="r.status === 'rejected' && r.rejection_reason" class="mt-1 text-xs text-red-600 italic">Reason: {{ r.rejection_reason }}</p>
                                        <p v-if="r.status !== 'pending'" class="mt-1 text-xs text-gray-400">Reviewed by {{ r.reviewed_by?.name }}</p>
                                    </div>
                                    <div class="flex flex-col items-end gap-2 shrink-0">
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                            :class="{
                                                'bg-amber-100 text-amber-700': r.status === 'pending',
                                                'bg-green-100 text-green-700': r.status === 'approved',
                                                'bg-red-100 text-red-700':    r.status === 'rejected',
                                            }">
                                            {{ r.status }}
                                        </span>
                                        <!-- Admin actions -->
                                        <div v-if="isAdmin && r.status === 'pending'" class="flex gap-1.5">
                                            <button @click="approveRequest(r.id)"
                                                class="text-xs px-2.5 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">
                                                Approve
                                            </button>
                                            <button @click="rejectingId = r.id; rejectReason = ''"
                                                class="text-xs px-2.5 py-1 bg-red-500 text-white rounded-lg hover:bg-red-600 font-medium">
                                                Reject
                                            </button>
                                        </div>
                                        <button v-if="isAdmin" @click="deleteRequest(r.id)" class="text-gray-300 hover:text-red-500">
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Inline reject reason input -->
                                <div v-if="isAdmin && rejectingId === r.id" class="mt-3 flex gap-2">
                                    <input v-model="rejectReason" class="input text-xs flex-1" placeholder="Reason (optional)" />
                                    <button @click="rejectRequest(r.id)" class="btn-sm bg-red-500 text-white hover:bg-red-600 rounded-lg px-3 text-xs font-medium">Confirm Reject</button>
                                    <button @click="rejectingId = null" class="btn-secondary btn-sm text-xs">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── TAB: Active Rules (admin only) ── -->
                <div v-if="blockedTab === 'active'" class="flex flex-col flex-1 overflow-hidden">

                    <!-- Add/Edit Form -->
                    <div v-if="showBlockedForm" class="px-6 py-4 border-b bg-gray-50 space-y-3">
                        <h4 class="text-sm font-medium text-gray-700">{{ editingBlocked ? 'Edit Rule' : 'Add Rule' }}</h4>
                        <div>
                            <label class="label">Rule Name</label>
                            <input v-model="blockedForm.name" class="input" placeholder="e.g. Spam Caller" />
                        </div>
                        <div>
                            <label class="label">Phone Numbers <span class="text-gray-400 text-xs">(comma-separated)</span></label>
                            <input v-model="blockedForm.numbers" class="input" placeholder="e.g. 0771234567,0779876543" />
                        </div>
                        <div>
                            <label class="label">Block Direction</label>
                            <select v-model="blockedForm.limit_type" class="input w-40">
                                <option value="inbound">Inbound</option>
                                <option value="outbound">Outbound</option>
                                <option value="both">Both</option>
                            </select>
                        </div>
                        <p v-if="blockedError" class="text-red-600 text-sm">{{ blockedError }}</p>
                        <div class="flex gap-2">
                            <button @click="saveBlocked" :disabled="blockedSaving" class="btn-primary btn-sm">{{ blockedSaving ? 'Saving…' : (editingBlocked ? 'Update' : 'Add Rule') }}</button>
                            <button @click="showBlockedForm = false" class="btn-secondary btn-sm">Cancel</button>
                        </div>
                    </div>

                    <!-- Search + Add -->
                    <div class="px-6 py-3 flex gap-2 items-center border-b">
                        <div class="relative flex-1">
                            <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                            <input v-model="blockedSearch" class="input pl-9" placeholder="Search by number or name…" />
                        </div>
                        <button v-if="!showBlockedForm" @click="openAddBlocked" class="btn-primary btn-sm inline-flex items-center gap-1">
                            <PlusIcon class="h-4 w-4" /> Add Rule
                        </button>
                    </div>

                    <!-- List -->
                    <div class="overflow-y-auto flex-1 px-6 py-3">
                        <p v-if="blockedLoading" class="text-sm text-gray-500 text-center py-6">Loading…</p>
                        <p v-else-if="blockedError && !blockedList.length" class="text-sm text-red-500 text-center py-6">{{ blockedError }}</p>
                        <p v-else-if="!blockedList.length" class="text-sm text-gray-400 text-center py-6">No blocked number rules found.</p>
                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 border-b">
                                    <th class="pb-2 font-medium">Name</th>
                                    <th class="pb-2 font-medium">Numbers</th>
                                    <th class="pb-2 font-medium">Direction</th>
                                    <th class="pb-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in blockedList" :key="row.id" class="border-b last:border-0 hover:bg-gray-50">
                                    <td class="py-2 pr-3 font-medium text-gray-800">{{ row.name }}</td>
                                    <td class="py-2 pr-3 text-gray-600 max-w-[200px] truncate" :title="row.number_list">{{ row.number_list }}</td>
                                    <td class="py-2 pr-3">
                                        <span class="text-xs rounded-full px-2 py-0.5 font-medium"
                                            :class="{ 'bg-blue-100 text-blue-700': row.limit_type === 'inbound', 'bg-orange-100 text-orange-700': row.limit_type === 'outbound', 'bg-purple-100 text-purple-700': row.limit_type === 'both' }">
                                            {{ row.limit_type }}
                                        </span>
                                    </td>
                                    <td class="py-2 flex gap-2 justify-end">
                                        <button @click="openEditBlocked(row)" class="text-gray-400 hover:text-blue-600" title="Edit">
                                            <PencilIcon class="h-4 w-4" />
                                        </button>
                                        <button @click="deleteBlocked(row.id)" class="text-gray-400 hover:text-red-600" title="Delete">
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="blockedTotal > 50" class="flex justify-between items-center mt-3 text-sm text-gray-500">
                            <span>{{ blockedTotal }} total rules</span>
                            <div class="flex gap-2">
                                <button :disabled="blockedPage <= 1" @click="blockedPage--; loadBlocked()" class="btn-secondary btn-sm" :class="{ 'opacity-40 cursor-not-allowed': blockedPage <= 1 }">Prev</button>
                                <button :disabled="blockedPage * 50 >= blockedTotal" @click="blockedPage++; loadBlocked()" class="btn-secondary btn-sm" :class="{ 'opacity-40 cursor-not-allowed': blockedPage * 50 >= blockedTotal }">Next</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
