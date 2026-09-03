<script setup>
import { TicketIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ calls: Array }); // [{ call_id, caller, callee, duration, ... }]
const emit = defineEmits(['open']);

function fmt(s) {
    if (!s) return '0:00';
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}
</script>

<template>
    <div class="fixed right-4 top-1/2 -translate-y-1/2 z-40 flex flex-col gap-2 items-end">
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-x-4"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0 translate-x-4"
        >
            <div v-for="call in calls" :key="call.call_id"
                class="group relative flex items-center gap-2 bg-white rounded-full shadow-lg border border-gray-100 pl-1.5 pr-3 py-1.5 transition-all cursor-pointer"
                @click="emit('open', call)">
                <span class="flex items-center justify-center h-9 w-9 rounded-full bg-brand-600 text-white flex-shrink-0 relative">
                    <TicketIcon class="h-4 w-4" />
                    <span class="absolute -top-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-red-500 animate-pulse ring-2 ring-white"></span>
                </span>
                <div class="text-left leading-tight">
                    <p class="text-xs font-semibold text-gray-800">{{ call.caller }}</p>
                    <p class="text-[10px] text-gray-400">{{ fmt(call.duration) }} · Log ticket</p>
                </div>
            </div>
        </TransitionGroup>
    </div>
</template>
