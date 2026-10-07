<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { DocumentTextIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    month: String,           // 'all' or YYYY-MM-01
    reports: Array,
    notSubmitted: Array,
    availableMonths: Array,
    staff: Array,
    filters: Object,
});

const isAll = computed(() => props.month === 'all');
const month = ref(isAll.value ? '' : props.month.slice(0, 7));
const status = ref(props.filters?.status ?? '');
const userId = ref(props.filters?.user_id ?? '');

function load(overrides = {}) {
    const params = {
        month: month.value ? `${month.value}-01` : 'all',
        status: status.value || undefined,
        user_id: userId.value || undefined,
        ...overrides,
    };
    router.get('/progress-reports/team', params, { preserveState: true, replace: true });
}
function showAll() {
    month.value = '';
    load({ month: 'all' });
}
function pickMonth(m) {
    month.value = m.slice(0, 7);
    load();
}

const fmtMonth = m => new Date(m.slice(0, 7) + '-01T00:00:00').toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
const heading = computed(() => (isAll.value ? 'All months' : fmtMonth(props.month)));

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

const totals = computed(() => ({
    submitted: props.reports.length,
    missing:   props.notSubmitted.length,
    approved:  props.reports.filter(r => r.status === 'approved').length,
    pending:   props.reports.filter(r => r.status === 'pending').length,
}));
</script>

<template>
    <AppLayout>
        <template #title>Team Reports</template>

        <div class="card mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="label">Month</label>
                <div class="flex gap-2">
                    <input v-model="month" @change="load()" type="month" class="input" />
                    <button type="button" @click="showAll" :class="['btn-sm', isAll ? 'btn-primary' : 'btn-secondary']">All months</button>
                </div>
            </div>
            <div>
                <label class="label">Status</label>
                <select v-model="status" @change="load()" class="input">
                    <option value="">Any status</option>
                    <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>
            <div>
                <label class="label">Staff member</label>
                <select v-model="userId" @change="load()" class="input">
                    <option value="">Everyone</option>
                    <option v-for="u in staff" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
            </div>
            <p class="text-sm text-gray-500 pb-2">Showing <span class="font-medium text-gray-700">{{ heading }}</span></p>
        </div>

        <!-- Months that have reports -->
        <div v-if="availableMonths.length" class="flex flex-wrap gap-2 mb-4">
            <button v-for="m in availableMonths" :key="m.month" type="button" @click="pickMonth(m.month)"
                :class="['px-3 py-1 rounded-full text-xs font-medium border', !isAll && month === m.month.slice(0, 7)
                    ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-600 border-gray-200 hover:border-brand-300']">
                {{ fmtMonth(m.month) }} · {{ m.count }}
            </button>
        </div>

        <!-- Summary strip -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
            <div class="rounded-2xl p-5 bg-gradient-to-br from-brand-600 to-indigo-600 text-white shadow-sm">
                <p class="text-2xl font-bold">{{ totals.submitted }}</p>
                <p class="text-xs text-white/80 mt-0.5">Reports</p>
            </div>
            <div class="rounded-2xl p-5 bg-white border border-gray-100 shadow-sm">
                <p class="text-2xl font-bold text-gray-900">{{ totals.approved }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Approved</p>
            </div>
            <div class="rounded-2xl p-5 bg-white border border-gray-100 shadow-sm">
                <p class="text-2xl font-bold text-gray-900">{{ totals.pending }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Pending review</p>
            </div>
            <div class="rounded-2xl p-5 bg-white border border-gray-100 shadow-sm">
                <p class="text-2xl font-bold text-gray-900">{{ isAll ? '—' : totals.missing }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ isAll ? 'Not submitted (pick a month)' : 'Not submitted' }}</p>
            </div>
        </div>

        <div class="card">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Reports</h3>
            <ul class="divide-y divide-gray-100">
                <li v-for="r in reports" :key="r.id" class="py-2.5 flex items-center justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <DocumentTextIcon class="h-4 w-4 text-gray-300" />
                        <span class="text-sm text-gray-800">{{ r.user?.name }}</span>
                        <span class="text-xs text-gray-500">{{ fmtMonth(r.month) }}</span>
                        <span v-if="r.job_title" class="text-xs text-gray-400">· {{ r.job_title }}</span>
                        <span :class="['badge', statusColor[r.status] ?? 'bg-gray-100 text-gray-600']">
                            {{ statusLabels[r.status] ?? r.status }}
                        </span>
                    </div>
                    <Link :href="`/progress-reports/${r.id}`" class="text-xs text-brand-600 hover:underline">View</Link>
                </li>
                <li v-if="!reports.length" class="py-6 text-center text-sm text-gray-400">
                    {{ isAll ? 'No progress reports match.' : 'No reports for this month yet.' }}
                </li>
            </ul>
        </div>

        <div class="card mt-5" v-if="notSubmitted.length">
            <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-1.5">
                <ExclamationCircleIcon class="h-4 w-4 text-amber-500" /> Not Yet Submitted — {{ heading }}
            </h3>
            <div class="flex flex-wrap gap-2">
                <span v-for="u in notSubmitted" :key="u.id" class="px-3 py-1.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700">
                    {{ u.name }}
                </span>
            </div>
        </div>
    </AppLayout>
</template>
