<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ report: Object });

const monthLabel = computed(() => new Date(props.report.month + 'T00:00:00').toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));
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
            <div>
                <h2 class="text-lg font-bold text-gray-900">Individual Monthly Progress Report</h2>
                <p class="text-xs text-gray-400 uppercase tracking-wide mt-1">{{ monthLabel }}</p>
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
        </div>
    </AppLayout>
</template>
