<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon, PhoneIcon, EnvelopeIcon, FlagIcon, TicketIcon, MicrophoneIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ counsellor: Object, tickets: Object, recordings: Object, progressReports: Array, filters: Object });

const statusLabels = {
    pending:         'Pending Review',
    reviewed:        'Reviewed',
    approved:        'Approved',
    needs_revision:  'Needs Revision',
};

const statusColor = {
    pending:        'bg-gray-100 text-gray-600',
    reviewed:       'bg-blue-100 text-blue-800',
    approved:       'bg-green-100 text-green-800',
    needs_revision: 'bg-amber-100 text-amber-800',
};

function monthLabel(month) {
    return new Date(month + 'T00:00:00').toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
}

const period = ref(props.filters.period ?? 'today');

const periods = [
    { key: 'today', label: 'Today' },
    { key: 'week',  label: 'This Week' },
    { key: 'month', label: 'This Month' },
    { key: 'year',  label: 'This Year' },
];

const periodLabel = computed(() => periods.find(p => p.key === period.value)?.label ?? 'Today');

function setPeriod(key) {
    period.value = key;
    router.get(`/counsellor-profiles/${props.counsellor.id}`, { period: key }, { preserveState: true, replace: true });
}

function targetPct() {
    const required = props.counsellor.call_target?.today_required;
    if (!required) return null;
    return Math.min(100, Math.round((props.counsellor.call_target.today_calls / required) * 100));
}

function targetBarColor(pct) {
    if (pct === null) return 'bg-gray-200';
    if (pct >= 100) return 'bg-green-500';
    if (pct >= 60) return 'bg-amber-500';
    return 'bg-red-500';
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
        <template #title>Counsellor Profile</template>
        <template #header-actions>
            <Link href="/counsellor-profiles" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                <ArrowLeftIcon class="h-4 w-4" /> Back to all counsellors
            </Link>
        </template>

        <!-- Profile header -->
        <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6 mb-5">
            <div class="flex flex-wrap items-start gap-5">
                <img :src="counsellor.avatar ? `/storage/${counsellor.avatar}` : '/images/default-avatar.png'"
                    class="h-24 w-24 rounded-full object-cover ring-4 ring-brand-50 flex-shrink-0" />
                <div class="flex-1 min-w-[220px]">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-xl font-bold text-gray-900">{{ counsellor.name }}</h2>
                        <span :class="['badge', counsellor.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500']">
                            {{ counsellor.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p v-if="counsellor.username" class="text-sm text-gray-400">@{{ counsellor.username }}</p>
                    <p v-if="counsellor.bio" class="text-sm text-gray-600 mt-2 max-w-xl">{{ counsellor.bio }}</p>

                    <div class="flex flex-wrap gap-4 mt-3 text-xs text-gray-500">
                        <span v-if="counsellor.phone" class="flex items-center gap-1.5"><PhoneIcon class="h-3.5 w-3.5" /> {{ counsellor.phone }}</span>
                        <span v-if="counsellor.email" class="flex items-center gap-1.5"><EnvelopeIcon class="h-3.5 w-3.5" /> {{ counsellor.email }}</span>
                    </div>
                </div>

                <!-- Call target -->
                <div class="w-full sm:w-64 rounded-xl bg-gray-50 p-4">
                    <div class="flex items-center justify-between text-xs mb-1.5">
                        <span class="flex items-center gap-1 text-gray-500"><FlagIcon class="h-3.5 w-3.5" /> Call target today</span>
                        <span v-if="counsellor.call_target?.expired" class="text-gray-400">Expired</span>
                        <span v-else-if="counsellor.call_target?.today_required" class="font-medium text-gray-700">
                            {{ counsellor.call_target.today_calls }} / {{ counsellor.call_target.today_required }}
                        </span>
                        <span v-else class="text-gray-400">No target set</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-200 overflow-hidden">
                        <div :class="['h-full rounded-full transition-all', targetBarColor(targetPct())]"
                            :style="{ width: (targetPct() ?? 0) + '%' }" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Period pills -->
        <div class="flex flex-wrap gap-2 mb-5">
            <button v-for="p in periods" :key="p.key" type="button" @click="setPeriod(p.key)"
                :class="['px-3 py-1.5 rounded-full text-xs font-medium transition',
                    period === p.key ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50']">
                {{ p.label }}
            </button>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <!-- Tickets -->
            <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <p class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                        <TicketIcon class="h-4 w-4 text-purple-500" /> Tickets · {{ periodLabel }}
                    </p>
                    <span class="text-xs text-gray-400">{{ tickets.total }} total</span>
                </div>
                <ul class="divide-y divide-gray-100">
                    <li v-for="t in tickets.data" :key="t.id" class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <Link :href="`/tickets/${t.id}`" class="text-sm text-brand-600 hover:underline truncate block">
                                #{{ t.id }} — {{ t.subject }}
                            </Link>
                            <p class="text-xs text-gray-400 mt-0.5">{{ t.contact_number ?? '—' }} · {{ new Date(t.created_at).toLocaleString() }}</p>
                        </div>
                        <span :class="['badge shrink-0', ticketStatusColor[t.status] ?? 'bg-gray-100 text-gray-600']">{{ t.status }}</span>
                    </li>
                    <li v-if="!tickets.data.length" class="px-5 py-10 text-center text-sm text-gray-400">No tickets in this period.</li>
                </ul>
                <div v-if="tickets.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-500">{{ tickets.from }}–{{ tickets.to }} of {{ tickets.total }}</p>
                    <div class="flex gap-1">
                        <Link v-for="link in tickets.links" :key="link.label" :href="link.url ?? '#'"
                            :class="['px-2.5 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                            v-html="link.label" />
                    </div>
                </div>
            </div>

            <!-- Recordings -->
            <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <p class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                        <MicrophoneIcon class="h-4 w-4 text-brand-500" /> Recordings · {{ periodLabel }}
                    </p>
                    <span class="text-xs text-gray-400">{{ recordings.total }} total</span>
                </div>
                <ul class="divide-y divide-gray-100">
                    <li v-for="r in recordings.data" :key="r.id" class="px-5 py-3">
                        <div class="flex items-center justify-between gap-3 mb-1.5">
                            <p class="text-sm text-gray-700 truncate">{{ r.call?.caller ?? '—' }} → {{ r.call?.callee ?? '—' }}</p>
                            <span class="text-xs text-gray-400 shrink-0">{{ fmtDuration(r.duration) }}</span>
                        </div>
                        <audio controls preload="none" class="h-8 w-full" :src="`/api/recordings/${r.id}/download`" />
                    </li>
                    <li v-if="!recordings.data.length" class="px-5 py-10 text-center text-sm text-gray-400">No recordings in this period.</li>
                </ul>
                <div v-if="recordings.last_page > 1" class="flex items-center justify-between px-5 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-500">{{ recordings.from }}–{{ recordings.to }} of {{ recordings.total }}</p>
                    <div class="flex gap-1">
                        <Link v-for="link in recordings.links" :key="link.label" :href="link.url ?? '#'"
                            :class="['px-2.5 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                            v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Reports — all submitted, not scoped to the period filter -->
        <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden mt-5">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <p class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                    <DocumentTextIcon class="h-4 w-4 text-indigo-500" /> Monthly Progress Reports
                </p>
                <span class="text-xs text-gray-400">{{ progressReports.length }} submitted</span>
            </div>
            <ul class="divide-y divide-gray-100">
                <li v-for="r in progressReports" :key="r.id" class="px-5 py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <Link :href="`/progress-reports/${r.id}`" class="text-sm text-brand-600 hover:underline truncate block">
                            {{ monthLabel(r.month) }}
                        </Link>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ r.job_title || 'No job title given' }}
                            <span v-if="r.date_submitted"> · submitted {{ r.date_submitted }}</span>
                        </p>
                    </div>
                    <span :class="['badge shrink-0', statusColor[r.status] ?? 'bg-gray-100 text-gray-600']">
                        {{ statusLabels[r.status] ?? r.status }}
                    </span>
                </li>
                <li v-if="!progressReports.length" class="px-5 py-10 text-center text-sm text-gray-400">No progress reports submitted yet.</li>
            </ul>
        </div>
    </AppLayout>
</template>
