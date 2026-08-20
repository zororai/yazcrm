<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ myAppraisals: Array, appraisalsToReview: Array, myTasks: Array, counts: Object });

const statusColor = {
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-amber-100 text-amber-800',
    not_started: 'bg-gray-100 text-gray-700',
    in_progress: 'bg-blue-100 text-blue-800',
    blocked: 'bg-red-100 text-red-800',
};

function openAppraisal(a) {
    router.get(`/appraisals/${a.id}`);
}

function openReview(a) {
    router.get(`/appraisals/${a.id}/review`);
}

function openTask(t) {
    router.get(`/tasks/${t.id}`);
}

function isOverdue(t) {
    return t.due_date && new Date(t.due_date) < new Date(new Date().toDateString()) && t.status !== 'completed';
}
</script>

<template>
    <AppLayout>
        <template #title>My Work</template>

        <div class="grid grid-cols-4 gap-3 mb-6">
            <div class="card py-3 text-center">
                <p class="text-xl font-semibold text-gray-900">{{ counts.tasksOpen }}</p>
                <p class="text-xs text-gray-500">Open Tasks</p>
            </div>
            <div class="card py-3 text-center">
                <p class="text-xl font-semibold text-red-700">{{ counts.tasksOverdue }}</p>
                <p class="text-xs text-gray-500">Overdue Tasks</p>
            </div>
            <div class="card py-3 text-center">
                <p class="text-xl font-semibold text-gray-900">{{ counts.appraisalsMine }}</p>
                <p class="text-xs text-gray-500">My Appraisals</p>
            </div>
            <div class="card py-3 text-center">
                <p class="text-xl font-semibold text-gray-900">{{ counts.appraisalsQueue }}</p>
                <p class="text-xs text-gray-500">Reviews Awaiting Me</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="card">
                <h3 class="font-semibold text-gray-900 mb-3">My Tasks</h3>
                <ul class="space-y-2">
                    <li
                        v-for="t in myTasks" :key="t.id"
                        @click="openTask(t)"
                        class="flex items-center justify-between text-sm border-b border-gray-50 pb-2 cursor-pointer hover:bg-gray-50 -mx-2 px-2 rounded"
                    >
                        <div>
                            <p class="font-medium text-gray-900">{{ t.title }}</p>
                            <p class="text-xs text-gray-400">{{ t.board?.name }}</p>
                        </div>
                        <div class="text-right">
                            <span :class="['badge', statusColor[t.status]]">{{ t.status.replace('_', ' ') }}</span>
                            <p v-if="t.due_date" :class="['text-xs mt-1', isOverdue(t) ? 'text-red-600 font-medium' : 'text-gray-400']">
                                {{ new Date(t.due_date).toLocaleDateString() }}
                            </p>
                        </div>
                    </li>
                    <li v-if="!myTasks.length" class="text-sm text-gray-400 text-center py-6">No open tasks assigned to you.</li>
                </ul>
            </div>

            <div class="space-y-4">
                <div class="card">
                    <h3 class="font-semibold text-gray-900 mb-3">My Appraisals</h3>
                    <ul class="space-y-2">
                        <li
                            v-for="a in myAppraisals" :key="a.id"
                            @click="openAppraisal(a)"
                            class="flex items-center justify-between text-sm border-b border-gray-50 pb-2 cursor-pointer hover:bg-gray-50 -mx-2 px-2 rounded"
                        >
                            <span>{{ a.status === 'draft' ? 'Self-assessment needed' : 'Awaiting supervisor review' }}</span>
                            <span :class="['badge', statusColor[a.status]]">{{ a.status }}</span>
                        </li>
                        <li v-if="!myAppraisals.length" class="text-sm text-gray-400 text-center py-6">Nothing pending.</li>
                    </ul>
                </div>

                <div class="card">
                    <h3 class="font-semibold text-gray-900 mb-3">Reviews Awaiting Me</h3>
                    <ul class="space-y-2">
                        <li
                            v-for="a in appraisalsToReview" :key="a.id"
                            @click="openReview(a)"
                            class="flex items-center justify-between text-sm border-b border-gray-50 pb-2 cursor-pointer hover:bg-gray-50 -mx-2 px-2 rounded"
                        >
                            <span>{{ a.user?.name }}</span>
                            <span :class="['badge', statusColor[a.status]]">{{ a.status }}</span>
                        </li>
                        <li v-if="!appraisalsToReview.length" class="text-sm text-gray-400 text-center py-6">Nothing to review.</li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
