<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ assignments: Array, submissions: Array });

function start(assignment) {
    router.post(`/data-collection/assignments/${assignment.id}/start`);
}

function open(submission) {
    router.get(`/data-collection/submissions/${submission.id}`);
}

const statusColor = {
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-green-100 text-green-800',
};
</script>

<template>
    <AppLayout>
        <template #title>My Collection</template>

        <div class="card mb-4">
            <h3 class="font-semibold text-gray-900 mb-3">Assignments</h3>
            <ul class="divide-y divide-gray-50">
                <li v-for="a in assignments" :key="a.id" class="flex items-center justify-between py-3 text-sm">
                    <div>
                        <p class="font-medium text-gray-900">{{ a.form?.name }}</p>
                        <p class="text-xs text-gray-400">{{ a.form?.project?.name }}
                            <span v-if="a.due_date"> — due {{ new Date(a.due_date).toLocaleDateString() }}</span>
                        </p>
                    </div>
                    <button @click="start(a)" class="btn-primary btn-sm">
                        {{ a.status === 'in_progress' ? 'Continue' : 'Start' }}
                    </button>
                </li>
                <li v-if="!assignments.length" class="text-sm text-gray-400 text-center py-6">No assignments right now.</li>
            </ul>
        </div>

        <div class="card">
            <h3 class="font-semibold text-gray-900 mb-3">My Submissions</h3>
            <ul class="divide-y divide-gray-50">
                <li
                    v-for="s in submissions" :key="s.id"
                    @click="open(s)"
                    class="flex items-center justify-between py-3 text-sm cursor-pointer hover:bg-gray-50 -mx-2 px-2 rounded"
                >
                    <div>
                        <p class="font-medium text-gray-900">{{ s.form?.name }}</p>
                        <p class="text-xs text-gray-400">{{ s.completion_percentage }}% complete</p>
                    </div>
                    <span :class="['badge', statusColor[s.status]]">{{ s.status }}</span>
                </li>
                <li v-if="!submissions.length" class="text-sm text-gray-400 text-center py-6">No submissions yet.</li>
            </ul>
        </div>
    </AppLayout>
</template>
