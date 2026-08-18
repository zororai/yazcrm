<script setup>
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PrinterIcon, ArrowUturnLeftIcon, TrashIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ appraisal: Object, staff: Array, can: Object });

const EMPLOYEE_FIELDS = [
    { key: 'job_description',  label: '1. Job Descriptions/Responsibilities (paste your JDs and KPIs)', rows: 4 },
    { key: 'past_activities',  label: '2a. Primary work activities undertaken since the last review period' },
    { key: 'past_strengths',   label: '2b. Strengths demonstrated in completing the above work activities' },
    { key: 'past_improve',     label: '2c. How can you improve your work performance?' },
    { key: 'future_help_org',  label: '3a. What can you do that would help the organization operate more effectively?' },
    { key: 'future_team_help', label: '3b. What can the rest of the team do that would help everyone do a better job?' },
    { key: 'future_goals',     label: '3c. What are your goals for next year, and how do you see yourself growing and developing professionally?' },
    { key: 'future_plans',     label: '3d. Specific plans/actions until next review period' },
];

function toDateInput(v) { return v ? String(v).slice(0, 10) : ''; }

const selfForm = useForm({
    job_title:       props.appraisal.job_title ?? '',
    department:      props.appraisal.department ?? '',
    employee_number: props.appraisal.employee_number ?? '',
    start_date:      toDateInput(props.appraisal.start_date),
    employee_responses: Object.fromEntries(
        EMPLOYEE_FIELDS.map(f => [f.key, props.appraisal.employee_responses?.[f.key] ?? ''])
    ),
});
selfForm.employee_responses.overall_comments = props.appraisal.employee_responses?.overall_comments ?? '';

const statusColor = {
    draft:     'bg-gray-100 text-gray-700',
    submitted: 'bg-amber-100 text-amber-800',
    completed: 'bg-green-100 text-green-800',
};

function saveSelf() {
    selfForm.put(`/appraisals/${props.appraisal.id}`);
}

function submitAppraisal() {
    if (!confirm('Submit your self-assessment to your supervisor? You will not be able to edit it afterwards.')) return;
    router.post(`/appraisals/${props.appraisal.id}/submit`);
}

function reopenAppraisal() {
    if (!confirm('Reopen this appraisal for editing?')) return;
    router.post(`/appraisals/${props.appraisal.id}/reopen`);
}

function deleteAppraisal() {
    if (!confirm('Permanently delete this appraisal?')) return;
    router.delete(`/appraisals/${props.appraisal.id}`, { onSuccess: () => router.visit('/appraisals') });
}

function goToReview() {
    router.get(`/appraisals/${props.appraisal.id}/review`);
}

function printDoc() {
    const stamp = new Date().toISOString().slice(0, 10);
    const originalTitle = document.title;
    document.title = `Appraisal - ${props.appraisal.user?.name ?? 'Staff'} - ${stamp}`;
    window.print();
    setTimeout(() => { document.title = originalTitle; }, 500);
}
</script>

<template>
    <AppLayout>
        <template #title>Appraisal — {{ appraisal.user?.name }}</template>
        <template #header-actions>
            <div class="flex gap-2 no-print">
                <button
                    v-if="appraisal.status !== 'draft'"
                    @click="goToReview"
                    class="btn-secondary btn-sm"
                >
                    <ClipboardDocumentCheckIcon class="h-4 w-4" /> View Supervisor Review
                </button>
                <button @click="printDoc" class="btn-secondary btn-sm"><PrinterIcon class="h-4 w-4" /> Print / Download</button>
                <button v-if="can.manage && appraisal.status !== 'draft'" @click="reopenAppraisal" class="btn-secondary btn-sm">
                    <ArrowUturnLeftIcon class="h-4 w-4" /> Reopen
                </button>
                <button v-if="can.delete" @click="deleteAppraisal" class="btn-danger btn-sm"><TrashIcon class="h-4 w-4" /> Delete</button>
            </div>
        </template>

        <div class="printable space-y-6 max-w-4xl">
            <div class="card">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Youth Advocates — Performance Appraisal Form</h2>
                    <span :class="['badge', statusColor[appraisal.status]]">{{ appraisal.status }}</span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="label">Employee Name</label><p class="text-sm text-gray-900">{{ appraisal.user?.name }}</p></div>
                    <div><label class="label">Reviewer</label><p class="text-sm text-gray-900">{{ appraisal.supervisor?.name ?? '—' }}</p></div>
                    <div>
                        <label class="label">Job Title</label>
                        <input v-model="selfForm.job_title" class="input" :disabled="!can.editSelf" />
                    </div>
                    <div>
                        <label class="label">Department</label>
                        <input v-model="selfForm.department" class="input" :disabled="!can.editSelf" />
                    </div>
                    <div>
                        <label class="label">Employee Number</label>
                        <input v-model="selfForm.employee_number" class="input" :disabled="!can.editSelf" />
                    </div>
                    <div>
                        <label class="label">Start Date</label>
                        <input v-model="selfForm.start_date" type="date" class="input" :disabled="!can.editSelf" />
                    </div>
                </div>
            </div>

            <!-- Employee self-assessment -->
            <div class="card">
                <h3 class="font-semibold text-gray-900 mb-1">Employee Self-Assessment</h3>
                <p class="text-xs text-gray-500 mb-4">
                    {{ can.editSelf ? 'Fill this in and submit it to your supervisor when ready.' : 'Completed by the employee.' }}
                </p>
                <div class="space-y-4">
                    <div>
                        <label class="label">Overall Comments</label>
                        <textarea v-model="selfForm.employee_responses.overall_comments" class="input" rows="3" :disabled="!can.editSelf"></textarea>
                    </div>
                    <div v-for="f in EMPLOYEE_FIELDS" :key="f.key">
                        <label class="label">{{ f.label }}</label>
                        <textarea v-model="selfForm.employee_responses[f.key]" class="input" :rows="f.rows ?? 3" :disabled="!can.editSelf"></textarea>
                    </div>
                </div>
                <div v-if="can.editSelf" class="flex gap-2 justify-end pt-4 no-print">
                    <button @click="saveSelf" class="btn-secondary" :disabled="selfForm.processing">Save Draft</button>
                    <button v-if="appraisal.status === 'draft'" @click="submitAppraisal" class="btn-primary" :disabled="selfForm.processing">
                        Submit to Supervisor
                    </button>
                </div>
                <p v-else-if="appraisal.status === 'draft'" class="text-xs text-gray-400 pt-2">
                    Waiting on the employee to complete and submit their self-assessment.
                </p>
            </div>

            <div class="card text-sm text-gray-600 grid grid-cols-2 gap-4">
                <div>
                    <p class="font-medium text-gray-900">Reviewer's Name and Position</p>
                    <p>{{ appraisal.supervisor?.name ?? '—' }}</p>
                    <p class="text-xs text-gray-400">{{ appraisal.supervisor_signed_at ? `Signed ${new Date(appraisal.supervisor_signed_at).toLocaleString()}` : 'Not yet signed' }}</p>
                </div>
                <div>
                    <p class="font-medium text-gray-900">Employee's Signature</p>
                    <p>{{ appraisal.user?.name }}</p>
                    <p class="text-xs text-gray-400">{{ appraisal.employee_signed_at ? `Signed ${new Date(appraisal.employee_signed_at).toLocaleString()}` : 'Not yet signed' }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    aside, header, .no-print { display: none !important; }
    main { padding: 0 !important; background: #fff !important; }
    .printable { max-width: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; break-inside: avoid; }
    textarea, input, select { border: none !important; background: transparent !important; color: #111 !important; }
}
</style>
