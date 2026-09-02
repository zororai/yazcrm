<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { MagnifyingGlassIcon, ChevronDownIcon, ChevronRightIcon, MicrophoneIcon, TicketIcon } from '@heroicons/vue/24/outline';
import { debounce } from 'lodash-es';

const props = defineProps({ rows: Array, filters: Object, pagination: Object });

const search = ref(props.filters.search ?? '');

const runFilter = debounce(() => {
    router.get('/recordings/by-number', { search: search.value || undefined }, { preserveState: true, replace: true });
}, 350);

function goToPage(page) {
    router.get('/recordings/by-number', { search: search.value || undefined, page }, { preserveState: true });
}

// ── Expand-to-load detail per number ──────────────────────────────────────────
const expanded = ref(new Set());
const details  = ref({}); // number -> { recordings: [], tickets: [], loading: bool }

async function toggleExpand(number) {
    if (expanded.value.has(number)) {
        expanded.value.delete(number);
        expanded.value = new Set(expanded.value);
        return;
    }
    expanded.value = new Set(expanded.value).add(number);

    if (!details.value[number]) {
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
</script>

<template>
    <AppLayout>
        <template #title>Recordings &amp; Tickets by Number</template>
        <template #header-actions>
            <Link href="/recordings" class="btn-secondary btn-sm">Back to Recordings</Link>
        </template>

        <div class="card mb-4">
            <div class="relative max-w-sm">
                <MagnifyingGlassIcon class="h-4 w-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input v-model="search" @input="runFilter" class="input pl-9" placeholder="Search phone number…" />
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-2 w-8"></th>
                        <th class="px-4 py-2 text-left">Number</th>
                        <th class="px-4 py-2 text-left">Recordings</th>
                        <th class="px-4 py-2 text-left">Tickets</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template v-for="row in rows" :key="row.number">
                        <tr class="hover:bg-gray-50 cursor-pointer" @click="toggleExpand(row.number)">
                            <td class="px-4 py-2.5 text-gray-400">
                                <component :is="expanded.has(row.number) ? ChevronDownIcon : ChevronRightIcon" class="h-4 w-4" />
                            </td>
                            <td class="px-4 py-2.5 font-medium text-gray-900">{{ row.number }}</td>
                            <td class="px-4 py-2.5">
                                <span class="badge bg-brand-100 text-brand-800 inline-flex items-center gap-1">
                                    <MicrophoneIcon class="h-3.5 w-3.5" /> {{ row.recordings_count }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="badge bg-purple-100 text-purple-800 inline-flex items-center gap-1">
                                    <TicketIcon class="h-3.5 w-3.5" /> {{ row.tickets_count }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="expanded.has(row.number)">
                            <td colspan="4" class="px-4 py-3 bg-gray-50">
                                <div v-if="details[row.number]?.loading" class="text-xs text-gray-400 py-2">Loading…</div>
                                <div v-else class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Recordings</p>
                                        <ul class="space-y-1.5">
                                            <li v-for="r in details[row.number]?.recordings ?? []" :key="r.id"
                                                class="flex items-center justify-between text-xs bg-white rounded-lg border border-gray-100 px-3 py-2">
                                                <span>{{ new Date(r.created_at).toLocaleString() }} · {{ fmtDuration(r.duration) }}</span>
                                                <audio controls preload="none" class="h-6 max-w-[140px]" :src="`/api/recordings/${r.id}/download`" />
                                            </li>
                                            <li v-if="!details[row.number]?.recordings?.length" class="text-xs text-gray-400">No recordings.</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Tickets</p>
                                        <ul class="space-y-1.5">
                                            <li v-for="t in details[row.number]?.tickets ?? []" :key="t.id"
                                                class="flex items-center justify-between text-xs bg-white rounded-lg border border-gray-100 px-3 py-2">
                                                <Link :href="`/tickets/${t.id}`" class="text-brand-600 hover:underline truncate mr-2">#{{ t.id }} — {{ t.subject }}</Link>
                                                <span :class="['badge shrink-0', ticketStatusColor[t.status] ?? 'bg-gray-100 text-gray-600']">{{ t.status }}</span>
                                            </li>
                                            <li v-if="!details[row.number]?.tickets?.length" class="text-xs text-gray-400">No tickets.</li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="!rows.length">
                        <td colspan="4" class="px-4 py-16 text-center text-gray-400">No matching numbers found.</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">
                    Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} numbers)
                </p>
                <div class="flex gap-1">
                    <button v-for="p in pagination.last_page" :key="p" @click="goToPage(p)"
                        :class="['px-3 py-1 rounded text-xs', p === pagination.current_page ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100']">
                        {{ p }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
