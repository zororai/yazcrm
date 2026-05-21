<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { MagnifyingGlassIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';
import { debounce } from 'lodash-es';

const props = defineProps({
    calls:    Object,
    filters:  Object,
    is_agent: Boolean,
});

const search    = ref(props.filters.search ?? '');
const direction = ref(props.filters.direction ?? '');
const status    = ref(props.filters.status ?? '');
const dateFrom  = ref(props.filters.date_from ?? '');
const dateTo    = ref(props.filters.date_to ?? '');

function apply() {
    router.get('/calls', {
        search:     search.value || undefined,
        direction:  direction.value || undefined,
        status:     status.value || undefined,
        date_from:  dateFrom.value || undefined,
        date_to:    dateTo.value || undefined,
    }, { preserveState: true, replace: true });
}

const debouncedApply = debounce(apply, 350);
watch(search, debouncedApply);
watch([direction, status, dateFrom, dateTo], apply);

function statusBadge(s) {
    return {
        answered: 'bg-green-100 text-green-800',
        missed:   'bg-red-100 text-red-800',
        voicemail:'bg-yellow-100 text-yellow-800',
    }[s] ?? 'bg-gray-100 text-gray-700';
}

function dirBadge(d) {
    return d === 'inbound' ? 'bg-blue-100 text-blue-800' : 'bg-indigo-100 text-indigo-800';
}

function fmt(s) {
    if (!s) return '—';
    const m = Math.floor(s / 60), sec = s % 60;
    return `${m}m ${sec}s`;
}
</script>

<template>
    <AppLayout>
        <template #title>Calls</template>

        <!-- Agent scope notice -->
        <div v-if="is_agent" class="mb-4 flex items-center gap-2 p-3 rounded-lg bg-blue-50 border border-blue-200 text-sm text-blue-800">
            <span class="font-medium">Showing your calls only</span> — based on your assigned extension.
        </div>

        <!-- Filters -->
        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48">
                <label class="label">Search</label>
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                    <input v-model="search" class="input pl-9" placeholder="Number or client…" />
                </div>
            </div>
            <div>
                <label class="label">Direction</label>
                <select v-model="direction" class="input w-36">
                    <option value="">All</option>
                    <option value="inbound">Inbound</option>
                    <option value="outbound">Outbound</option>
                </select>
            </div>
            <div>
                <label class="label">Status</label>
                <select v-model="status" class="input w-36">
                    <option value="">All</option>
                    <option value="answered">Answered</option>
                    <option value="missed">Missed</option>
                    <option value="voicemail">Voicemail</option>
                </select>
            </div>
            <div>
                <label class="label">From</label>
                <input v-model="dateFrom" type="date" class="input w-36" />
            </div>
            <div>
                <label class="label">To</label>
                <input v-model="dateTo" type="date" class="input w-36" />
            </div>
        </div>

        <!-- Table -->
        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Date / Time</th>
                        <th class="table-th">From</th>
                        <th class="table-th">To</th>
                        <th class="table-th">Direction</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Duration</th>
                        <th class="table-th">Client</th>
                        <th class="table-th">Agent</th>
                        <th class="table-th w-16" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!calls.data.length">
                        <td colspan="9" class="py-12 text-center text-sm text-gray-400">No calls found.</td>
                    </tr>
                    <tr v-for="call in calls.data" :key="call.id" class="hover:bg-gray-50 transition-colors">
                        <td class="table-td text-xs text-gray-500 whitespace-nowrap">{{ new Date(call.started_at).toLocaleString() }}</td>
                        <td class="table-td font-mono text-xs">{{ call.caller }}</td>
                        <td class="table-td font-mono text-xs">{{ call.callee }}</td>
                        <td class="table-td">
                            <span :class="['badge', dirBadge(call.direction)]">{{ call.direction }}</span>
                        </td>
                        <td class="table-td">
                            <span :class="['badge', statusBadge(call.status)]">{{ call.status }}</span>
                        </td>
                        <td class="table-td">{{ fmt(call.duration) }}</td>
                        <td class="table-td">
                            <Link v-if="call.client" :href="`/clients/${call.client.id}`" class="text-brand-600 hover:underline">
                                {{ call.client.name }}
                            </Link>
                            <span v-else class="text-gray-400 text-xs">—</span>
                        </td>
                        <td class="table-td">{{ call.agent?.name ?? '—' }}</td>
                        <td class="table-td">
                            <Link :href="`/calls/${call.id}`" class="btn-secondary btn-sm">View</Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="calls.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">
                    Showing {{ calls.from }}–{{ calls.to }} of {{ calls.total }}
                </p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in calls.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                        preserve-state
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
