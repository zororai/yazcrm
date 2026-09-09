<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, TrashIcon, DocumentTextIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

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

function blankActivity()  { return { activity: '', completed: '', details: '' }; }
function blankKpi()       { return { title: '', description: '' }; }
function blankProvince()  { return { province: '', clients: '' }; }
function blankService()   { return { service: '', clients: '' }; }
function blankStory()     { return { challenge: '', solution: '' }; }

const provinces = [
    'Bulawayo', 'Harare', 'Manicaland', 'Mashonaland Central',
    'Mashonaland East', 'Mashonaland West', 'Masvingo',
    'Matabeleland North', 'Matabeleland South', 'Midlands',
];

const form = useForm({
    month:             props.month,
    job_title:         props.current?.job_title ?? '',
    supervisor:        props.current?.supervisor ?? '',
    date_submitted:    props.current?.date_submitted ?? '',
    overall_progress:  props.current?.overall_progress ?? '',
    kpis:              props.current?.kpis?.length ? [...props.current.kpis] : [blankKpi()],
    provinces:         props.current?.provinces?.length ? [...props.current.provinces] : [blankProvince()],
    male_clients:      props.current?.male_clients ?? '',
    female_clients:    props.current?.female_clients ?? '',
    services:          props.current?.services?.length ? [...props.current.services] : [blankService()],
    activities:        props.current?.activities?.length ? [...props.current.activities] : [blankActivity()],
    success_stories:   props.current?.success_stories?.length ? [...props.current.success_stories] : [blankStory()],
});

watch(() => props.current, (c) => {
    form.job_title        = c?.job_title ?? '';
    form.supervisor       = c?.supervisor ?? '';
    form.date_submitted   = c?.date_submitted ?? '';
    form.overall_progress = c?.overall_progress ?? '';
    form.kpis            = c?.kpis?.length ? [...c.kpis] : [blankKpi()];
    form.provinces       = c?.provinces?.length ? [...c.provinces] : [blankProvince()];
    form.male_clients    = c?.male_clients ?? '';
    form.female_clients  = c?.female_clients ?? '';
    form.services        = c?.services?.length ? [...c.services] : [blankService()];
    form.activities       = c?.activities?.length ? [...c.activities] : [blankActivity()];
    form.success_stories = c?.success_stories?.length ? [...c.success_stories] : [blankStory()];
    form.month            = props.month;
});

function addRow(field, blank) {
    form[field].push(blank());
}
function removeRow(field, blank, i) {
    form[field].splice(i, 1);
    if (!form[field].length) form[field].push(blank());
}

function addActivityRow() { addRow('activities', blankActivity); }
function removeActivityRow(i) { removeRow('activities', blankActivity, i); }

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
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a v-if="current" :href="`/progress-reports/${current.id}/export-pdf`" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                        <ArrowDownTrayIcon class="h-4 w-4" /> PDF
                    </a>
                    <span v-if="current" :class="['badge', statusColor[current.status] ?? 'bg-gray-100 text-gray-600']">
                        {{ statusLabels[current.status] ?? current.status }}
                    </span>
                </div>
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

            <!-- ── KPI sections — Monthly Overall Progress ── -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <label class="label mb-0">Monthly Overall Progress</label>
                        <p class="text-xs text-gray-400">Describe progress against each KPI as per your contract.</p>
                    </div>
                    <button type="button" @click="addRow('kpis', blankKpi)" class="text-xs text-brand-600 hover:underline inline-flex items-center gap-1 flex-shrink-0">
                        <PlusIcon class="h-3.5 w-3.5" /> Add KPI
                    </button>
                </div>
                <div v-for="(kpi, i) in form.kpis" :key="i" class="rounded-xl ring-1 ring-gray-200 p-3 mb-2 relative">
                    <button type="button" @click="removeRow('kpis', blankKpi, i)" class="absolute top-2 right-2 text-gray-300 hover:text-red-500">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                    <input v-model="kpi.title" class="input mb-2 font-semibold" :placeholder="`KPI ${i + 1}: e.g. Helpline management`" />
                    <textarea v-model="kpi.description" rows="4" class="input resize-none" placeholder="Narrative for this KPI…" />
                </div>
            </div>

            <!-- ── Province reach table ── -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="label mb-0">Clients Reached by Province</label>
                    <button type="button" @click="addRow('provinces', blankProvince)" class="text-xs text-brand-600 hover:underline inline-flex items-center gap-1">
                        <PlusIcon class="h-3.5 w-3.5" /> Add row
                    </button>
                </div>
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr><th class="px-3 py-2 text-left">Province</th><th class="px-3 py-2 text-left w-40">Number of Clients</th><th class="w-8"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(row, i) in form.provinces" :key="i">
                                <td class="px-2 py-1.5">
                                    <select v-model="row.province" class="input">
                                        <option value="">— select —</option>
                                        <option v-for="p in provinces" :key="p" :value="p">{{ p }}</option>
                                    </select>
                                </td>
                                <td class="px-2 py-1.5"><input v-model="row.clients" type="number" min="0" class="input" /></td>
                                <td class="px-1 text-center">
                                    <button type="button" @click="removeRow('provinces', blankProvince, i)" class="text-gray-300 hover:text-red-500">
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Gender breakdown ── -->
            <div class="grid grid-cols-2 gap-4 max-w-sm">
                <div>
                    <label class="label">Male Clients</label>
                    <input v-model="form.male_clients" type="number" min="0" class="input" />
                </div>
                <div>
                    <label class="label">Female Clients</label>
                    <input v-model="form.female_clients" type="number" min="0" class="input" />
                </div>
            </div>

            <!-- ── Services requested table ── -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="label mb-0">Services Requested</label>
                    <button type="button" @click="addRow('services', blankService)" class="text-xs text-brand-600 hover:underline inline-flex items-center gap-1">
                        <PlusIcon class="h-3.5 w-3.5" /> Add row
                    </button>
                </div>
                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                            <tr><th class="px-3 py-2 text-left">Service</th><th class="px-3 py-2 text-left w-40">Number of Clients</th><th class="w-8"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(row, i) in form.services" :key="i">
                                <td class="px-2 py-1.5"><input v-model="row.service" class="input" /></td>
                                <td class="px-2 py-1.5"><input v-model="row.clients" type="number" min="0" class="input" /></td>
                                <td class="px-1 text-center">
                                    <button type="button" @click="removeRow('services', blankService, i)" class="text-gray-300 hover:text-red-500">
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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

            <!-- ── Success stories ── -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <label class="label mb-0">Success Stories</label>
                        <p class="text-xs text-gray-400">Case narratives — challenge and how it was resolved.</p>
                    </div>
                    <button type="button" @click="addRow('success_stories', blankStory)" class="text-xs text-brand-600 hover:underline inline-flex items-center gap-1 flex-shrink-0">
                        <PlusIcon class="h-3.5 w-3.5" /> Add story
                    </button>
                </div>
                <div v-for="(s, i) in form.success_stories" :key="i" class="rounded-xl ring-1 ring-gray-200 p-3 mb-2 relative space-y-2">
                    <button type="button" @click="removeRow('success_stories', blankStory, i)" class="absolute top-2 right-2 text-gray-300 hover:text-red-500">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                    <div>
                        <label class="label">{{ i + 1 }}. Challenge</label>
                        <textarea v-model="s.challenge" rows="3" class="input resize-none" placeholder="What was the situation…" />
                    </div>
                    <div>
                        <label class="label">Solution</label>
                        <textarea v-model="s.solution" rows="3" class="input resize-none" placeholder="How it was resolved…" />
                    </div>
                </div>
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
