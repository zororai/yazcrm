<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PrinterIcon, ArrowUturnLeftIcon, TrashIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ report: Object, users: Array, can: Object });

const AGE_BRACKETS = ['10-14', '15-19', '20-24', '25+'];

function toDateInput(v) { return v ? String(v).slice(0, 10) : ''; }

function blankAttendance() {
    return Object.fromEntries(AGE_BRACKETS.map(b => [b, {
        male:   props.report.attendance?.[b]?.male ?? '',
        female: props.report.attendance?.[b]?.female ?? '',
    }]));
}

const form = useForm({
    reviewer_id:        props.report.reviewer_id ?? '',
    approver_id:        props.report.approver_id ?? '',
    name_of_activity:   props.report.name_of_activity ?? '',
    date:               toDateInput(props.report.date),
    district:           props.report.district ?? '',
    organized_by:       props.report.organized_by ?? '',
    officer_in_charge:  props.report.officer_in_charge ?? '',
    venue:              props.report.venue ?? '',
    attendance:         blankAttendance(),
    objectives:         props.report.objectives ?? '',
    methodology:        props.report.methodology ?? '',
    narration:          props.report.narration ?? '',
    key_outcomes:       props.report.key_outcomes ?? '',
    challenges:         props.report.challenges ?? '',
    action_items:       props.report.action_items?.length ? [...props.report.action_items] : [],
    pictures_link:      props.report.pictures_link ?? '',
    impact_quotes:      [props.report.impact_quotes?.[0] ?? '', props.report.impact_quotes?.[1] ?? ''],
});

const viewersForm = useForm({
    viewer_ids: props.report.viewer_ids ?? [],
});

const totals = computed(() => {
    let male = 0, female = 0;
    for (const b of AGE_BRACKETS) {
        male   += Number(form.attendance[b].male) || 0;
        female += Number(form.attendance[b].female) || 0;
    }
    return { male, female, total: male + female };
});

function addActionItem() {
    form.action_items.push({ item: '', details: '', deadline: '', responsibility: '' });
}

function removeActionItem(idx) {
    form.action_items.splice(idx, 1);
}

const statusColor = {
    draft:     'bg-gray-100 text-gray-700',
    submitted: 'bg-amber-100 text-amber-800',
    reviewed:  'bg-blue-100 text-blue-800',
    approved:  'bg-green-100 text-green-800',
};

function save() {
    form.put(`/activity-reports/${props.report.id}`);
}

function submitReport() {
    if (!form.reviewer_id) { alert('Choose a reviewer before submitting.'); return; }
    if (!confirm('Submit this report for review? You will not be able to edit it afterwards.')) return;
    form.put(`/activity-reports/${props.report.id}`, {
        onSuccess: () => router.post(`/activity-reports/${props.report.id}/submit`),
    });
}

function markReviewed() {
    if (!confirm('Mark this report as reviewed and forward it to the approver?')) return;
    router.post(`/activity-reports/${props.report.id}/review`);
}

function markApproved() {
    if (!confirm('Approve this activity report?')) return;
    router.post(`/activity-reports/${props.report.id}/approve`);
}

function reopenReport() {
    if (!confirm('Reopen this report for editing?')) return;
    router.post(`/activity-reports/${props.report.id}/reopen`);
}

function deleteReport() {
    if (!confirm('Permanently delete this activity report?')) return;
    router.delete(`/activity-reports/${props.report.id}`, { onSuccess: () => router.visit('/activity-reports') });
}

function toggleViewer(id) {
    const ids = [...viewersForm.viewer_ids];
    const idx = ids.indexOf(id);
    if (idx === -1) ids.push(id);
    else ids.splice(idx, 1);
    viewersForm.viewer_ids = ids;
}

function saveViewers() {
    viewersForm.put(`/activity-reports/${props.report.id}/viewers`);
}

function printDoc() {
    const stamp = new Date().toISOString().slice(0, 10);
    const originalTitle = document.title;
    document.title = `Activity Report - ${props.report.name_of_activity || 'Untitled'} - ${stamp}`;
    window.print();
    setTimeout(() => { document.title = originalTitle; }, 500);
}

const otherUsers = computed(() =>
    props.users.filter(u => u.id !== props.report.compiled_by && u.id !== Number(form.reviewer_id) && u.id !== Number(form.approver_id))
);
</script>

<template>
    <AppLayout>
        <template #title>Activity Report — {{ report.name_of_activity || '(untitled)' }}</template>
        <template #header-actions>
            <div class="flex gap-2 no-print">
                <button @click="printDoc" class="btn-secondary btn-sm"><PrinterIcon class="h-4 w-4" /> Print / Download</button>
                <button v-if="can.manage && report.status !== 'draft'" @click="reopenReport" class="btn-secondary btn-sm">
                    <ArrowUturnLeftIcon class="h-4 w-4" /> Reopen
                </button>
                <button v-if="can.delete" @click="deleteReport" class="btn-danger btn-sm"><TrashIcon class="h-4 w-4" /> Delete</button>
            </div>
        </template>

        <div class="printable space-y-6 max-w-4xl">
            <div class="card">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Youth Advocates — Activity Report</h2>
                    <span :class="['badge', statusColor[report.status]]">{{ report.status }}</span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="label">Name of Activity</label>
                        <input v-model="form.name_of_activity" class="input" :disabled="!can.edit" />
                    </div>
                    <div>
                        <label class="label">Date</label>
                        <input v-model="form.date" type="date" class="input" :disabled="!can.edit" />
                    </div>
                    <div>
                        <label class="label">District</label>
                        <input v-model="form.district" class="input" :disabled="!can.edit" />
                    </div>
                    <div>
                        <label class="label">Organized By</label>
                        <input v-model="form.organized_by" class="input" :disabled="!can.edit" />
                    </div>
                    <div>
                        <label class="label">Officer in Charge of the Activity</label>
                        <input v-model="form.officer_in_charge" class="input" :disabled="!can.edit" />
                    </div>
                    <div class="col-span-2">
                        <label class="label">Venue</label>
                        <input v-model="form.venue" class="input" :disabled="!can.edit" />
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 class="font-semibold text-gray-900 mb-3">Attendance</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 text-xs">
                            <th class="pb-2">Age</th>
                            <th class="pb-2">Males</th>
                            <th class="pb-2">Females</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in AGE_BRACKETS" :key="b" class="border-t border-gray-100">
                            <td class="py-1.5 font-medium">{{ b }}</td>
                            <td class="py-1.5 pr-4"><input v-model="form.attendance[b].male" type="number" min="0" class="input w-24" :disabled="!can.edit" /></td>
                            <td class="py-1.5"><input v-model="form.attendance[b].female" type="number" min="0" class="input w-24" :disabled="!can.edit" /></td>
                        </tr>
                        <tr class="border-t border-gray-200 font-semibold">
                            <td class="py-1.5">Total</td>
                            <td class="py-1.5">{{ totals.male }}</td>
                            <td class="py-1.5">{{ totals.female }} <span class="text-gray-400 font-normal">({{ totals.total }} total)</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card space-y-4">
                <h3 class="font-semibold text-gray-900">Activity Narration</h3>
                <div>
                    <label class="label">Objectives of the Activity (SMART)</label>
                    <textarea v-model="form.objectives" class="input" rows="3" :disabled="!can.edit"></textarea>
                </div>
                <div>
                    <label class="label">Methodology</label>
                    <textarea v-model="form.methodology" class="input" rows="3" :disabled="!can.edit"></textarea>
                </div>
                <div>
                    <label class="label">Activity Narration (What transpired, what was discussed)</label>
                    <textarea v-model="form.narration" class="input" rows="4" :disabled="!can.edit"></textarea>
                </div>
                <div>
                    <label class="label">Key Outcomes of Activity</label>
                    <textarea v-model="form.key_outcomes" class="input" rows="3" :disabled="!can.edit"></textarea>
                </div>
                <div>
                    <label class="label">Challenges, Lessons Learnt and Recommendations</label>
                    <textarea v-model="form.challenges" class="input" rows="3" :disabled="!can.edit"></textarea>
                </div>
            </div>

            <div class="card">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900">Action Table</h3>
                    <button v-if="can.edit" @click="addActionItem" type="button" class="btn-secondary btn-sm no-print">
                        <PlusIcon class="h-3.5 w-3.5" /> Add Row
                    </button>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 text-xs">
                            <th class="pb-2">Action Item</th>
                            <th class="pb-2">Details / Suggestions</th>
                            <th class="pb-2">Deadline</th>
                            <th class="pb-2">Responsibility</th>
                            <th v-if="can.edit" class="pb-2 no-print" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in form.action_items" :key="idx" class="border-t border-gray-100">
                            <td class="py-1.5 pr-2"><input v-model="row.item" class="input" :disabled="!can.edit" /></td>
                            <td class="py-1.5 pr-2"><input v-model="row.details" class="input" :disabled="!can.edit" /></td>
                            <td class="py-1.5 pr-2"><input v-model="row.deadline" type="date" class="input" :disabled="!can.edit" /></td>
                            <td class="py-1.5 pr-2"><input v-model="row.responsibility" class="input" :disabled="!can.edit" /></td>
                            <td v-if="can.edit" class="py-1.5 no-print">
                                <button @click="removeActionItem(idx)" type="button" class="text-gray-400 hover:text-red-600">
                                    <XMarkIcon class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!form.action_items.length">
                            <td colspan="5" class="py-3 text-center text-gray-400 text-xs">No action items yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card space-y-4">
                <h3 class="font-semibold text-gray-900">Activity Pictures</h3>
                <div>
                    <label class="label">Link to at least 5 high-resolution pictures that tell a story</label>
                    <input v-model="form.pictures_link" class="input" placeholder="https://…" :disabled="!can.edit" />
                </div>
                <div>
                    <label class="label">Impact Quotation / Narration / Story #1</label>
                    <textarea v-model="form.impact_quotes[0]" class="input" rows="2" :disabled="!can.edit"></textarea>
                </div>
                <div>
                    <label class="label">Impact Quotation / Narration / Story #2</label>
                    <textarea v-model="form.impact_quotes[1]" class="input" rows="2" :disabled="!can.edit"></textarea>
                </div>
            </div>

            <div class="card space-y-4">
                <h3 class="font-semibold text-gray-900">Sign-off</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label">Reviewer (Head Impact and Programs)</label>
                        <select v-model="form.reviewer_id" class="input" :disabled="!can.edit">
                            <option value="">— Select —</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Approver (Programs Director)</label>
                        <select v-model="form.approver_id" class="input" :disabled="!can.edit">
                            <option value="">— Select —</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 text-sm text-gray-600 pt-2 border-t border-gray-100">
                    <div>
                        <p class="font-medium text-gray-900">Compiled By (Project Officer)</p>
                        <p>{{ report.compiler?.name ?? '—' }}</p>
                        <p class="text-xs text-gray-400">{{ report.compiled_at ? new Date(report.compiled_at).toLocaleString() : 'Not yet submitted' }}</p>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">Reviewed by (Head Impact and Programs)</p>
                        <p>{{ report.reviewer?.name ?? '—' }}</p>
                        <p class="text-xs text-gray-400">{{ report.reviewed_at ? new Date(report.reviewed_at).toLocaleString() : 'Not yet reviewed' }}</p>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">Approved by (Programs Director)</p>
                        <p>{{ report.approver?.name ?? '—' }}</p>
                        <p class="text-xs text-gray-400">{{ report.approved_at ? new Date(report.approved_at).toLocaleString() : 'Not yet approved' }}</p>
                    </div>
                </div>
            </div>

            <div v-if="can.edit || can.review || can.approve" class="flex gap-2 justify-end no-print">
                <button v-if="can.edit" @click="save" class="btn-secondary" :disabled="form.processing">Save Draft</button>
                <button v-if="can.edit && report.status === 'draft'" @click="submitReport" class="btn-primary" :disabled="form.processing">
                    Submit for Review
                </button>
                <button v-if="can.review" @click="markReviewed" class="btn-primary">Mark Reviewed</button>
                <button v-if="can.approve" @click="markApproved" class="btn-primary">Approve Report</button>
            </div>

            <!-- Extra viewers — per-report sharing control -->
            <div v-if="can.manageViewers" class="card no-print">
                <h3 class="font-semibold text-gray-900 mb-1">Viewers</h3>
                <p class="text-xs text-gray-500 mb-3">
                    Give specific people read access to this report, in addition to the compiler, reviewer and approver.
                </p>
                <div class="grid grid-cols-2 gap-2 max-h-56 overflow-y-auto rounded-lg border border-gray-200 p-3 bg-gray-50">
                    <label v-for="u in otherUsers" :key="u.id" class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 py-1">
                        <input type="checkbox" :checked="viewersForm.viewer_ids.includes(u.id)" @change="toggleViewer(u.id)" class="rounded border-gray-300 text-brand-600" />
                        {{ u.name }}
                    </label>
                </div>
                <div class="flex justify-end pt-3">
                    <button @click="saveViewers" class="btn-secondary btn-sm" :disabled="viewersForm.processing">Save Viewers</button>
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
