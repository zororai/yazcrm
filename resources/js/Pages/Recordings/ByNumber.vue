<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    MagnifyingGlassIcon, ChevronDownIcon, PhoneIcon,
    MicrophoneIcon, TicketIcon, ArrowLeftIcon,
} from '@heroicons/vue/24/outline';
import { debounce } from 'lodash-es';

const props = defineProps({ rows: Array, filters: Object, pagination: Object });

const search = ref(props.filters.search ?? '');

const runFilter = debounce(() => {
    router.get('/recordings/by-number', { search: search.value || undefined }, { preserveState: true, replace: true });
}, 350);

function goToPage(page) {
    router.get('/recordings/by-number', { search: search.value || undefined, page }, { preserveState: true, preserveScroll: true });
}

const totals = computed(() => ({
    numbers: props.pagination.total,
    recordings: props.rows.reduce((sum, r) => sum + r.recordings_count, 0),
    tickets: props.rows.reduce((sum, r) => sum + r.tickets_count, 0),
}));

// ── Expand-to-load detail per number ──────────────────────────────────────────
const expanded = ref(null); // only one open at a time
const details  = ref({}); // number -> { recordings: [], tickets: [], loading: bool }

async function toggleExpand(number) {
    expanded.value = expanded.value === number ? null : number;
    if (expanded.value && !details.value[number]) {
        details.value = { ...details.value, [number]: { loading: true, recordings: [], tickets: [] } };
        const res = await fetch(`/recordings/by-number/details?number=${encodeURIComponent(number)}`);
        const data = await res.json();
        details.value = { ...details.value, [number]: { loading: false, ...data } };
    }
}

function fmtDuration(s) {
    if (!s) return '—';
    return `${Math.floor(s / 60)}m ${s % 60}s`;
}

const ticketStatusColor = {
    open: 'bg-blue-100 text-blue-800',
    in_progress: 'bg-amber-100 text-amber-800',
    resolved: 'bg-green-100 text-green-800',
    closed: 'bg-gray-100 text-gray-600',
};

// Deterministic soft color per number, purely cosmetic
const avatarPalettes = [
    'from-fuchsia-400 to-purple-500', 'from-sky-400 to-blue-500', 'from-emerald-400 to-teal-500',
    'from-amber-400 to-orange-500', 'from-rose-400 to-pink-500', 'from-indigo-400 to-violet-500',
];
function paletteFor(number) {
    let hash = 0;
    for (const c of number) hash = (hash * 31 + c.charCodeAt(0)) >>> 0;
    return avatarPalettes[hash % avatarPalettes.length];
}
</script>

<template>
    <AppLayout>
        <template #title>Recordings &amp; Tickets by Number</template>
        <template #header-actions>
            <Link href="/recordings" class="btn-secondary btn-sm"><ArrowLeftIcon class="h-4 w-4" /> Back to Recordings</Link>
        </template>

        <!-- Summary strip -->
        <div class="grid grid-cols-3 gap-4 mb-5">
            <div class="rounded-2xl p-5 bg-gradient-to-br from-brand-600 to-indigo-600 text-white shadow-sm">
                <p class="text-2xl font-bold">{{ totals.numbers }}</p>
                <p class="text-xs text-white/80 mt-0.5">Numbers on this page</p>
            </div>
            <div class="rounded-2xl p-5 bg-white border border-gray-100 shadow-sm">
                <p class="text-2xl font-bold text-gray-900 flex items-center gap-2"><MicrophoneIcon class="h-5 w-5 text-brand-500" /> {{ totals.recordings }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Recordings</p>
            </div>
            <div class="rounded-2xl p-5 bg-white border border-gray-100 shadow-sm">
                <p class="text-2xl font-bold text-gray-900 flex items-center gap-2"><TicketIcon class="h-5 w-5 text-purple-500" /> {{ totals.tickets }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Tickets</p>
            </div>
        </div>

        <!-- Search -->
        <div class="relative max-w-sm mb-4">
            <MagnifyingGlassIcon class="h-4 w-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input v-model="search" @input="runFilter" class="input pl-9 bg-white shadow-sm" placeholder="Search phone number…" />
        </div>

        <!-- Grouped list -->
        <div class="space-y-3">
            <div v-for="row in rows" :key="row.number"
                class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden transition-shadow hover:shadow-md">
                <button type="button" @click="toggleExpand(row.number)"
                    class="w-full flex items-center gap-4 px-5 py-4 text-left">
                    <div :class="['h-11 w-11 rounded-full bg-gradient-to-br flex items-center justify-center text-white shadow-sm flex-shrink-0', paletteFor(row.number)]">
                        <PhoneIcon class="h-5 w-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-900 font-mono">{{ row.number }}</p>
                        <p class="text-xs text-gray-400">{{ row.recordings_count + row.tickets_count }} total interactions</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 text-brand-700 pl-2 pr-3 py-1 text-xs font-semibold">
                            <MicrophoneIcon class="h-3.5 w-3.5" /> {{ row.recordings_count }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-purple-50 text-purple-700 pl-2 pr-3 py-1 text-xs font-semibold">
                            <TicketIcon class="h-3.5 w-3.5" /> {{ row.tickets_count }}
                        </span>
                    </div>
                    <ChevronDownIcon :class="['h-5 w-5 text-gray-300 transition-transform flex-shrink-0', expanded === row.number && 'rotate-180']" />
                </button>

                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 -translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                >
                    <div v-if="expanded === row.number" class="border-t border-gray-100 bg-gray-50/60 px-5 py-4">
                        <div v-if="details[row.number]?.loading" class="text-xs text-gray-400 py-4 text-center">Loading…</div>
                        <div v-else class="grid grid-cols-2 gap-5">
                            <div>
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                                    <MicrophoneIcon class="h-3.5 w-3.5" /> Recordings
                                </p>
                                <ul class="space-y-1.5">
                                    <li v-for="r in details[row.number]?.recordings ?? []" :key="r.id"
                                        class="flex items-center justify-between gap-3 text-xs bg-white rounded-xl border border-gray-100 px-3 py-2.5 shadow-sm">
                                        <span class="text-gray-600">{{ new Date(r.created_at).toLocaleString() }} · {{ fmtDuration(r.duration) }}</span>
                                        <audio controls preload="none" class="h-7 max-w-[150px] flex-shrink-0" :src="`/api/recordings/${r.id}/download`" />
                                    </li>
                                    <li v-if="!details[row.number]?.recordings?.length" class="text-xs text-gray-400 py-2">No recordings.</li>
                                </ul>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                                    <TicketIcon class="h-3.5 w-3.5" /> Tickets
                                </p>
                                <ul class="space-y-1.5">
                                    <li v-for="t in details[row.number]?.tickets ?? []" :key="t.id"
                                        class="flex items-center justify-between gap-2 text-xs bg-white rounded-xl border border-gray-100 px-3 py-2.5 shadow-sm">
                                        <Link :href="`/tickets/${t.id}`" class="text-brand-600 hover:underline truncate">#{{ t.id }} — {{ t.subject }}</Link>
                                        <span :class="['badge shrink-0', ticketStatusColor[t.status] ?? 'bg-gray-100 text-gray-600']">{{ t.status }}</span>
                                    </li>
                                    <li v-if="!details[row.number]?.tickets?.length" class="text-xs text-gray-400 py-2">No tickets.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>

            <div v-if="!rows.length" class="rounded-2xl bg-white border border-gray-100 py-16 text-center text-gray-400">
                No matching numbers found.
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="flex items-center justify-between mt-5">
            <p class="text-xs text-gray-500">
                Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} numbers)
            </p>
            <div class="flex gap-1">
                <button v-for="p in pagination.last_page" :key="p" @click="goToPage(p)"
                    :class="['h-8 w-8 rounded-lg text-xs font-medium transition-colors',
                        p === pagination.current_page ? 'bg-brand-600 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-100']">
                    {{ p }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
