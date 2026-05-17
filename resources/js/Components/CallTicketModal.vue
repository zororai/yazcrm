<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { PhoneIcon, XMarkIcon, ClockIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    call: Object,   // { call_id, caller, callee, duration, direction, client }
});

const emit = defineEmits(['close']);

const form = useForm({
    subject:     '',
    description: '',
    priority:    'medium',
    call_id:     props.call.call_id,
    client_id:   props.call.client?.id ?? '',
});

// Pre-fill subject from call data
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

const priorityColor = {
    low:    'ring-gray-300 text-gray-600',
    medium: 'ring-blue-400 text-blue-700',
    high:   'ring-orange-400 text-orange-700',
    urgent: 'ring-red-500 text-red-700',
};
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden animate-slide-up">

            <!-- Header -->
            <div class="flex items-center justify-between bg-brand-600 px-5 py-4">
                <div class="flex items-center gap-2 text-white">
                    <PhoneIcon class="h-5 w-5" />
                    <span class="font-semibold">Call ended — log a ticket?</span>
                </div>
                <button @click="emit('close')" class="text-white/70 hover:text-white transition-colors">
                    <XMarkIcon class="h-5 w-5" />
                </button>
            </div>

            <!-- Call summary -->
            <div class="bg-brand-50 border-b border-brand-100 px-5 py-3 flex items-center gap-4 text-sm">
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

            <!-- Form -->
            <form @submit.prevent="submit" class="p-5 space-y-4">
                <div>
                    <label class="label">Subject *</label>
                    <input
                        v-model="form.subject"
                        class="input"
                        :class="{ 'border-red-500': form.errors.subject }"
                        required
                        autofocus
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
                        >
                            {{ p }}
                        </button>
                    </div>
                </div>

                <div>
                    <label class="label">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea
                        v-model="form.description"
                        class="input h-20 resize-none"
                        placeholder="What was discussed on this call…"
                    />
                </div>

                <div class="flex gap-2 pt-1">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="btn-secondary flex-1 justify-center"
                    >
                        Dismiss
                    </button>
                    <button
                        type="submit"
                        class="btn-primary flex-1 justify-center"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Creating…' : 'Create Ticket' }}
                    </button>
                </div>
            </form>
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
