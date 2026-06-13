<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import JsSIP from 'jssip';
import {
    PhoneIcon, PhoneXMarkIcon, PhoneArrowDownLeftIcon,
    BackspaceIcon, MicrophoneIcon, SpeakerWaveIcon,
    ClockIcon, UserIcon,
} from '@heroicons/vue/24/outline';

// ── SIP state ──────────────────────────────────────────────────────────────────
const sipConfig      = ref(null);
const status         = ref('loading');   // loading | unconfigured | registering | ready | on_call | incoming | error
const statusText     = ref('');
const errorDetail    = ref('');
const dialNumber     = ref('');
const callDuration   = ref(0);
const callTimer      = ref(null);
const muted          = ref(false);
const recentCalls    = ref([]);

let ua           = null;
let session      = null;
let remoteAudio  = null;

// ── Status helpers ─────────────────────────────────────────────────────────────
const statusDot = computed(() => ({
    loading:      'bg-gray-400 animate-pulse',
    unconfigured: 'bg-amber-400',
    registering:  'bg-blue-400 animate-pulse',
    ready:        'bg-green-400',
    on_call:      'bg-brand-400',
    incoming:     'bg-blue-400 animate-pulse',
    error:        'bg-red-400',
}[status.value] ?? 'bg-gray-400'));

const callDurationFormatted = computed(() => {
    const m = Math.floor(callDuration.value / 60);
    const s = callDuration.value % 60;
    return `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
});

const callerLabel = computed(() => {
    if (!session) return dialNumber.value;
    return session.remote_identity?.uri?.user ?? dialNumber.value;
});

// ── Dial pad keys ──────────────────────────────────────────────────────────────
const keys = [
    [{ k:'1', sub:'' },  { k:'2', sub:'ABC' }, { k:'3', sub:'DEF' }],
    [{ k:'4', sub:'GHI' },{ k:'5', sub:'JKL' },{ k:'6', sub:'MNO' }],
    [{ k:'7', sub:'PQRS' },{ k:'8', sub:'TUV' },{ k:'9', sub:'WXYZ' }],
    [{ k:'*', sub:'' },  { k:'0', sub:'+' },   { k:'#', sub:'' }],
];

// ── SIP setup ──────────────────────────────────────────────────────────────────
async function loadSipConfig() {
    status.value = 'loading';
    try {
        const res  = await fetch('/dialer/sip-config', { headers: { Accept: 'application/json' }, credentials: 'include' });
        const data = await res.json();
        if (!data.configured) {
            status.value     = 'unconfigured';
            statusText.value = 'SIP not configured — go to Extensions and click Set SIP on your extension.';
            return;
        }
        sipConfig.value = data;
        registerSip(data);
    } catch {
        status.value     = 'error';
        statusText.value = 'Could not load SIP config.';
    }
}

function registerSip(cfg) {
    status.value     = 'registering';
    statusText.value = `Registering extension ${cfg.extension_number}…`;

    const socket = new JsSIP.WebSocketInterface(cfg.ws_url);

    ua = new JsSIP.UA({
        sockets:      [socket],
        uri:          `sip:${cfg.extension_number}@${cfg.sip_domain}`,
        password:     cfg.sip_password,
        display_name: cfg.display_name,
        register:     true,
        log:          { builtinEnabled: false },
    });

    ua.on('registered', () => {
        status.value     = 'ready';
        statusText.value = `Extension ${cfg.extension_number} — Ready`;
    });

    ua.on('unregistered', () => {
        status.value      = 'error';
        statusText.value  = 'Unregistered. Check credentials.';
        errorDetail.value = `Tried: ${cfg.ws_url}  |  SIP: ${cfg.extension_number}@${cfg.sip_domain}`;
    });

    ua.on('registrationFailed', (e) => {
        status.value      = 'error';
        statusText.value  = `Registration failed: ${e.cause}`;
        errorDetail.value = e.cause === 'Connection Error'
            ? `Cannot reach ${cfg.ws_url} — open that address in a new tab and accept the certificate, then refresh this page.`
            : `SIP: ${cfg.extension_number}@${cfg.sip_domain}  |  WS: ${cfg.ws_url}`;
    });

    ua.on('newRTCSession', ({ session: s, originator }) => {
        session = s;

        if (originator === 'remote') {
            status.value     = 'incoming';
            statusText.value = `Incoming call from ${s.remote_identity?.uri?.user ?? 'unknown'}`;
            dialNumber.value = s.remote_identity?.uri?.user ?? '';
        }

        s.on('accepted',  () => { status.value = 'on_call'; startTimer(); attachAudio(s); });
        s.on('confirmed', () => { status.value = 'on_call'; startTimer(); attachAudio(s); });
        s.on('ended',     () => { endCall(s.remote_identity?.uri?.user); });
        s.on('failed',    () => { endCall(); });
    });

    ua.start();
}

function attachAudio(s) {
    if (!remoteAudio) {
        remoteAudio = document.createElement('audio');
        remoteAudio.autoplay = true;
        document.body.appendChild(remoteAudio);
    }
    s.connection?.addEventListener('track', (e) => {
        if (e.streams?.[0]) remoteAudio.srcObject = e.streams[0];
    });
}

// ── Call actions ───────────────────────────────────────────────────────────────
function dial() {
    if (!dialNumber.value.trim() || status.value !== 'ready' || !ua) return;
    const target = `sip:${dialNumber.value.trim()}@${sipConfig.value.sip_domain}`;
    session = ua.call(target, { mediaConstraints: { audio: true, video: false } });
    statusText.value = `Calling ${dialNumber.value}…`;
}

function answer() {
    session?.answer({ mediaConstraints: { audio: true, video: false } });
}

function hangup() {
    try { session?.terminate(); } catch {}
    endCall();
}

function endCall(number = null) {
    stopTimer();
    if (number || dialNumber.value) {
        recentCalls.value.unshift({
            number:   number ?? dialNumber.value,
            duration: callDuration.value,
            time:     new Date().toLocaleTimeString(),
        });
        if (recentCalls.value.length > 10) recentCalls.value.pop();
    }
    status.value     = 'ready';
    statusText.value = sipConfig.value ? `Extension ${sipConfig.value.extension_number} — Ready` : 'Ready';
    session          = null;
    muted.value      = false;
    if (remoteAudio) remoteAudio.srcObject = null;
}

function toggleMute() {
    if (!session) return;
    muted.value ? session.unmute({ audio: true }) : session.mute({ audio: true });
    muted.value = !muted.value;
}

// ── Dial pad ───────────────────────────────────────────────────────────────────
function press(k) {
    dialNumber.value += k;
    if (session && status.value === 'on_call') {
        try { session.sendDTMF(k); } catch {}
    }
}

function backspace() {
    dialNumber.value = dialNumber.value.slice(0, -1);
}

function redial(number) {
    dialNumber.value = number;
}

// ── Timer ──────────────────────────────────────────────────────────────────────
function startTimer() {
    callDuration.value = 0;
    callTimer.value    = setInterval(() => callDuration.value++, 1000);
}

function stopTimer() {
    if (callTimer.value) { clearInterval(callTimer.value); callTimer.value = null; }
    callDuration.value = 0;
}

function fmtDuration(s) {
    return `${Math.floor(s/60)}m ${s%60}s`;
}

onMounted(loadSipConfig);
onUnmounted(() => {
    stopTimer();
    try { ua?.stop(); } catch {}
    remoteAudio?.remove();
});
</script>

<template>
    <AppLayout>
        <template #title>Dialer</template>

        <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- ── Left: Softphone ── -->
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-200 overflow-hidden flex flex-col">

                <!-- Status bar -->
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full flex-shrink-0" :class="statusDot" />
                    <span class="text-xs text-gray-600 truncate">{{ statusText || 'Loading…' }}</span>
                </div>

                <!-- Error detail + fix hint -->
                <div v-if="status === 'error' && errorDetail"
                    class="mx-4 mt-3 rounded-xl bg-red-50 border border-red-200 px-4 py-3 space-y-2">
                    <p class="text-xs text-red-700">{{ errorDetail }}</p>
                    <a v-if="sipConfig"
                        :href="`https://${sipConfig.sip_domain}:8088`"
                        target="_blank"
                        class="inline-flex items-center gap-1 text-xs font-medium text-red-700 underline">
                        Click here to trust the PBX certificate → then refresh this page
                    </a>
                </div>

                <!-- Unconfigured hint -->
                <div v-if="status === 'unconfigured'"
                    class="mx-4 mt-3 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3">
                    <p class="text-xs text-amber-800">{{ statusText }}</p>
                    <a href="/extensions" class="mt-1 inline-block text-xs font-medium text-amber-700 underline">
                        Go to Extensions page to configure
                    </a>
                </div>

                <!-- Incoming call banner -->
                <div v-if="status === 'incoming'"
                    class="mx-4 mt-4 rounded-xl bg-blue-50 border border-blue-200 px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <PhoneArrowDownLeftIcon class="h-5 w-5 text-blue-500 animate-bounce" />
                        <div>
                            <p class="text-sm font-semibold text-blue-900">Incoming Call</p>
                            <p class="text-xs text-blue-600">{{ callerLabel }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button @click="answer"
                            class="px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded-lg text-xs font-medium flex items-center gap-1">
                            <PhoneIcon class="h-3.5 w-3.5" /> Answer
                        </button>
                        <button @click="hangup"
                            class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-medium flex items-center gap-1">
                            <PhoneXMarkIcon class="h-3.5 w-3.5" /> Decline
                        </button>
                    </div>
                </div>

                <!-- Active call bar -->
                <div v-if="status === 'on_call'"
                    class="mx-4 mt-4 rounded-xl bg-brand-50 border border-brand-200 px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <PhoneIcon class="h-5 w-5 text-brand-600" />
                        <div>
                            <p class="text-sm font-semibold text-brand-900">{{ callerLabel }}</p>
                            <p class="text-xs font-mono text-brand-600">{{ callDurationFormatted }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button @click="toggleMute"
                            :class="muted ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium flex items-center gap-1 transition-colors">
                            <MicrophoneIcon class="h-3.5 w-3.5" />
                            {{ muted ? 'Unmute' : 'Mute' }}
                        </button>
                        <button @click="hangup"
                            class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-medium flex items-center gap-1">
                            <PhoneXMarkIcon class="h-3.5 w-3.5" /> Hang up
                        </button>
                    </div>
                </div>

                <!-- Number input -->
                <div class="px-5 pt-5 pb-3">
                    <div class="flex items-center gap-2 bg-gray-50 rounded-xl px-4 py-3 ring-1 ring-gray-200 focus-within:ring-brand-400 focus-within:ring-2 transition-all">
                        <input
                            v-model="dialNumber"
                            class="flex-1 bg-transparent text-2xl font-mono tracking-widest text-gray-900 outline-none min-w-0"
                            placeholder="Enter number"
                            :disabled="status === 'on_call' || status === 'incoming'"
                            @keydown.enter="dial"
                        />
                        <button @click="backspace" class="text-gray-400 hover:text-gray-600 flex-shrink-0">
                            <BackspaceIcon class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                <!-- Dial pad -->
                <div class="px-5 pb-3 grid grid-cols-3 gap-2.5">
                    <template v-for="row in keys" :key="row[0].k">
                        <button
                            v-for="key in row" :key="key.k"
                            @click="press(key.k)"
                            class="flex flex-col items-center justify-center h-14 rounded-xl bg-gray-50 hover:bg-gray-100 active:bg-gray-200 transition-colors select-none"
                        >
                            <span class="text-xl font-semibold text-gray-800">{{ key.k }}</span>
                            <span class="text-[9px] text-gray-400 tracking-widest leading-none">{{ key.sub }}</span>
                        </button>
                    </template>
                </div>

                <!-- Call button -->
                <div class="px-5 pb-5">
                    <button
                        @click="dial"
                        :disabled="!dialNumber || status !== 'ready'"
                        class="w-full flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl py-3.5 font-semibold text-base transition-colors"
                    >
                        <PhoneIcon class="h-5 w-5" /> Call
                    </button>
                </div>
            </div>

            <!-- ── Right: Recent calls ── -->
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-200 flex flex-col overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900 flex items-center gap-2">
                        <ClockIcon class="h-4 w-4 text-gray-400" />
                        Recent Calls
                        <span class="text-xs font-normal text-gray-400">(this session)</span>
                    </h3>
                </div>

                <div class="flex-1 overflow-y-auto divide-y divide-gray-50">
                    <div v-if="!recentCalls.length" class="flex flex-col items-center justify-center py-16 text-gray-300 gap-2">
                        <SpeakerWaveIcon class="h-10 w-10" />
                        <p class="text-sm">No calls yet</p>
                    </div>

                    <div v-for="(c, i) in recentCalls" :key="i"
                        class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 group">
                        <div class="flex items-center gap-3">
                            <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                                <UserIcon class="h-4 w-4 text-gray-400" />
                            </div>
                            <div>
                                <p class="text-sm font-mono font-medium text-gray-900">{{ c.number }}</p>
                                <p class="text-xs text-gray-400">{{ c.time }} · {{ fmtDuration(c.duration) }}</p>
                            </div>
                        </div>
                        <button @click="redial(c.number)"
                            class="opacity-0 group-hover:opacity-100 transition-opacity p-1.5 rounded-lg hover:bg-green-50 text-green-600">
                            <PhoneIcon class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
