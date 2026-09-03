<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CallTicketModal from '@/Components/CallTicketModal.vue';
import { MagnifyingGlassIcon, ArrowDownTrayIcon, MicrophoneIcon, TicketIcon } from '@heroicons/vue/24/outline';
import { debounce } from 'lodash-es';

const props = defineProps({ recordings: Object, filters: Object, agents: { type: Array, default: () => [] } });

const search  = ref(props.filters.search ?? '');
const from    = ref(props.filters.from   ?? '');
const to      = ref(props.filters.to     ?? '');
const agentId = ref(props.filters.agent_id ?? '');
const period  = ref(props.filters.period ?? '');
const groupByAgent = ref(true);

const periods = [
    { key: 'today', label: 'Today' },
    { key: 'week',  label: 'This Week' },
    { key: 'month', label: 'This Month' },
    { key: 'year',  label: 'This Year' },
];

const runFilter = debounce(() => {
    router.get('/recordings', { search: search.value, from: from.value, to: to.value, agent_id: agentId.value, period: period.value }, {
        preserveState: true,
        replace: true,
    });
}, 400);

function setPeriod(key) {
    period.value = period.value === key ? '' : key;
    from.value = '';
    to.value = '';
    runFilter();
}

function onDateChange() {
    period.value = '';
    runFilter();
}

const groupedRecordings = computed(() => {
    const groups = new Map();
    for (const r of props.recordings.data) {
        const name = r.call?.agent?.name ?? 'Unassigned';
        if (! groups.has(name)) groups.set(name, []);
        groups.get(name).push(r);
    }
    return [...groups.entries()].sort(([a], [b]) => a.localeCompare(b));
});

// ── Attach ticket to a recorded call ─────────────────────────────────────────
const ticketModalCall = ref(null);

function openTicketModal(r) {
    if (!r.call) return;
    ticketModalCall.value = {
        call_id:      r.call.id,
        caller:       r.call.caller,
        callee:       r.call.callee,
        duration:     r.duration,
        direction:    r.call.direction,
        client:       r.call.client ?? null,
        recording_id: r.id,
    };
}

function closeTicketModal() {
    ticketModalCall.value = null;
    router.reload({ only: ['recordings'] });
}

function fmtDuration(s) {
    if (!s) return '—';
    return `${Math.floor(s / 60)}m ${s % 60}s`;
}

function statusColor(s) {
    return {
        done:       'bg-green-100 text-green-800',
        processing: 'bg-amber-100 text-amber-800',
        pending:    'bg-gray-100 text-gray-500',
        failed:     'bg-red-100 text-red-800',
    }[s] ?? 'bg-gray-100 text-gray-500';
}
</script>

<template>
    <AppLayout>
        <template #title>Call Recordings</template>
        <template #header-actions>
            <Link href="/recordings/by-number" class="btn-secondary btn-sm">Group by Number</Link>
        </template>

        <div class="card mb-4">
            <div class="flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="label">Search caller/callee/extension</label>
                    <div class="relative">
                        <MagnifyingGlassIcon class="h-4 w-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input v-model="search" @input="runFilter" class="input pl-9" placeholder="Phone number or extension…" />
                    </div>
                </div>
                <div>
                    <label class="label">From</label>
                    <input v-model="from" @change="onDateChange" type="date" class="input" />
                </div>
                <div>
                    <label class="label">To</label>
                    <input v-model="to" @change="onDateChange" type="date" class="input" />
                </div>
                <div v-if="agents.length">
                    <label class="label">Agent</label>
                    <select v-model="agentId" @change="runFilter" class="input">
                        <option value="">All agents</option>
                        <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                </div>
                <label v-if="agents.length" class="flex items-center gap-2 text-sm text-gray-600 pb-2">
                    <input type="checkbox" v-model="groupByAgent" /> Group by agent
                </label>
            </div>

            <div class="flex flex-wrap gap-2 mt-3">
                <button v-for="p in periods" :key="p.key" type="button" @click="setPeriod(p.key)"
                    :class="['px-3 py-1.5 rounded-full text-xs font-medium transition',
                        period === p.key ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
                    {{ p.label }}
                </button>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Call</th>
                        <th class="px-4 py-2 text-left">Ext</th>
                        <th class="px-4 py-2 text-left">Agent</th>
                        <th class="px-4 py-2 text-left">Duration</th>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-left">Transcription</th>
                        <th class="px-4 py-2 text-left">Recording</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template v-if="groupByAgent && agents.length">
                        <template v-for="[agentName, rows] in groupedRecordings" :key="agentName">
                            <tr class="bg-gray-100">
                                <td colspan="8" class="px-4 py-1.5 text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                    {{ agentName }} <span class="font-normal normal-case text-gray-400">({{ rows.length }})</span>
                                </td>
                            </tr>
                            <tr v-for="r in rows" :key="r.id" class="hover:bg-gray-50">
                                <td class="px-4 py-2.5">
                                    <p class="font-medium text-gray-900">{{ r.call?.caller }} → {{ r.call?.callee }}</p>
                                    <p class="text-xs text-gray-400">{{ r.call?.client?.name ?? 'No client linked' }}</p>
                                </td>
                                <td class="px-4 py-2.5 text-gray-500">{{ r.call?.extension_number ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ r.call?.agent?.name ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ fmtDuration(r.duration) }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ new Date(r.created_at).toLocaleString() }}</td>
                                <td class="px-4 py-2.5">
                                    <span :class="['badge', statusColor(r.transcription_status)]">{{ r.transcription_status }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <audio controls preload="none" class="h-8 max-w-[220px]" :src="`/api/recordings/${r.id}/download`" />
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <div class="flex gap-2 justify-end items-center">
                                        <a :href="`/api/recordings/${r.id}/download?download=1`" download class="text-gray-400 hover:text-gray-700" title="Download">
                                            <ArrowDownTrayIcon class="h-4 w-4" />
                                        </a>
                                        <button
                                            v-if="r.call && !r.call.ticket"
                                            @click="openTicketModal(r)"
                                            class="text-gray-400 hover:text-brand-600"
                                            title="Attach ticket"
                                        >
                                            <TicketIcon class="h-4 w-4" />
                                        </button>
                                        <Link v-if="r.call?.ticket" :href="`/tickets/${r.call.ticket.id}`" class="text-green-600 hover:underline text-xs font-medium">
                                            Ticket #{{ r.call.ticket.id }}
                                        </Link>
                                        <Link v-if="r.call" :href="`/calls/${r.call.id}`" class="text-brand-600 hover:underline text-xs font-medium">
                                            View call
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </template>
                    <template v-else>
                        <tr v-for="r in recordings.data" :key="r.id" class="hover:bg-gray-50">
                            <td class="px-4 py-2.5">
                                <p class="font-medium text-gray-900">{{ r.call?.caller }} → {{ r.call?.callee }}</p>
                                <p class="text-xs text-gray-400">{{ r.call?.client?.name ?? 'No client linked' }}</p>
                            </td>
                            <td class="px-4 py-2.5 text-gray-500">{{ r.call?.extension_number ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-600">{{ r.call?.agent?.name ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-600">{{ fmtDuration(r.duration) }}</td>
                            <td class="px-4 py-2.5 text-gray-600">{{ new Date(r.created_at).toLocaleString() }}</td>
                            <td class="px-4 py-2.5">
                                <span :class="['badge', statusColor(r.transcription_status)]">{{ r.transcription_status }}</span>
                            </td>
                            <td class="px-4 py-2.5">
                                <audio controls preload="none" class="h-8 max-w-[220px]" :src="`/api/recordings/${r.id}/download`" />
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <div class="flex gap-2 justify-end items-center">
                                    <a :href="`/api/recordings/${r.id}/download?download=1`" download class="text-gray-400 hover:text-gray-700" title="Download">
                                        <ArrowDownTrayIcon class="h-4 w-4" />
                                    </a>
                                    <button
                                        v-if="r.call && !r.call.ticket"
                                        @click="openTicketModal(r)"
                                        class="text-gray-400 hover:text-brand-600"
                                        title="Attach ticket"
                                    >
                                        <TicketIcon class="h-4 w-4" />
                                    </button>
                                    <Link v-if="r.call?.ticket" :href="`/tickets/${r.call.ticket.id}`" class="text-green-600 hover:underline text-xs font-medium">
                                        Ticket #{{ r.call.ticket.id }}
                                    </Link>
                                    <Link v-if="r.call" :href="`/calls/${r.call.id}`" class="text-brand-600 hover:underline text-xs font-medium">
                                        View call
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="!recordings.data.length">
                        <td colspan="8" class="px-4 py-16 text-center text-gray-400">
                            <MicrophoneIcon class="h-8 w-8 mx-auto mb-2 text-gray-300" />
                            No recordings found.
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="recordings.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">
                    Showing {{ recordings.from }}–{{ recordings.to }} of {{ recordings.total }} recordings
                </p>
                <div class="flex gap-1">
                    <Link v-for="link in recordings.links" :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-xs',
                            link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100',
                            !link.url && 'opacity-40 pointer-events-none']"
                        v-html="link.label" />
                </div>
            </div>
        </div>

        <CallTicketModal
            v-if="ticketModalCall"
            :call="ticketModalCall"
            @close="closeTicketModal"
            @minimize="closeTicketModal"
        />
    </AppLayout>
</template>
