<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import JsSIP from 'jssip';
import {
    PhoneIcon, PhoneXMarkIcon, PhoneArrowDownLeftIcon,
    BackspaceIcon, ChevronDownIcon,
} from '@heroicons/vue/24/outline';

// ── State ──────────────────────────────────────────────────────────────────────
// Opened externally (a nav button) — no floating launcher of its own.
const props = defineProps({ open: { type: Boolean, default: false } });
const emit  = defineEmits(['update:open']);
const open  = computed({
    get: () => props.open,
    set: (v) => emit('update:open', v),
});
const dialNumber   = ref('');
const sipConfig    = ref(null);
const status       = ref('loading'); // loading | unconfigured | registering | ready | on_call | incoming | error
const statusText   = ref('');
const callDuration = ref(0);
const callTimer    = ref(null);
const muted        = ref(false);

let ua        = null;   // JsSIP UserAgent
let session   = null;   // active JsSIP RTCSession
let remoteAudio = null; // <audio> element for remote audio

// ── Computed ───────────────────────────────────────────────────────────────────
const statusColor = computed(() => ({
    loading:      'text-gray-400',
    unconfigured: 'text-amber-500',
    registering:  'text-blue-400',
    ready:        'text-green-500',
    on_call:      'text-brand-500',
    incoming:     'text-blue-500',
    error:        'text-red-500',
}[status.value] ?? 'text-gray-400'));

const callDurationFormatted = computed(() => {
    const m = Math.floor(callDuration.value / 60);
    const s = callDuration.value % 60;
    return `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
});

// ── SIP setup ─────────────────────────────────────────────────────────────────
async function loadSipConfig() {
    status.value = 'loading';
    try {
        const res  = await fetch('/dialer/sip-config', { headers: { Accept: 'application/json' }, credentials: 'include' });
        const data = await res.json();
        if (!data.configured) {
            status.value  = 'unconfigured';
            statusText.value = 'SIP credentials not set — ask your admin.';
            return;
        }
        sipConfig.value = data;
        registerSip(data);
    } catch (e) {
        status.value  = 'error';
        statusText.value = 'Could not load SIP config.';
    }
}

function registerSip(cfg) {
    status.value    = 'registering';
    statusText.value = `Registering ext ${cfg.extension_number}…`;

    const socket = new JsSIP.WebSocketInterface(cfg.ws_url);

    ua = new JsSIP.UA({
        sockets:        [socket],
        uri:            `sip:${cfg.extension_number}@${cfg.sip_domain}`,
        password:       cfg.sip_password,
        display_name:   cfg.display_name,
        register:       true,
        log:            { builtinEnabled: false },
    });

    ua.on('registered', () => {
        status.value     = 'ready';
        statusText.value = `Ext ${cfg.extension_number} ready`;
    });

    ua.on('unregistered', () => {
        status.value     = 'error';
        statusText.value = 'Unregistered — check credentials.';
    });

    ua.on('registrationFailed', (e) => {
        status.value     = 'error';
        statusText.value = `Registration failed: ${e.cause}`;
    });

    ua.on('newRTCSession', ({ session: s, originator }) => {
        session = s;

        if (originator === 'remote') {
            status.value     = 'incoming';
            statusText.value = `Incoming: ${s.remote_identity?.uri?.user ?? 'unknown'}`;
            dialNumber.value = s.remote_identity?.uri?.user ?? '';
            open.value       = true;
        }

        s.on('accepted', () => {
            status.value = 'on_call';
            statusText.value = `On call — ${s.remote_identity?.uri?.user ?? ''}`;
            startTimer();
            attachRemoteAudio(s);
        });

        s.on('confirmed', () => {
            status.value = 'on_call';
            startTimer();
            attachRemoteAudio(s);
        });

        s.on('ended', endCall);
        s.on('failed', endCall);
    });

    ua.start();
}

function attachRemoteAudio(s) {
    if (!remoteAudio) {
        remoteAudio = document.createElement('audio');
        remoteAudio.autoplay = true;
        document.body.appendChild(remoteAudio);
    }
    s.connection?.addEventListener('track', (e) => {
        if (e.streams?.[0]) remoteAudio.srcObject = e.streams[0];
    });
}

// ── Call controls ──────────────────────────────────────────────────────────────
function dial() {
    if (!dialNumber.value.trim() || status.value !== 'ready' || !ua) return;

    const target = `sip:${dialNumber.value.trim()}@${sipConfig.value.sip_domain}`;

    const mediaConstraints = { audio: true, video: false };

    session = ua.call(target, {
        mediaConstraints,
        pcConfig: { iceServers: [] },
    });

    status.value     = 'on_call';
    statusText.value = `Calling ${dialNumber.value}…`;
}

function answer() {
    if (!session) return;
    session.answer({ mediaConstraints: { audio: true, video: false } });
}

function hangup() {
    if (!session) return;
    try { session.terminate(); } catch {}
    endCall();
}

function endCall() {
    stopTimer();
    status.value     = 'ready';
    statusText.value = sipConfig.value ? `Ext ${sipConfig.value.extension_number} ready` : 'Ready';
    session          = null;
    muted.value      = false;
    if (remoteAudio) { remoteAudio.srcObject = null; }
}

function toggleMute() {
    if (!session) return;
    muted.value ? session.unmute({ audio: true }) : session.mute({ audio: true });
    muted.value = !muted.value;
}

// ── Dial pad ──────────────────────────────────────────────────────────────────
const keys = [
    ['1','2','3'],
    ['4','5','6'],
    ['7','8','9'],
    ['*','0','#'],
];

function press(k) {
    dialNumber.value += k;
    // Send DTMF if call is active
    if (session && status.value === 'on_call') {
        try { session.sendDTMF(k); } catch {}
    }
}

function backspace() {
    dialNumber.value = dialNumber.value.slice(0, -1);
}

// ── Timer ─────────────────────────────────────────────────────────────────────
function startTimer() {
    callDuration.value = 0;
    callTimer.value    = setInterval(() => callDuration.value++, 1000);
}
function stopTimer() {
    if (callTimer.value) { clearInterval(callTimer.value); callTimer.value = null; }
    callDuration.value = 0;
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────
onMounted(loadSipConfig);

onUnmounted(() => {
    stopTimer();
    try { ua?.stop(); } catch {}
    remoteAudio?.remove();
});
</script>

<template>
    <!-- Toggle button — always visible in bottom-right corner -->
    <div class="fixed bottom-6 right-6 z-40 flex flex-col items-end gap-2">

        <!-- Dialer panel -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 translate-y-2 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-2 scale-95"
        >
            <div v-if="open"
                class="bg-white rounded-2xl shadow-2xl ring-1 ring-black/10 w-72 overflow-hidden mb-2">

                <!-- Header -->
                <div class="bg-brand-600 px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <PhoneIcon class="h-4 w-4 text-white" />
                        <span class="text-white text-sm font-semibold">Dialer</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs" :class="statusColor">{{ statusText }}</span>
                        <button @click="open = false" class="text-white/70 hover:text-white">
                            <ChevronDownIcon class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Unconfigured warning -->
                <div v-if="status === 'unconfigured'" class="px-4 py-6 text-center text-sm text-amber-700 bg-amber-50">
                    {{ statusText }}
                </div>

                <template v-else>
                    <!-- Number display -->
                    <div class="px-4 pt-4 pb-2">
                        <div class="flex items-center gap-1 bg-gray-50 rounded-xl px-3 py-2.5 ring-1 ring-gray-200">
                            <input
                                v-model="dialNumber"
                                class="flex-1 bg-transparent text-xl font-mono tracking-widest text-gray-900 outline-none min-w-0"
                                placeholder="Enter number"
                                :disabled="status === 'on_call'"
                            />
                            <button @click="backspace" class="text-gray-400 hover:text-gray-600 flex-shrink-0">
                                <BackspaceIcon class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- On-call timer -->
                        <div v-if="status === 'on_call'" class="mt-2 text-center text-sm font-mono text-brand-600 font-medium">
                            {{ callDurationFormatted }}
                        </div>
                    </div>

                    <!-- Dial pad -->
                    <div class="px-4 pb-3 grid grid-cols-3 gap-2">
                        <template v-for="row in keys" :key="row">
                            <button
                                v-for="k in row" :key="k"
                                @click="press(k)"
                                class="h-12 rounded-xl bg-gray-50 hover:bg-gray-100 active:bg-gray-200 text-gray-800 font-semibold text-lg transition-colors"
                            >{{ k }}</button>
                        </template>
                    </div>

                    <!-- Action buttons -->
                    <div class="px-4 pb-4 flex gap-2">
                        <!-- Incoming call: answer + reject -->
                        <template v-if="status === 'incoming'">
                            <button @click="answer"
                                class="flex-1 flex items-center justify-center gap-1.5 bg-green-500 hover:bg-green-600 text-white rounded-xl py-3 font-medium text-sm transition-colors">
                                <PhoneArrowDownLeftIcon class="h-5 w-5" /> Answer
                            </button>
                            <button @click="hangup"
                                class="flex-1 flex items-center justify-center gap-1.5 bg-red-500 hover:bg-red-600 text-white rounded-xl py-3 font-medium text-sm transition-colors">
                                <PhoneXMarkIcon class="h-5 w-5" /> Decline
                            </button>
                        </template>

                        <!-- Active call: mute + hangup -->
                        <template v-else-if="status === 'on_call'">
                            <button @click="toggleMute"
                                :class="muted
                                    ? 'bg-amber-500 hover:bg-amber-600 text-white'
                                    : 'bg-gray-200 hover:bg-gray-300 text-gray-700'"
                                class="flex-1 text-sm font-medium rounded-xl py-3 transition-colors"
                            >{{ muted ? 'Unmute' : 'Mute' }}</button>
                            <button @click="hangup"
                                class="flex-1 flex items-center justify-center gap-1.5 bg-red-500 hover:bg-red-600 text-white rounded-xl py-3 font-medium text-sm transition-colors">
                                <PhoneXMarkIcon class="h-5 w-5" /> Hang up
                            </button>
                        </template>

                        <!-- Idle: dial button -->
                        <template v-else>
                            <button @click="dial"
                                :disabled="!dialNumber || status !== 'ready'"
                                class="w-full flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl py-3 font-medium text-sm transition-colors">
                                <PhoneIcon class="h-5 w-5" /> Call
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </Transition>
    </div>
</template>
