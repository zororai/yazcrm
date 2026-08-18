<script setup>
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

defineProps({ reports: Array });

const newForm = useForm({});

function startNew() {
    newForm.post('/activity-reports');
}

function open(report) {
    router.get(`/activity-reports/${report.id}`);
}

const statusColor = {
    draft:     'bg-gray-100 text-gray-700',
    submitted: 'bg-amber-100 text-amber-800',
    reviewed:  'bg-blue-100 text-blue-800',
    approved:  'bg-green-100 text-green-800',
};
</script>

<template>
    <AppLayout>
        <template #title>Activity Reports</template>
        <template #header-actions>
            <button @click="startNew" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Report
            </button>
        </template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Activity</th>
                        <th class="table-th">District</th>
                        <th class="table-th">Date</th>
                        <th class="table-th">Compiled By</th>
                        <th class="table-th">Status</th>
                        <th class="table-th w-24" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in reports" :key="r.id" class="hover:bg-gray-50 cursor-pointer" @click="open(r)">
                        <td class="table-td font-medium">{{ r.name_of_activity || '(untitled)' }}</td>
                        <td class="table-td">{{ r.district ?? '—' }}</td>
                        <td class="table-td text-xs">{{ r.date ? new Date(r.date).toLocaleDateString() : '—' }}</td>
                        <td class="table-td">{{ r.compiler?.name ?? '—' }}</td>
                        <td class="table-td">
                            <span :class="['badge', statusColor[r.status]]">{{ r.status }}</span>
                        </td>
                        <td class="table-td text-right">
                            <DocumentTextIcon class="h-4 w-4 text-gray-400 inline-block" />
                        </td>
                    </tr>
                    <tr v-if="!reports.length">
                        <td colspan="6" class="table-td text-center text-gray-400 py-8">No activity reports yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
