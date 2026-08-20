<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PrinterIcon, ArrowUturnLeftIcon, TrashIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ appraisal: Object, can: Object, activityLogs: { type: Array, default: () => [] }, tasks: { type: Array, default: () => [] } });

const SUPERVISOR_RATED_FIELDS = [
    { key: 'key_strengths',          label: '1a. Key Strengths and Accomplishments' },
    { key: 'improvement_areas',      label: '1b. Areas of Improvement / Development Needs' },
    { key: 'goals_achieved',         label: '1c. Goals and Objectives Achieved' },
    { key: 'technical_skills',       label: '2a. Technical Skills (software proficiency)' },
    { key: 'industry_knowledge',     label: '2b. Industry Knowledge (Business Acumen)' },
    { key: 'communication_skills',   label: '2c. Communication Skills (Verbal, Written, Presentations)' },
    { key: 'problem_solving',        label: '2d. Problem Solving and Decision Making' },
    { key: 'leadership_teamwork',    label: '2e. Leadership and Teamwork' },
    { key: 'adaptability',           label: '3a. Adaptability and Flexibility' },
    { key: 'initiative',             label: '3b. Initiative and Proactivity' },
    { key: 'time_management',        label: '3c. Time Management and Productivity' },
    { key: 'stakeholder_engagement', label: '3d. Stakeholder Engagement and Productivity' },
    { key: 'team_collaboration',     label: '3e. Team Collaboration and Support' },
];

const SUPERVISOR_TEXT_FIELDS = [
    { key: 'long_term_goals',              label: '4a. Long Term Goals (Career Development)' },
    { key: 'training_needs',               label: '4b. Training and Development Needs' },
    { key: 'mentorship',                   label: '4c. Mentorship or Coaching Requirements' },
    { key: 'succession_planning',          label: '4d. Succession Planning (if applicable)' },
    { key: 'recommendations_improvement',  label: '4e. Recommendations for Improvement' },
    { key: 'action_plan',                  label: '4f. Action Plan for Improvement' },
];

function toDateInput(v) { return v ? String(v).slice(0, 10) : ''; }

const reviewForm = useForm({
    date_of_review:    toDateInput(props.appraisal.date_of_review),
    next_review_date:  toDateInput(props.appraisal.next_review_date),
    overall_rating:    props.appraisal.overall_rating ?? '',
    supervisor_responses: {
        reviewer_summary: props.appraisal.supervisor_responses?.reviewer_summary ?? '',
        ...Object.fromEntries(SUPERVISOR_RATED_FIELDS.map(f => [f.key, {
            text:   props.appraisal.supervisor_responses?.[f.key]?.text ?? '',
            rating: props.appraisal.supervisor_responses?.[f.key]?.rating ?? '',
        }])),
        ...Object.fromEntries(SUPERVISOR_TEXT_FIELDS.map(f => [f.key, props.appraisal.supervisor_responses?.[f.key] ?? ''])),
    },
});

const statusColor = {
    draft:     'bg-gray-100 text-gray-700',
    submitted: 'bg-amber-100 text-amber-800',
    completed: 'bg-green-100 text-green-800',
};

const reviewLocked = computed(() => props.appraisal.status === 'draft');

function saveReview() {
    reviewForm.put(`/appraisals/${props.appraisal.id}/review`);
}

function completeAppraisal() {
    if (!confirm('Mark this appraisal as completed? The review section will be locked.')) return;
    router.post(`/appraisals/${props.appraisal.id}/complete`);
}

function reopenAppraisal() {
    const reason = prompt('Why are you reopening this appraisal?');
    if (!reason) return;
    router.post(`/appraisals/${props.appraisal.id}/reopen`, { reason });
}

function deleteAppraisal() {
    if (!confirm('Permanently delete this appraisal?')) return;
    router.delete(`/appraisals/${props.appraisal.id}`, { onSuccess: () => router.visit('/appraisal-reviews') });
}

function goToSelfAssessment() {
    router.get(`/appraisals/${props.appraisal.id}`);
}

function printDoc() {
    const stamp = new Date().toISOString().slice(0, 10);
    const originalTitle = document.title;
    document.title = `Appraisal Review - ${props.appraisal.user?.name ?? 'Staff'} - ${stamp}`;
    window.print();
    setTimeout(() => { document.title = originalTitle; }, 500);
}
</script>

<template>
    <AppLayout>
        <template #title>Supervisor Review — {{ appraisal.user?.name }}</template>
        <template #header-actions>
            <div class="flex gap-2 no-print">
                <button @click="goToSelfAssessment" class="btn-secondary btn-sm">
                    <DocumentTextIcon class="h-4 w-4" /> View Self-Assessment
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
                    <h2 class="text-lg font-semibold text-gray-900">Supervisor / Reviewer Evaluation</h2>
                    <span :class="['badge', statusColor[appraisal.status]]">{{ appraisal.status }}</span>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div><label class="label">Employee</label><p class="text-sm text-gray-900">{{ appraisal.user?.name }}</p></div>
                    <div><label class="label">Reviewer</label><p class="text-sm text-gray-900">{{ appraisal.supervisor?.name ?? '—' }}</p></div>
                    <div>
                        <label class="label">Date of Review</label>
                        <input v-model="reviewForm.date_of_review" type="date" class="input" :disabled="!can.editReview" />
                    </div>
                    <div>
                        <label class="label">Next Date of Review</label>
                        <input v-model="reviewForm.next_review_date" type="date" class="input" :disabled="!can.editReview" />
                    </div>
                </div>
                <p class="text-xs text-gray-500">
                    <span v-if="reviewLocked">Available once the employee submits their self-assessment.</span>
                    <span v-else>Please use the rating scale (1 = Unsatisfactory, 5 = Outstanding) after commenting on each indicator.</span>
                </p>
            </div>

            <div class="card space-y-4">
                <div>
                    <label class="label">Reviewer's Summary Report</label>
                    <textarea v-model="reviewForm.supervisor_responses.reviewer_summary" class="input" rows="3" :disabled="!can.editReview"></textarea>
                </div>
                <div>
                    <label class="label">Overall Performance Rating (1–5)</label>
                    <select v-model="reviewForm.overall_rating" class="input w-32" :disabled="!can.editReview">
                        <option value="">—</option>
                        <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                    </select>
                </div>
            </div>

            <div class="card space-y-4">
                <h3 class="font-semibold text-gray-900">Performance Evaluation</h3>
                <div v-for="f in SUPERVISOR_RATED_FIELDS" :key="f.key" class="grid grid-cols-[1fr_100px] gap-3 items-start">
                    <div>
                        <label class="label">{{ f.label }}</label>
                        <textarea v-model="reviewForm.supervisor_responses[f.key].text" class="input" rows="2" :disabled="!can.editReview"></textarea>
                    </div>
                    <div>
                        <label class="label">Rating</label>
                        <select v-model="reviewForm.supervisor_responses[f.key].rating" class="input" :disabled="!can.editReview">
                            <option value="">—</option>
                            <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card space-y-4">
                <h3 class="font-semibold text-gray-900">Recommendations (Goals and Development)</h3>
                <div v-for="f in SUPERVISOR_TEXT_FIELDS" :key="f.key">
                    <label class="label">{{ f.label }}</label>
                    <textarea v-model="reviewForm.supervisor_responses[f.key]" class="input" rows="2" :disabled="!can.editReview"></textarea>
                </div>
            </div>

            <div v-if="can.editReview" class="flex gap-2 justify-end no-print">
                <button @click="saveReview" class="btn-secondary" :disabled="reviewForm.processing">Save Draft</button>
                <button v-if="appraisal.status === 'submitted'" @click="completeAppraisal" class="btn-primary" :disabled="reviewForm.processing">
                    Complete Appraisal
                </button>
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

            <div v-if="tasks.length" class="card no-print">
                <h3 class="font-semibold text-gray-900 mb-2">Development Tasks</h3>
                <p class="text-xs text-gray-500 mb-3">Generated from the fields below when this appraisal is completed.</p>
                <ul class="space-y-1 text-sm">
                    <li v-for="t in tasks" :key="t.id">
                        <a :href="`/tasks/${t.id}`" class="text-blue-600 hover:underline">{{ t.title }}</a>
                        <span class="text-xs text-gray-400"> — {{ t.status.replace('_', ' ') }}</span>
                    </li>
                </ul>
            </div>

            <details v-if="can.manage && activityLogs.length" class="card no-print">
                <summary class="font-semibold text-gray-900 cursor-pointer">History</summary>
                <ul class="mt-3 space-y-2 text-sm text-gray-600">
                    <li v-for="log in activityLogs" :key="log.id">
                        <span class="font-medium text-gray-900">{{ log.user?.name ?? 'Unknown' }}</span>
                        {{ log.action }}
                        <span v-if="log.old_status && log.new_status">({{ log.old_status }} → {{ log.new_status }})</span>
                        <span class="text-gray-400">— {{ new Date(log.created_at).toLocaleString() }}</span>
                        <p v-if="log.reason" class="text-xs text-gray-500 italic">Reason: {{ log.reason }}</p>
                    </li>
                </ul>
            </details>
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
