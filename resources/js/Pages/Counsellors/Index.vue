<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { MagnifyingGlassIcon, PhoneIcon, TicketIcon, FlagIcon, MicrophoneIcon, ChevronRightIcon, SunIcon, MoonIcon } from '@heroicons/vue/24/outline';
import { debounce } from 'lodash-es';

const props = defineProps({ counsellors: Array, filters: Object });

const search = ref(props.filters.search ?? '');
const period = ref(props.filters.period ?? 'today');

const periods = [
    { key: 'today', label: 'Today' },
    { key: 'week',  label: 'This Week' },
    { key: 'month', label: 'This Month' },
    { key: 'year',  label: 'This Year' },
];

const periodLabel = computed(() => periods.find(p => p.key === period.value)?.label ?? 'Today');

const runFilter = debounce(() => {
    router.get('/counsellor-profiles', { search: search.value || undefined, period: period.value }, { preserveState: true, replace: true });
}, 350);

function setPeriod(key) {
    period.value = key;
    runFilter();
}

const totals = computed(() => ({
    counsellors: props.counsellors.length,
    ticketsToday: props.counsellors.reduce((sum, c) => sum + c.tickets_today, 0),
    recordingsToday: props.counsellors.reduce((sum, c) => sum + c.recordings_today, 0),
}));

function targetPct(c) {
    const required = c.call_target?.today_required;
    if (!required) return null;
    return Math.min(100, Math.round((c.call_target.today_calls / required) * 100));
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

</script>

<template>
    <AppLayout>
        <template #title>Counsellor Profiles</template>

        <!-- Summary strip -->
        <div class="grid grid-cols-3 gap-4 mb-5 max-w-2xl">
            <div class="rounded-2xl p-5 bg-gradient-to-br from-brand-600 to-indigo-600 text-white shadow-sm">
                <p class="text-2xl font-bold">{{ totals.counsellors }}</p>
                <p class="text-xs text-white/80 mt-0.5">Counsellors</p>
            </div>
            <div class="rounded-2xl p-5 bg-white border border-gray-100 shadow-sm">
                <p class="text-2xl font-bold text-gray-900 flex items-center gap-2"><TicketIcon class="h-5 w-5 text-purple-500" /> {{ totals.ticketsToday }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Tickets · {{ periodLabel }}</p>
            </div>
            <div class="rounded-2xl p-5 bg-white border border-gray-100 shadow-sm">
                <p class="text-2xl font-bold text-gray-900 flex items-center gap-2"><MicrophoneIcon class="h-5 w-5 text-brand-500" /> {{ totals.recordingsToday }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Recordings · {{ periodLabel }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4 mb-5">
            <div class="relative max-w-sm flex-1 min-w-[220px]">
                <MagnifyingGlassIcon class="h-4 w-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input v-model="search" @input="runFilter" class="input pl-9 bg-white shadow-sm" placeholder="Search name, username, email…" />
            </div>
            <div class="flex flex-wrap gap-2">
                <button v-for="p in periods" :key="p.key" type="button" @click="setPeriod(p.key)"
                    :class="['px-3 py-1.5 rounded-full text-xs font-medium transition',
                        period === p.key ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50']">
                    {{ p.label }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <Link v-for="c in counsellors" :key="c.id" :href="`/counsellor-profiles/${c.id}?period=${period}`"
                class="block rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden hover:shadow-md hover:border-brand-100 transition">
                <div class="p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <img :src="c.avatar ? `/storage/${c.avatar}` : '/images/default-avatar.png'"
                            class="h-14 w-14 rounded-full object-cover ring-2 ring-brand-50 flex-shrink-0" />
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-900 truncate">{{ c.name }}</p>
                            <p v-if="c.username" class="text-xs text-gray-400">@{{ c.username }}</p>
                            <span class="flex items-center gap-1.5 mt-1 flex-wrap">
                                <span :class="['badge', c.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500']">
                                    {{ c.is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span v-if="c.duty_today === 'day'" class="badge bg-amber-100 text-amber-800 inline-flex items-center gap-1">
                                    <SunIcon class="h-3 w-3" /> On duty · Day
                                </span>
                                <span v-else-if="c.duty_today === 'night'" class="badge bg-indigo-100 text-indigo-800 inline-flex items-center gap-1">
                                    <MoonIcon class="h-3 w-3" /> On duty · Night
                                </span>
                                <span v-else class="badge bg-gray-100 text-gray-500">Off today</span>
                            </span>
                        </div>
                        <ChevronRightIcon class="h-5 w-5 text-gray-300 flex-shrink-0" />
                    </div>

                    <p v-if="c.bio" class="text-xs text-gray-500 mb-3 line-clamp-2">{{ c.bio }}</p>

                    <div class="flex items-center gap-1.5 text-xs text-gray-500 mb-4" v-if="c.phone">
                        <PhoneIcon class="h-3.5 w-3.5" /> {{ c.phone }}
                    </div>

                    <!-- Call target progress -->
                    <div class="mb-3">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="flex items-center gap-1 text-gray-500"><FlagIcon class="h-3.5 w-3.5" /> Call target today</span>
                            <span v-if="c.call_target?.expired" class="text-gray-400">Expired</span>
                            <span v-else-if="c.call_target?.today_required" class="font-medium text-gray-700">
                                {{ c.call_target.today_calls }} / {{ c.call_target.today_required }}
                            </span>
                            <span v-else class="text-gray-400">No target set</span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div :class="['h-full rounded-full transition-all', targetBarColor(targetPct(c))]"
                                :style="{ width: (targetPct(c) ?? 0) + '%' }" />
                        </div>
                    </div>

                    <!-- Tickets / Recordings — click card to view full details -->
                    <div class="flex items-center gap-2">
                        <span class="flex-1 flex items-center justify-between rounded-xl bg-purple-50 px-3 py-2">
                            <span class="flex items-center gap-1.5 text-xs font-medium text-purple-700">
                                <TicketIcon class="h-3.5 w-3.5" /> Tickets
                            </span>
                            <span class="text-sm font-bold text-purple-800">{{ c.tickets_today }}</span>
                        </span>
                        <span class="flex-1 flex items-center justify-between rounded-xl bg-brand-50 px-3 py-2">
                            <span class="flex items-center gap-1.5 text-xs font-medium text-brand-700">
                                <MicrophoneIcon class="h-3.5 w-3.5" /> Recordings
                            </span>
                            <span class="text-sm font-bold text-brand-800">{{ c.recordings_today }}</span>
                        </span>
                    </div>
                </div>
            </Link>

            <div v-if="!counsellors.length" class="col-span-full rounded-2xl bg-white border border-gray-100 py-16 text-center text-gray-400">
                No counsellors found.
            </div>
        </div>
    </AppLayout>
</template>
