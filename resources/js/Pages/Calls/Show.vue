<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon, LinkIcon, ArrowDownTrayIcon, LanguageIcon, ArrowPathIcon, ClipboardDocumentIcon, SparklesIcon, CheckIcon, PencilIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ call: Object, clients: Array, can: Object });

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

// ── Transcription ────────────────────────────────────────────────────────────
const transcriptStatusColor = {
    pending:    'bg-gray-100 text-gray-600',
    processing: 'bg-amber-100 text-amber-800',
    completed:  'bg-green-100 text-green-800',
    failed:     'bg-red-100 text-red-800',
    cancelled:  'bg-gray-100 text-gray-500',
};

const transcribeForm = useForm({ language: 'shona' });
const showTranscript = ref(false);
const copied = ref(false);

function requestTranscription() {
    transcribeForm.post(`/calls/${props.call.id}/transcript`);
}

function retryTranscription() {
    router.post(`/calls/${props.call.id}/transcript/retry`);
}

function toggleTranscript() {
    showTranscript.value = !showTranscript.value;
    if (showTranscript.value) {
        router.post(`/calls/${props.call.id}/transcript/viewed`, {}, { preserveScroll: true, preserveState: true, onSuccess: () => {} });
    }
}

async function copyTranscript() {
    await navigator.clipboard.writeText(props.call.transcript.transcript);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 1500);
}

// ── AI Case Intelligence ──────────────────────────────────────────────────────
const aiAnalysisStatusColor = {
    pending_review: 'bg-amber-100 text-amber-800',
    accepted:       'bg-green-100 text-green-800',
    edited:         'bg-blue-100 text-blue-800',
    rejected:       'bg-gray-100 text-gray-500',
};

const editingAiSummary = ref(false);
const reviewForm = useForm({ action: '', reviewed_summary: '' });

function requestAiAnalysis() {
    router.post(`/calls/${props.call.id}/ai-analysis`);
}

function acceptAiSummary() {
    reviewForm.action = 'accept';
    reviewForm.post(`/calls/${props.call.id}/ai-analysis/review`);
}

function startEditAiSummary() {
    reviewForm.reviewed_summary = props.call.ai_analysis?.ai_summary ?? '';
    editingAiSummary.value = true;
}

function saveEditedAiSummary() {
    reviewForm.action = 'edit';
    reviewForm.post(`/calls/${props.call.id}/ai-analysis/review`, {
        onSuccess: () => { editingAiSummary.value = false; },
    });
}

function rejectAiSummary() {
    if (! confirm('Reject this AI-generated summary?')) return;
    reviewForm.action = 'reject';
    reviewForm.post(`/calls/${props.call.id}/ai-analysis/review`);
}
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
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-800">Recording</h3>
                    <a :href="`/api/recordings/${call.recording.id}/download?download=1`" download
                        class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline">
                        <ArrowDownTrayIcon class="h-4 w-4" /> Download
                    </a>
                </div>
                <audio controls class="w-full" :src="`/api/recordings/${call.recording.id}/download`" />
            </div>

            <!-- Transcription -->
            <div v-if="call.recording" class="card">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                        <LanguageIcon class="h-4 w-4 text-gray-400" /> Transcription
                    </h3>
                    <span v-if="call.transcript" :class="['badge capitalize', transcriptStatusColor[call.transcript.status]]">
                        {{ call.transcript.status }}
                    </span>
                </div>

                <!-- Not yet requested -->
                <div v-if="!call.transcript" class="flex items-center gap-3">
                    <select v-if="can.transcribe" v-model="transcribeForm.language" class="input w-40">
                        <option value="shona">Shona</option>
                        <option value="english">English</option>
                        <option value="ndebele">Ndebele</option>
                    </select>
                    <button v-if="can.transcribe" @click="requestTranscription" class="btn-primary btn-sm" :disabled="transcribeForm.processing">
                        Transcribe Call
                    </button>
                    <p v-else class="text-sm text-gray-400">No transcription has been requested for this call.</p>
                </div>

                <!-- Pending / processing -->
                <div v-else-if="['pending', 'processing'].includes(call.transcript.status)" class="text-sm text-gray-500">
                    Transcription processing…
                </div>

                <!-- Failed -->
                <div v-else-if="call.transcript.status === 'failed'">
                    <p class="text-sm text-red-600 mb-2">Transcription failed.</p>
                    <p class="text-xs text-gray-500 mb-3">Reason: {{ call.transcript.error_message ?? 'Unknown error.' }}</p>
                    <button v-if="can.transcribe" @click="retryTranscription" class="btn-secondary btn-sm">
                        <ArrowPathIcon class="h-4 w-4" /> Retry
                    </button>
                </div>

                <!-- Completed -->
                <div v-else-if="call.transcript.status === 'completed'">
                    <dl class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm mb-3">
                        <div><dt class="text-gray-500">Language</dt><dd class="font-medium capitalize">{{ call.transcript.language }}</dd></div>
                        <div><dt class="text-gray-500">Model</dt><dd class="font-medium text-xs">{{ call.transcript.model }}</dd></div>
                        <div v-if="call.transcript.confidence"><dt class="text-gray-500">Confidence</dt><dd class="font-medium">{{ Math.round(call.transcript.confidence * 100) }}%</dd></div>
                    </dl>

                    <div class="flex items-center gap-3 mb-3">
                        <button @click="toggleTranscript" class="btn-secondary btn-sm">
                            {{ showTranscript ? 'Hide Transcript' : 'View Transcript' }}
                        </button>
                        <button v-if="showTranscript" @click="copyTranscript" class="text-sm text-gray-500 hover:text-gray-700 inline-flex items-center gap-1">
                            <ClipboardDocumentIcon class="h-4 w-4" /> {{ copied ? 'Copied!' : 'Copy' }}
                        </button>
                        <a v-if="can.exportTranscript" :href="`/calls/${call.id}/transcript/export`" class="text-sm text-brand-600 hover:underline inline-flex items-center gap-1">
                            <ArrowDownTrayIcon class="h-4 w-4" /> Download
                        </a>
                    </div>

                    <p v-if="showTranscript" class="text-sm text-gray-700 whitespace-pre-line bg-gray-50 rounded-lg p-3">
                        {{ call.transcript.transcript }}
                    </p>
                </div>
            </div>

            <!-- AI Case Intelligence -->
            <div v-if="call.transcript?.status === 'completed'" class="card">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                        <SparklesIcon class="h-4 w-4 text-gray-400" /> AI Case Intelligence
                    </h3>
                    <span v-if="call.ai_analysis" :class="['badge capitalize', aiAnalysisStatusColor[call.ai_analysis.status]]">
                        {{ call.ai_analysis.status === 'pending_review' ? 'Review Required' : call.ai_analysis.status }}
                    </span>
                </div>

                <!-- Not yet requested -->
                <div v-if="!call.ai_analysis" class="flex items-center gap-3">
                    <button v-if="can.reviewAiAnalysis" @click="requestAiAnalysis" class="btn-primary btn-sm">
                        Generate AI Summary
                    </button>
                    <p v-else class="text-sm text-gray-400">No AI summary has been generated for this call.</p>
                </div>

                <!-- Pending -->
                <div v-else-if="call.ai_analysis.analysis_status === 'pending'" class="text-sm text-gray-500">
                    Generating AI summary…
                </div>

                <!-- Failed -->
                <div v-else-if="call.ai_analysis.analysis_status === 'failed'">
                    <p class="text-sm text-red-600 mb-2">AI analysis failed.</p>
                    <p class="text-xs text-gray-500 mb-3">{{ call.ai_analysis.error_message ?? 'Unknown error.' }}</p>
                    <button v-if="can.reviewAiAnalysis" @click="requestAiAnalysis" class="btn-secondary btn-sm">
                        <ArrowPathIcon class="h-4 w-4" /> Retry
                    </button>
                </div>

                <!-- Completed -->
                <div v-else-if="call.ai_analysis.analysis_status === 'completed'">
                    <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">
                        This summary was generated by AI and is an assistant recommendation only. It does not
                        represent a confirmed case decision until reviewed by a staff member.
                    </p>

                    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm mb-3">
                        <div><dt class="text-gray-500">Category</dt><dd class="font-medium">{{ call.ai_analysis.ai_category ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Priority</dt><dd class="font-medium capitalize">{{ call.ai_analysis.ai_priority ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Follow-up</dt><dd class="font-medium">{{ call.ai_analysis.ai_follow_up_required ? 'Required' : 'Not required' }}</dd></div>
                        <div><dt class="text-gray-500">Referral</dt><dd class="font-medium">{{ call.ai_analysis.ai_referral_required ? 'Required' : 'Not required' }}</dd></div>
                    </dl>

                    <div class="mb-3">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">AI-Generated Summary</p>
                        <p class="text-sm text-gray-700 whitespace-pre-line bg-gray-50 rounded-lg p-3">{{ call.ai_analysis.ai_summary }}</p>
                    </div>

                    <div v-if="call.ai_analysis.reviewed_summary && call.ai_analysis.status !== 'rejected'" class="mb-3">
                        <p class="text-xs font-semibold text-green-700 uppercase tracking-wide mb-1">Human-Confirmed Summary</p>
                        <p class="text-sm text-gray-800 whitespace-pre-line bg-green-50 border border-green-100 rounded-lg p-3">{{ call.ai_analysis.reviewed_summary }}</p>
                        <p class="text-xs text-gray-400 mt-1">Reviewed by {{ call.ai_analysis.reviewer?.name ?? '—' }} on {{ new Date(call.ai_analysis.reviewed_at).toLocaleString() }}</p>
                    </div>
                    <div v-else-if="call.ai_analysis.status === 'rejected'" class="mb-3">
                        <p class="text-sm text-gray-500">Rejected by {{ call.ai_analysis.reviewer?.name ?? '—' }} on {{ new Date(call.ai_analysis.reviewed_at).toLocaleString() }}. Not used.</p>
                    </div>

                    <!-- Review actions (only while pending review) -->
                    <div v-if="can.reviewAiAnalysis && call.ai_analysis.status === 'pending_review'">
                        <div v-if="!editingAiSummary" class="flex gap-2">
                            <button @click="acceptAiSummary" class="btn-primary btn-sm"><CheckIcon class="h-4 w-4" /> Accept</button>
                            <button @click="startEditAiSummary" class="btn-secondary btn-sm"><PencilIcon class="h-4 w-4" /> Edit</button>
                            <button @click="rejectAiSummary" class="btn-secondary btn-sm text-red-600"><XMarkIcon class="h-4 w-4" /> Reject</button>
                        </div>
                        <div v-else class="space-y-2">
                            <textarea v-model="reviewForm.reviewed_summary" class="input h-24 resize-none" placeholder="Edit the summary before confirming…"></textarea>
                            <div class="flex gap-2">
                                <button @click="saveEditedAiSummary" class="btn-primary btn-sm" :disabled="reviewForm.processing">Save &amp; Confirm</button>
                                <button @click="editingAiSummary = false" class="btn-secondary btn-sm">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
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
