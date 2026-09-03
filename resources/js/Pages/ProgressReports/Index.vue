<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, TrashIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    month: String,
    current: Object,
    history: Array,
    isManager: Boolean,
    supervisorOptions: Array,
});

const month = ref(props.month.slice(0, 7)); // "YYYY-MM" for the <input type="month">

function changeMonth() {
    router.get('/progress-reports', { month: month.value + '-01' }, { preserveState: true, replace: true });
}

function blankActivity() {
    return { activity: '', completed: '', details: '' };
}

const form = useForm({
    month:             props.month,
    job_title:         props.current?.job_title ?? '',
    supervisor:        props.current?.supervisor ?? '',
    date_submitted:    props.current?.date_submitted ?? '',
    overall_progress:  props.current?.overall_progress ?? '',
    activities:        props.current?.activities?.length ? [...props.current.activities] : [blankActivity()],
});

watch(() => props.current, (c) => {
    form.job_title        = c?.job_title ?? '';
    form.supervisor       = c?.supervisor ?? '';
    form.date_submitted   = c?.date_submitted ?? '';
    form.overall_progress = c?.overall_progress ?? '';
    form.activities       = c?.activities?.length ? [...c.activities] : [blankActivity()];
    form.month            = props.month;
});

function addActivityRow() {
    form.activities.push(blankActivity());
}

function removeActivityRow(i) {
    form.activities.splice(i, 1);
    if (!form.activities.length) form.activities.push(blankActivity());
}

function submit() {
    form.post('/progress-reports', { preserveScroll: true });
}

const monthLabel = computed(() => new Date(month.value + '-01T00:00:00').toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));

const statusLabels = {
    pending:         'Pending Review',
    reviewed:        'Reviewed',
    approved:        'Approved',
    needs_revision:  'Needs Revision',
};

const statusColor = {
    pending:        'bg-gray-100 text-gray-600',
    reviewed:       'bg-blue-100 text-blue-800',
    approved:       'bg-green-100 text-green-800',
    needs_revision: 'bg-amber-100 text-amber-800',
};
</script>

<template>
    <AppLayout>
        <template #title>Individual Monthly Progress Report</template>

        <div class="card mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="label">Month</label>
                <input v-model="month" @change="changeMonth" type="month" class="input" />
            </div>
            <p class="text-sm text-gray-500 pb-2">Reporting for <span class="font-medium text-gray-700">{{ monthLabel }}</span></p>
        </div>

        <!-- The report form — mirrors the paper template -->
        <form @submit.prevent="submit" class="card space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Individual Monthly Progress Report</h2>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mt-1">Summary of portfolio details: please insert your KPIs as per contract</p>
                </div>
                <span v-if="current" :class="['badge flex-shrink-0', statusColor[current.status] ?? 'bg-gray-100 text-gray-600']">
                    {{ statusLabels[current.status] ?? current.status }}
                </span>
            </div>

            <div v-if="current?.review_notes" class="rounded-xl bg-amber-50 border border-amber-100 px-3 py-2.5 text-sm text-amber-800">
                <span class="font-medium">Reviewer notes:</span> {{ current.review_notes }}
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label">Job Title</label>
                    <input v-model="form.job_title" class="input" />
                </div>
                <div>
                    <label class="label">Supervisor</label>
                    <select v-model="form.supervisor" class="input">
                        <option value="">— select —</option>
                        <option v-for="u in supervisorOptions" :key="u.id" :value="u.name">{{ u.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Date Submitted</label>
                    <input v-model="form.date_submitted" type="date" class="input" />
                </div>
            </div>

            <div>
                <label class="label">Monthly Overall Progress</label>
                <p class="text-xs text-gray-400 mb-1.5">
                    Describe overall progress vs planned activities and KPIs. Also highlight delays in implementation and causes, change recommendations, etc.
                </p>
                <textarea v-model="form.overall_progress" rows="5" class="input resize-none" />
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="label mb-0">Workplan Activities</label>
                    <button type="button" @click="addActivityRow" class="text-xs text-brand-600 hover:underline inline-flex items-center gap-1">
                        <PlusIcon class="h-3.5 w-3.5" /> Add row
                    </button>
                </div>
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr>
                                <th class="px-3 py-2 text-left">Workplan Activities</th>
                                <th class="px-3 py-2 text-left w-32">Completed</th>
                                <th class="px-3 py-2 text-left">Progress Details</th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(row, i) in form.activities" :key="i">
                                <td class="px-2 py-1.5"><input v-model="row.activity" class="input" /></td>
                                <td class="px-2 py-1.5"><input v-model="row.completed" class="input" placeholder="Yes/No/%" /></td>
                                <td class="px-2 py-1.5"><input v-model="row.details" class="input" /></td>
                                <td class="px-1 text-center">
                                    <button type="button" @click="removeActivityRow(i)" class="text-gray-300 hover:text-red-500">
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button type="button" @click="addActivityRow"
                    class="mt-2 w-full flex items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-gray-200 text-gray-500 hover:border-brand-300 hover:text-brand-600 py-2 text-sm font-medium transition-colors">
                    <PlusIcon class="h-4 w-4" /> Add Activity
                </button>
            </div>

            <div class="flex justify-center">
                <button type="submit" :disabled="form.processing" class="btn-primary">
                    {{ form.processing ? 'Saving…' : 'Save Report' }}
                </button>
            </div>
        </form>

        <!-- My submission history -->
        <div class="card mt-5" v-if="history.length">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">My Reports</h3>
            <div class="flex flex-wrap gap-2">
                <button v-for="h in history" :key="h.id" type="button" @click="month = h.month.slice(0,7); changeMonth()"
                    :class="['px-3 py-1.5 rounded-full text-xs font-medium ring-1 transition-colors inline-flex items-center gap-1.5',
                        h.month.slice(0,7) === month ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-gray-600 ring-gray-200 hover:bg-gray-50']">
                    {{ new Date(h.month + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', year: 'numeric' }) }}
                    <span v-if="h.submitted" class="text-green-500">✓</span>
                    <span :class="['h-1.5 w-1.5 rounded-full',
                        { pending: 'bg-gray-400', reviewed: 'bg-blue-400', approved: 'bg-green-400', needs_revision: 'bg-amber-400' }[h.status] ?? 'bg-gray-400']"
                        :title="statusLabels[h.status]" />
                </button>
            </div>
        </div>

        <!-- Manager: link out to the dedicated Team Reports page -->
        <div class="card mt-5 flex items-center justify-between" v-if="isManager">
            <div class="flex items-center gap-2">
                <DocumentTextIcon class="h-4 w-4 text-gray-300" />
                <span class="text-sm text-gray-700">Reviewing the whole team's reports?</span>
            </div>
            <Link href="/progress-reports/team" class="btn-secondary btn-sm">Open Team Reports</Link>
        </div>
    </AppLayout>
</template>
