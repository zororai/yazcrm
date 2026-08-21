<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { PrinterIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ appraisal: Object });

const EMPLOYEE_FIELDS = [
    { key: 'job_description',  label: '1. Job Descriptions/Responsibilities' },
    { key: 'past_activities',  label: '2a. Primary work activities undertaken since the last review period' },
    { key: 'past_strengths',   label: '2b. Strengths demonstrated in completing the above work activities' },
    { key: 'past_improve',     label: '2c. How can you improve your work performance?' },
    { key: 'future_help_org',  label: '3a. What can you do that would help the organization operate more effectively?' },
    { key: 'future_team_help', label: '3b. What can the rest of the team do that would help everyone do a better job?' },
    { key: 'future_goals',     label: '3c. What are your goals for next year, and how do you see yourself growing and developing professionally?' },
    { key: 'future_plans',     label: '3d. Specific plans/actions until next review period' },
];

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

function fmt(v) { return v ? new Date(v).toLocaleDateString() : '—'; }
function fmtDateTime(v) { return v ? new Date(v).toLocaleString() : 'Not yet signed'; }

const statusColor = {
    draft:     'bg-gray-100 text-gray-700',
    submitted: 'bg-amber-100 text-amber-800',
    completed: 'bg-green-100 text-green-800',
};

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
        <template #title>Appraisal Document — {{ appraisal.user?.name }}</template>
        <template #header-actions>
            <button @click="printDoc" class="btn-primary btn-sm no-print">
                <PrinterIcon class="h-4 w-4" /> Print / Download
            </button>
        </template>

        <div class="printable space-y-6 max-w-4xl">
            <div class="card flex items-center justify-between">
                <img src="/logo.png" alt="Youth Advocates" class="h-14" />
                <div class="text-right">
                    <h2 class="text-lg font-semibold text-gray-900">Staff Performance Appraisal</h2>
                    <span :class="['badge', statusColor[appraisal.status]]">{{ appraisal.status }}</span>
                </div>
            </div>

            <div class="card grid grid-cols-2 gap-4">
                <div><label class="label">Employee Name</label><p class="text-sm text-gray-900">{{ appraisal.user?.name }}</p></div>
                <div><label class="label">Supervisor</label><p class="text-sm text-gray-900">{{ appraisal.supervisor?.name ?? '—' }}</p></div>
                <div><label class="label">Job Title</label><p class="text-sm text-gray-900">{{ appraisal.job_title ?? '—' }}</p></div>
                <div><label class="label">Department</label><p class="text-sm text-gray-900">{{ appraisal.department ?? '—' }}</p></div>
                <div><label class="label">Employee Number</label><p class="text-sm text-gray-900">{{ appraisal.employee_number ?? '—' }}</p></div>
                <div><label class="label">Start Date</label><p class="text-sm text-gray-900">{{ fmt(appraisal.start_date) }}</p></div>
                <div><label class="label">Date of Review</label><p class="text-sm text-gray-900">{{ fmt(appraisal.date_of_review) }}</p></div>
                <div><label class="label">Next Review Date</label><p class="text-sm text-gray-900">{{ fmt(appraisal.next_review_date) }}</p></div>
            </div>

            <div class="card">
                <h3 class="font-semibold text-gray-900 mb-3">Section A — Employee Self-Assessment</h3>
                <div class="space-y-3">
                    <div>
                        <label class="label">Overall Comments</label>
                        <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ appraisal.employee_responses?.overall_comments || '—' }}</p>
                    </div>
                    <div v-for="f in EMPLOYEE_FIELDS" :key="f.key">
                        <label class="label">{{ f.label }}</label>
                        <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ appraisal.employee_responses?.[f.key] || '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 class="font-semibold text-gray-900 mb-3">Section B — Supervisor Evaluation</h3>
                <div class="mb-4">
                    <label class="label">Reviewer's Summary Report</label>
                    <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ appraisal.supervisor_responses?.reviewer_summary || '—' }}</p>
                </div>
                <div class="mb-4">
                    <label class="label">Overall Performance Rating</label>
                    <p class="text-sm text-gray-800">{{ appraisal.overall_rating ? `${appraisal.overall_rating} / 5` : '—' }}</p>
                </div>
                <div class="space-y-3 mb-4">
                    <div v-for="f in SUPERVISOR_RATED_FIELDS" :key="f.key" class="grid grid-cols-[1fr_80px] gap-3">
                        <div>
                            <label class="label">{{ f.label }}</label>
                            <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ appraisal.supervisor_responses?.[f.key]?.text || '—' }}</p>
                        </div>
                        <div>
                            <label class="label">Rating</label>
                            <p class="text-sm text-gray-800">{{ appraisal.supervisor_responses?.[f.key]?.rating || '—' }}</p>
                        </div>
                    </div>
                </div>
                <div class="space-y-3">
                    <div v-for="f in SUPERVISOR_TEXT_FIELDS" :key="f.key">
                        <label class="label">{{ f.label }}</label>
                        <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ appraisal.supervisor_responses?.[f.key] || '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="card grid grid-cols-2 gap-4 text-sm text-gray-600">
                <div>
                    <p class="font-medium text-gray-900">Employee's Signature</p>
                    <p>{{ appraisal.user?.name }}</p>
                    <p class="text-xs text-gray-400">{{ fmtDateTime(appraisal.employee_signed_at) }}</p>
                </div>
                <div>
                    <p class="font-medium text-gray-900">Reviewer's Signature</p>
                    <p>{{ appraisal.supervisor?.name ?? '—' }}</p>
                    <p class="text-xs text-gray-400">{{ fmtDateTime(appraisal.supervisor_signed_at) }}</p>
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
}
</style>
