<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { PhoneIcon, XMarkIcon, ClockIcon } from '@heroicons/vue/24/outline';

const distressDomains = computed(() => usePage().props.distressDomains ?? []);

const props = defineProps({
    call: Object,   // { call_id, caller, callee, duration, direction, client }
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

            <!-- Header -->
            <div class="flex items-center justify-between bg-brand-600 px-5 py-4 flex-shrink-0">
                <div class="flex items-center gap-2 text-white">
                    <PhoneIcon class="h-5 w-5" />
                    <span class="font-semibold">Call ended — log a ticket?</span>
                </div>
                <button @click="emit('close')" class="text-white/70 hover:text-white transition-colors">
                    <XMarkIcon class="h-5 w-5" />
                </button>
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
                            <option value="Clinic / Health Facility">Clinic / Health Facility — SRHR, MH referrals</option>
                            <option value="Police Station">Police Station — GBV &amp; protection cases</option>
                            <option value="CeSHHAR / STI Clinic">CeSHHAR / STI Clinic — SRHR specialist service</option>
                            <option value="School Headmaster">School Headmaster — Child protection</option>
                            <option value="DSD / Social Welfare">DSD / Social Welfare — Socioeconomic cases</option>
                            <option value="Civil Court">Civil Court — Legal justice cases</option>
                            <option value="VFU (Victim Support)">VFU (Victim Support) — Violence survivors</option>
                            <option value="YALEP Programme">YALEP Programme — Education track</option>
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
                    <label class="label">Counsellor's Notes <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea
                        v-model="form.description"
                        class="input h-20 resize-none"
                        placeholder="What was discussed on this call…"
                    />
                </div>
            </form>

            <!-- Footer -->
            <div class="flex gap-2 px-5 py-4 border-t border-gray-100 flex-shrink-0">
                <button type="button" @click="emit('close')" class="btn-secondary flex-1 justify-center">
                    Dismiss
                </button>
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
