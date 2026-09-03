<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ report: Object, isManager: Boolean, statuses: Array });

const monthLabel = computed(() => new Date(props.report.month + 'T00:00:00').toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));

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

const reviewForm = useForm({
    status: props.report.status,
    review_notes: props.report.review_notes ?? '',
});

function submitReview() {
    reviewForm.post(`/progress-reports/${props.report.id}/status`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <template #title>Progress Report — {{ report.user?.name }}</template>
        <template #header-actions>
            <Link href="/progress-reports" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                <ArrowLeftIcon class="h-4 w-4" /> Back
            </Link>
        </template>

        <div class="card space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Individual Monthly Progress Report</h2>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mt-1">{{ monthLabel }}</p>
                </div>
                <span :class="['badge', statusColor[report.status] ?? 'bg-gray-100 text-gray-600']">
                    {{ statusLabels[report.status] ?? report.status }}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <p><span class="text-gray-400">Name:</span> {{ report.user?.name }}</p>
                <p><span class="text-gray-400">Job Title:</span> {{ report.job_title || '—' }}</p>
                <p><span class="text-gray-400">Supervisor:</span> {{ report.supervisor || '—' }}</p>
                <p><span class="text-gray-400">Date Submitted:</span> {{ report.date_submitted || '—' }}</p>
            </div>

            <div>
                <p class="label">Monthly Overall Progress</p>
                <p class="text-sm text-gray-700 whitespace-pre-wrap rounded-xl bg-gray-50 p-3">{{ report.overall_progress || '—' }}</p>
            </div>

            <div>
                <p class="label mb-2">Workplan Activities</p>
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr>
                                <th class="px-3 py-2 text-left">Workplan Activities</th>
                                <th class="px-3 py-2 text-left w-32">Completed</th>
                                <th class="px-3 py-2 text-left">Progress Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(row, i) in report.activities" :key="i">
                                <td class="px-3 py-2">{{ row.activity || '—' }}</td>
                                <td class="px-3 py-2">{{ row.completed || '—' }}</td>
                                <td class="px-3 py-2">{{ row.details || '—' }}</td>
                            </tr>
                            <tr v-if="!report.activities?.length">
                                <td colspan="3" class="px-3 py-6 text-center text-gray-400">No activities logged.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="report.reviewer" class="text-xs text-gray-400">
                Last reviewed by {{ report.reviewer.name }} on {{ new Date(report.reviewed_at).toLocaleString() }}
            </div>
        </div>

        <!-- Responsible authority review — manager/supervisor only -->
        <div v-if="isManager" class="card mt-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Review</h3>
            <form @submit.prevent="submitReview" class="space-y-3">
                <div class="flex flex-wrap gap-2">
                    <button v-for="s in statuses" :key="s" type="button" @click="reviewForm.status = s"
                        :class="['px-3 py-1.5 rounded-full text-xs font-medium transition-colors',
                            reviewForm.status === s ? statusColor[s] + ' ring-2 ring-offset-1 ring-current' : 'bg-gray-100 text-gray-500 hover:bg-gray-200']">
                        {{ statusLabels[s] ?? s }}
                    </button>
                </div>
                <div>
                    <label class="label">Notes (optional)</label>
                    <textarea v-model="reviewForm.review_notes" rows="3" class="input resize-none" placeholder="Feedback for the agent…" />
                </div>
                <button type="submit" :disabled="reviewForm.processing" class="btn-primary btn-sm">
                    {{ reviewForm.processing ? 'Saving…' : 'Save Review' }}
                </button>
            </form>
        </div>
    </AppLayout>
</template>
