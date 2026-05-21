<script setup>
import { computed } from 'vue';
import { PhoneArrowDownLeftIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    calls: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['dismiss']);

const visible = computed(() => props.calls.length > 0);
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
        <div
            v-if="visible"
            class="fixed bottom-6 right-6 z-50 flex flex-col gap-2 max-w-sm w-full"
        >
            <div
                v-for="(call, i) in calls"
                :key="call.id ?? call.caller ?? i"
                class="flex items-start gap-3 bg-white rounded-xl shadow-2xl border border-green-200 p-4 ring-2 ring-green-400 ring-offset-1"
            >
                <!-- Pulsing icon -->
                <div class="relative flex-shrink-0 mt-0.5">
                    <span class="absolute inset-0 rounded-full bg-green-400 animate-ping opacity-60" />
                    <span class="relative flex h-8 w-8 items-center justify-center rounded-full bg-green-500">
                        <PhoneArrowDownLeftIcon class="h-4 w-4 text-white" />
                    </span>
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-green-700 uppercase tracking-wide mb-0.5">Incoming Call</p>
                    <p class="text-sm font-bold text-gray-900 truncate">{{ call.caller || call.src || 'Unknown' }}</p>
                    <p class="text-xs text-gray-500">
                        → Ext <span class="font-medium">{{ call.callee || call.dst || call.extension_number }}</span>
                        <span v-if="call.client?.name" class="ml-1 text-brand-600">· {{ call.client.name }}</span>
                    </p>
                </div>

                <!-- Dismiss -->
                <button
                    @click="emit('dismiss', i)"
                    class="flex-shrink-0 p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                    title="Dismiss"
                >
                    <XMarkIcon class="h-4 w-4" />
                </button>
            </div>
        </div>
    </Transition>
</template>
