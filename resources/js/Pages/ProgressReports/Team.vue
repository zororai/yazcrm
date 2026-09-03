<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { DocumentTextIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    month: String,
    reports: Array,
    notSubmitted: Array,
});

const month = ref(props.month.slice(0, 7));

function changeMonth() {
    router.get('/progress-reports/team', { month: month.value + '-01' }, { preserveState: true, replace: true });
}

const monthLabel = computed(() => new Date(month.value + '-01T00:00:00').toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));

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
                <input v-model="month" @change="changeMonth" type="month" class="input" />
            </div>
            <p class="text-sm text-gray-500 pb-2">Reports for <span class="font-medium text-gray-700">{{ monthLabel }}</span></p>
        </div>

        <!-- Summary strip -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
            <div class="rounded-2xl p-5 bg-gradient-to-br from-brand-600 to-indigo-600 text-white shadow-sm">
                <p class="text-2xl font-bold">{{ totals.submitted }}</p>
                <p class="text-xs text-white/80 mt-0.5">Submitted</p>
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
                <p class="text-2xl font-bold text-gray-900">{{ totals.missing }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Not submitted</p>
            </div>
        </div>

        <div class="card">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Submitted Reports</h3>
            <ul class="divide-y divide-gray-100">
                <li v-for="r in reports" :key="r.id" class="py-2.5 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <DocumentTextIcon class="h-4 w-4 text-gray-300" />
                        <span class="text-sm text-gray-800">{{ r.user?.name }}</span>
                        <span v-if="r.job_title" class="text-xs text-gray-400">· {{ r.job_title }}</span>
                        <span :class="['badge', statusColor[r.status] ?? 'bg-gray-100 text-gray-600']">
                            {{ statusLabels[r.status] ?? r.status }}
                        </span>
                    </div>
                    <Link :href="`/progress-reports/${r.id}`" class="text-xs text-brand-600 hover:underline">View</Link>
                </li>
                <li v-if="!reports.length" class="py-6 text-center text-sm text-gray-400">No reports submitted for this month yet.</li>
            </ul>
        </div>

        <div class="card mt-5" v-if="notSubmitted.length">
            <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-1.5">
                <ExclamationCircleIcon class="h-4 w-4 text-amber-500" /> Not Yet Submitted
            </h3>
            <div class="flex flex-wrap gap-2">
                <span v-for="u in notSubmitted" :key="u.id" class="px-3 py-1.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700">
                    {{ u.name }}
                </span>
            </div>
        </div>
    </AppLayout>
</template>
