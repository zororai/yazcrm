<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline';

defineProps({ appraisals: Array });

function open(appraisal) {
    router.get(`/appraisals/${appraisal.id}/review`);
}

const statusColor = {
    submitted: 'bg-amber-100 text-amber-800',
    completed: 'bg-green-100 text-green-800',
};
</script>

<template>
    <AppLayout>
        <template #title>Appraisal Reviews</template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Employee</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Overall Rating</th>
                        <th class="table-th">Submitted</th>
                        <th class="table-th w-24" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="a in appraisals" :key="a.id" class="hover:bg-gray-50 cursor-pointer" @click="open(a)">
                        <td class="table-td font-medium">{{ a.user?.name ?? '—' }}</td>
                        <td class="table-td">
                            <span :class="['badge', statusColor[a.status]]">{{ a.status }}</span>
                        </td>
                        <td class="table-td">{{ a.overall_rating ?? '—' }}</td>
                        <td class="table-td text-xs">{{ a.submitted_at ? new Date(a.submitted_at).toLocaleDateString() : '—' }}</td>
                        <td class="table-td text-right">
                            <ClipboardDocumentCheckIcon class="h-4 w-4 text-gray-400 inline-block" />
                        </td>
                    </tr>
                    <tr v-if="!appraisals.length">
                        <td colspan="5" class="table-td text-center text-gray-400 py-8">Nothing awaiting review.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
