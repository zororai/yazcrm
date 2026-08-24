<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ submissions: Array });

function open(s) {
    router.get(`/data-collection/submissions/${s.id}`);
}

const statusColor = {
    submitted: 'bg-amber-100 text-amber-800',
    under_review: 'bg-blue-100 text-blue-800',
};
</script>

<template>
    <AppLayout>
        <template #title>Review Queue</template>
        <template #subtitle>Submissions awaiting review, across all projects and forms.</template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Form</th>
                        <th class="table-th">Project</th>
                        <th class="table-th">Submitted By</th>
                        <th class="table-th">Submitted</th>
                        <th class="table-th">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in submissions" :key="s.id" class="hover:bg-gray-50 cursor-pointer" @click="open(s)">
                        <td class="table-td font-medium">{{ s.form?.name }}</td>
                        <td class="table-td">{{ s.form?.project?.name }}</td>
                        <td class="table-td">{{ s.submitted_by?.name }}</td>
                        <td class="table-td text-xs">{{ s.submitted_at ? new Date(s.submitted_at).toLocaleString() : '—' }}</td>
                        <td class="table-td"><span :class="['badge', statusColor[s.status]]">{{ s.status.replace('_', ' ') }}</span></td>
                    </tr>
                    <tr v-if="!submissions.length">
                        <td colspan="5" class="table-td text-center text-gray-400 py-8">Nothing waiting for review.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
