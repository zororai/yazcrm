<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { BellAlertIcon, XMarkIcon } from '@heroicons/vue/24/outline';

// The agent's own cases whose follow-up (48 h for High / Urgent, 7 days for
// Low) or expected referral uptake (96 h for High / Urgent) is due. Polled
// every minute; "Remind me later" hides it for 30 minutes, but a newly due
// case brings it straight back.
const props = defineProps({ userId: { type: Number, required: true } });

const SNOOZE_MS = 30 * 60 * 1000;
const POLL_MS   = 60 * 1000;
const storageKey = `follow-ups-snoozed:${props.userId}`;

const tickets = ref([]);
const count   = ref(0);
const today   = ref('');
const snooze  = ref(readSnooze()); // { until, ids }
let timer = null;

function readSnooze() {
    try { return JSON.parse(localStorage.getItem(storageKey)) ?? { until: 0, ids: [] }; }
    catch { return { until: 0, ids: [] }; }
}

function remindLater() {
    snooze.value = { until: Date.now() + SNOOZE_MS, ids: tickets.value.map(t => t.id) };
    try { localStorage.setItem(storageKey, JSON.stringify(snooze.value)); } catch { /* private window */ }
}

const visible = computed(() => {
    if (!tickets.value.length) return false;
    if (Date.now() >= snooze.value.until) return true;
    return tickets.value.some(t => !snooze.value.ids.includes(t.id)); // something new since snoozing
});

async function poll() {
    try {
        const { data } = await axios.get('/tickets/follow-ups-due');
        tickets.value = data.tickets ?? [];
        count.value   = data.count ?? 0;
        today.value   = data.today ?? '';
    } catch { /* ignore network errors while polling */ }
}

function when(date) {
    if (!date) return '';
    if (date === today.value) return 'today';
    const days = Math.round((new Date(today.value) - new Date(date)) / 86400000);
    return days === 1 ? '1 day overdue' : `${days} days overdue`;
}

const priorityClass = {
    urgent: 'bg-red-100 text-red-700',
    high:   'bg-orange-100 text-orange-700',
    medium: 'bg-yellow-100 text-yellow-700',
    low:    'bg-gray-100 text-gray-600',
};

onMounted(() => {
    poll();
    timer = setInterval(poll, POLL_MS);
});
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-4 opacity-0"
    >
        <div v-if="visible"
            class="fixed bottom-6 left-4 right-4 sm:left-6 sm:right-auto z-50 sm:w-96 bg-white rounded-xl shadow-2xl border border-amber-200 ring-2 ring-amber-400 ring-offset-1 overflow-hidden">
            <div class="flex items-start gap-3 p-4 bg-amber-50 border-b border-amber-100">
                <BellAlertIcon class="h-6 w-6 flex-shrink-0 text-amber-600 animate-pulse" />
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm text-gray-900">
                        {{ count }} case{{ count === 1 ? '' : 's' }} need{{ count === 1 ? 's' : '' }} follow-up
                    </p>
                    <p class="text-xs text-gray-500">High / Urgent: follow up in 48 h, uptake in 96 h · Low: 7 days</p>
                </div>
                <button @click="remindLater" class="text-gray-400 hover:text-gray-600" title="Remind me in 30 minutes">
                    <XMarkIcon class="h-4 w-4" />
                </button>
            </div>

            <ul class="max-h-72 overflow-y-auto divide-y divide-gray-100">
                <li v-for="t in tickets" :key="t.id">
                    <Link :href="`/tickets/${t.id}`" @click="remindLater" class="block px-4 py-3 hover:bg-gray-50">
                        <div class="flex items-center gap-2">
                            <span :class="['badge capitalize', priorityClass[t.priority] ?? priorityClass.low]">{{ t.priority }}</span>
                            <span class="text-sm font-medium text-gray-900 truncate">#{{ t.id }} {{ t.subject }}</span>
                        </div>
                        <p v-if="t.follow_up_due" class="mt-1 text-xs" :class="t.follow_up_due < today ? 'text-red-600 font-semibold' : 'text-amber-700'">
                            Follow-up due {{ when(t.follow_up_due) }}
                        </p>
                        <p v-if="t.uptake_due" class="mt-0.5 text-xs" :class="t.uptake_due < today ? 'text-red-600 font-semibold' : 'text-amber-700'">
                            Referral uptake {{ t.referred_to ? `(${t.referred_to}) ` : '' }}due {{ when(t.uptake_due) }}
                        </p>
                        <p v-if="t.contact_number" class="mt-0.5 text-xs text-gray-400">📞 {{ t.contact_number }}</p>
                    </Link>
                </li>
            </ul>

            <div class="flex items-center justify-between gap-2 px-4 py-2 bg-gray-50 border-t border-gray-100">
                <Link href="/tickets?follow_up=follow_up_due" @click="remindLater" class="text-xs font-semibold text-brand-600 hover:underline">
                    View all on Tickets →
                </Link>
                <button @click="remindLater" class="text-xs text-gray-500 hover:text-gray-700">Remind me in 30 min</button>
            </div>
        </div>
    </Transition>
</template>
