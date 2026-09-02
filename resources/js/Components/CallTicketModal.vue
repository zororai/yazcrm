<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { PhoneIcon, ClockIcon } from '@heroicons/vue/24/outline';

const distressDomains = computed(() => usePage().props.distressDomains ?? []);

const props = defineProps({
    call: Object,   // { call_id, caller, callee, duration, direction, client, recording_id }
});

const emit = defineEmits(['close']);

const form = useForm({
    subject:     '',
    description: '',
    priority:    'medium',
    call_id:     props.call.call_id,
    // CRM fields — sensible defaults for a phone call
    mode_of_communication:    'phone',
    call_validity:            'valid',
    purpose_of_call:          '',
    immediate_action_required: false,
    caller_age:               '',
    caller_gender:            '',
    province:                 '',
    district:                 '',
    services_requested:       '',
    referred_to:              '',
    is_repeat_caller:         false,
    uptake_confirmed:         false,
});

form.subject = props.call.client
    ? `Call with ${props.call.client.name} — follow-up required`
    : `Call from ${props.call.caller} — follow-up required`;

function submit() {
    form.post('/tickets', {
        onSuccess: () => emit('close'),
    });
}

const aiStatus   = ref(null);   // null | 'loading' | 'done' | 'processing' | 'failed'
const aiMessage  = ref('');

async function loadAiNotes() {
    if (!props.call.recording_id) {
        aiMessage.value = 'No recording found for this call.';
        aiStatus.value  = 'failed';
        return;
    }
    aiStatus.value  = 'loading';
    aiMessage.value = '';
    try {
        const res  = await fetch(`/api/recordings/${props.call.recording_id}/ai-notes`, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();

        if (data.status === 'done' && data.ai_notes) {
            form.description = data.ai_notes;
            aiStatus.value   = 'done';
        } else if (data.status === 'processing' || data.status === 'pending') {
            aiStatus.value  = 'processing';
            aiMessage.value = 'Still transcribing — try again in a moment.';
        } else {
            aiStatus.value  = 'failed';
            aiMessage.value = 'Notes not ready yet. Try again shortly.';
        }
    } catch {
        aiStatus.value  = 'failed';
        aiMessage.value = 'Could not load AI notes.';
    }
}

function fmt(s) {
    if (!s) return '—';
    return `${Math.floor(s / 60)}m ${s % 60}s`;
}

const provinces = [
    'Bulawayo', 'Harare', 'Manicaland', 'Mashonaland Central',
    'Mashonaland East', 'Mashonaland West', 'Masvingo',
    'Matabeleland North', 'Matabeleland South', 'Midlands',
];

const priorityColor = {
    low:    'ring-gray-300 text-gray-600',
    medium: 'ring-blue-400 text-blue-700',
    high:   'ring-orange-400 text-orange-700',
    urgent: 'ring-red-500 text-red-700',
};
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden animate-slide-up flex flex-col max-h-[90vh]">

            <!-- Header — no close button: a ticket must be logged before this can be dismissed -->
            <div class="flex items-center justify-between bg-brand-600 px-5 py-4 flex-shrink-0">
                <div class="flex items-center gap-2 text-white">
                    <PhoneIcon class="h-5 w-5" />
                    <span class="font-semibold">Call ended — log a ticket</span>
                </div>
            </div>

            <!-- Call summary -->
            <div class="bg-brand-50 border-b border-brand-100 px-5 py-3 flex items-center gap-4 text-sm flex-shrink-0">
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

            <!-- Scrollable form -->
            <form @submit.prevent="submit" class="overflow-y-auto flex-1 p-5 space-y-4">

                <!-- Subject + priority -->
                <div>
                    <label class="label">Subject *</label>
                    <input
                        v-model="form.subject"
                        class="input"
                        :class="{ 'border-red-500': form.errors.subject }"
                        required autofocus
                    />
                    <p v-if="form.errors.subject" class="mt-1 text-xs text-red-600">{{ form.errors.subject }}</p>
                </div>

                <div>
                    <label class="label">Priority</label>
                    <div class="flex gap-2">
                        <button
                            v-for="p in ['low', 'medium', 'high', 'urgent']"
                            :key="p"
                            type="button"
                            @click="form.priority = p"
                            :class="[
                                'flex-1 py-1.5 rounded-lg text-xs font-medium capitalize border-2 transition-colors',
                                form.priority === p
                                    ? 'border-current ring-2 ' + priorityColor[p]
                                    : 'border-gray-200 text-gray-500 hover:border-gray-300',
                            ]"
                        >{{ p }}</button>
                    </div>
                </div>

                <!-- Call details -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Call Validity</label>
                        <select v-model="form.call_validity" class="input">
                            <option value="valid">Valid</option>
                            <option value="invalid">Invalid</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Purpose of Call</label>
                        <select v-model="form.purpose_of_call" class="input">
                            <option value="">— select —</option>
                            <option v-for="d in distressDomains" :key="d" :value="d">{{ d }}</option>
                        </select>
                    </div>
                </div>

                <!-- Caller info -->
                <div class="grid grid-cols-2 gap-3">
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
                </div>

                <!-- Services -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Services Requested</label>
                        <input v-model="form.services_requested" class="input" />
                    </div>
                    <div>
                        <label class="label">Referred To</label>
                        <select v-model="form.referred_to" class="input">
                            <option value="">— select —</option>
                            <option value="Clinic / Health Facility">Clinic / Health Facility</option>
                            <option value="Police Station">Police Station</option>
                            <option value="CeSHHAR / STI Clinic">CeSHHAR / STI Clinic</option>
                            <option value="School Headmaster">School Headmaster</option>
                            <option value="DSD / Social Welfare">DSD / Social Welfare</option>
                            <option value="Civil Court">Civil Court</option>
                            <option value="VFU (Victim Support)">VFU (Victim Support)</option>
                            <option value="YALEP Programme">YALEP Programme</option>
                        </select>
                    </div>
                </div>

                <!-- Checkboxes -->
                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                        <input type="checkbox" v-model="form.immediate_action_required" class="rounded border-gray-300 text-brand-600" />
                        Immediate Action Required
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                        <input type="checkbox" v-model="form.is_repeat_caller" class="rounded border-gray-300 text-brand-600" />
                        Repeat Caller
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                        <input type="checkbox" v-model="form.uptake_confirmed" class="rounded border-gray-300 text-brand-600" />
                        Uptake Confirmed
                    </label>
                </div>

                <!-- Notes -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="label">Counsellor's Notes <span class="text-gray-400 font-normal">(optional)</span></label>
                        <button
                            type="button"
                            @click="loadAiNotes"
                            :disabled="aiStatus === 'loading'"
                            class="flex items-center gap-1.5 text-xs font-medium text-brand-600 hover:text-brand-700 disabled:opacity-50"
                        >
                            <svg v-if="aiStatus !== 'loading'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                            {{ aiStatus === 'loading' ? 'Loading…' : 'Load AI Notes' }}
                        </button>
                    </div>
                    <p v-if="aiMessage" class="mb-1 text-xs" :class="aiStatus === 'done' ? 'text-green-600' : 'text-amber-600'">
                        {{ aiMessage }}
                    </p>
                    <textarea
                        v-model="form.description"
                        class="input h-20 resize-none"
                        placeholder="What was discussed on this call…"
                    />
                </div>
            </form>

            <!-- Footer — Create Ticket is the only way to close this modal -->
            <div class="flex gap-2 px-5 py-4 border-t border-gray-100 flex-shrink-0">
                <button type="button" @click="submit" class="btn-primary flex-1 justify-center" :disabled="form.processing">
                    {{ form.processing ? 'Creating…' : 'Create Ticket' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes slide-up {
    from { transform: translateY(1rem); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
}
.animate-slide-up { animation: slide-up 0.2s ease-out; }
</style>
