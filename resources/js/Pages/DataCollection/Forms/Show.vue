<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormTabs from '@/Components/DataCollection/FormTabs.vue';
import { formatDateTime } from '@/Components/DataCollection/answers';
import {
    ArrowUpTrayIcon, ArrowDownTrayIcon, RocketLaunchIcon, ExclamationTriangleIcon, ArchiveBoxIcon, TrashIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    form: Object,
    versions: Array,
    preview: [Object, Array],
    assignments: Array,
    users: Array,
    roles: Array,
    presets: Object,
    activity: Array,
    can: Object,
});

const statusColor = {
    draft: 'bg-gray-100 text-gray-700',
    deployed: 'bg-green-100 text-green-800',
    archived: 'bg-gray-200 text-gray-500',
};

// ── XLSForm upload ──────────────────────────────────────────────────────────
const upload = useForm({ xlsform: null });
const fileInput = ref(null);
function submitUpload() {
    upload.post(`/data-collection/forms/${props.form.id}/xlsform`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { upload.reset(); if (fileInput.value) fileInput.value.value = ''; },
    });
}

const latest = computed(() => props.versions[0] ?? null);
const draft = computed(() => (latest.value?.status === 'draft' ? latest.value : null));

function deploy(version) {
    if (!confirm(`Deploy version ${version.version_number}? Devices will download it on their next sync; the current version stays valid for data already collected on it.`)) return;
    router.post(`/data-collection/forms/${props.form.id}/versions/${version.id}/deploy`, {}, { preserveScroll: true });
}

function setArchived(archived) {
    const message = archived
        ? 'Archive this form? It disappears from the app; data already collected can still sync.'
        : 'Restore this form so it appears in the app again?';
    if (confirm(message)) router.post(`/data-collection/forms/${props.form.id}/archive`, { archived }, { preserveScroll: true });
}

function destroyForm() {
    if (confirm('Delete this form? Only possible while it has no submissions.')) router.delete(`/data-collection/forms/${props.form.id}`);
}

// ── Access ──────────────────────────────────────────────────────────────────
const PERMISSION_LABELS = { collect: 'Collect (app)', view: 'View data', edit: 'Edit & validate', export: 'Export', map: 'CRM mapping' };
const access = useForm({ user_ids: [], crm_role: '', permissions: ['collect'] });
const userSearch = ref('');
const matchingUsers = computed(() => {
    const q = userSearch.value.trim().toLowerCase();
    const taken = new Set(access.user_ids);
    return props.users.filter(u => !taken.has(u.id) && (!q || u.name.toLowerCase().includes(q))).slice(0, 8);
});
const pickedUsers = computed(() => props.users.filter(u => access.user_ids.includes(u.id)));

function applyPreset(name) {
    access.permissions = [...props.presets[name]];
}

function submitAccess() {
    access.post(`/data-collection/forms/${props.form.id}/assignments`, {
        preserveScroll: true,
        onSuccess: () => { access.reset(); userSearch.value = ''; },
    });
}

function removeAccess(a) {
    if (confirm(`Remove ${a.user?.name ?? `role "${a.crm_role}"`} from this form?`)) {
        router.delete(`/data-collection/forms/${props.form.id}/assignments/${a.id}`, { preserveScroll: true });
    }
}

const TYPE_LABELS = {
    text: 'Text', integer: 'Whole number', decimal: 'Decimal', select_one: 'Select one', select_multiple: 'Select many',
    date: 'Date', time: 'Time', datetime: 'Date & time', geopoint: 'Location', photo: 'Photo', note: 'Note',
    calculate: 'Calculation', group: 'Group', repeat: 'Repeat',
};
</script>

<template>
    <AppLayout>
        <template #title>{{ form.name }}</template>
        <template #header-actions>
            <div v-if="can.design" class="flex gap-2">
                <button v-if="form.status !== 'archived'" class="btn-secondary btn-sm" @click="setArchived(true)"><ArchiveBoxIcon class="h-4 w-4" /> Archive</button>
                <button v-else class="btn-secondary btn-sm" @click="setArchived(false)">Restore</button>
                <button v-if="!form.submissions_count" class="btn-danger btn-sm" @click="destroyForm"><TrashIcon class="h-4 w-4" /> Delete</button>
            </div>
        </template>

        <FormTabs :form="form" :can="can" active="overview" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 space-y-4">
                <div class="card">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs text-gray-500">Code {{ form.code }} · {{ form.submissions_count }} submissions</p>
                            <p v-if="form.description" class="text-sm text-gray-700 mt-1">{{ form.description }}</p>
                        </div>
                        <span :class="['badge', statusColor[form.status]]">{{ form.status }}</span>
                    </div>
                </div>

                <!-- Upload -->
                <div v-if="can.design" class="card">
                    <h3 class="font-semibold text-gray-900 mb-1">Upload an XLSForm</h3>
                    <p class="text-xs text-gray-500 mb-3">
                        An .xlsx file with <b>survey</b>, <b>choices</b> and <b>settings</b> sheets. Uploading creates a new draft version
                        (or replaces the current draft); deploy it to send it to the app.
                        Add a <b>pii</b> column (yes) to mark personal data.
                    </p>
                    <form class="flex flex-wrap items-center gap-3" @submit.prevent="submitUpload">
                        <input ref="fileInput" type="file" accept=".xlsx" class="text-sm" @change="upload.xlsform = $event.target.files[0]" />
                        <button type="submit" class="btn-primary btn-sm" :disabled="!upload.xlsform || upload.processing">
                            <ArrowUpTrayIcon class="h-4 w-4" /> {{ upload.processing ? 'Checking…' : 'Upload' }}
                        </button>
                        <a href="/data-collection/xlsform-template.xlsx" class="text-sm text-brand-600 hover:underline">Blank template</a>
                        <a href="/data-collection/xlsform-sample.xlsx" class="text-sm text-brand-600 hover:underline">Sample household survey</a>
                    </form>
                    <p v-if="upload.errors.xlsform" class="text-sm text-red-600 mt-2">{{ upload.errors.xlsform }}</p>
                </div>

                <!-- Errors of the draft -->
                <div v-if="draft && draft.errors.length" class="card border-red-200">
                    <h3 class="font-semibold text-red-700 flex items-center gap-2 mb-2">
                        <ExclamationTriangleIcon class="h-5 w-5" /> Version {{ draft.version_number }} has {{ draft.errors.length }} problem(s)
                    </h3>
                    <p class="text-xs text-gray-500 mb-3">It can't be deployed until these are fixed in the spreadsheet and it's uploaded again.</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50"><tr>
                                <th class="table-th">Sheet</th><th class="table-th">Row</th><th class="table-th">Column</th><th class="table-th">Problem</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="(e, i) in draft.errors" :key="i">
                                    <td class="table-td">{{ e.sheet }}</td>
                                    <td class="table-td">{{ e.row ?? '—' }}</td>
                                    <td class="table-td font-mono text-xs">{{ e.column ?? '—' }}</td>
                                    <td class="table-td">{{ e.message }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Versions -->
                <div class="card p-0 overflow-hidden">
                    <div class="px-6 pt-5 pb-3"><h3 class="font-semibold text-gray-900">Versions</h3></div>
                    <table class="w-full">
                        <thead class="bg-gray-50 border-y border-gray-100"><tr>
                            <th class="table-th">Version</th><th class="table-th">Status</th><th class="table-th">Questions</th>
                            <th class="table-th">Submissions</th><th class="table-th">Deployed</th><th class="table-th"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="v in versions" :key="v.id">
                                <td class="table-td">
                                    <span class="font-medium">v{{ v.version_number }}</span>
                                    <span v-if="v.version_label" class="text-xs text-gray-400"> ({{ v.version_label }})</span>
                                    <p class="text-xs text-gray-400">{{ v.source_name }}</p>
                                </td>
                                <td class="table-td">
                                    <span :class="['badge', statusColor[v.status]]">{{ v.status }}</span>
                                    <span v-if="v.errors.length" class="badge bg-red-100 text-red-700 ml-1">{{ v.errors.length }} errors</span>
                                </td>
                                <td class="table-td">{{ v.questions }}</td>
                                <td class="table-td">{{ v.submissions }}</td>
                                <td class="table-td text-xs text-gray-500">{{ v.deployed_at ? `${formatDateTime(v.deployed_at)} by ${v.deployed_by}` : '—' }}</td>
                                <td class="table-td text-right whitespace-nowrap">
                                    <a v-if="can.design && v.source_name" :href="`/data-collection/forms/${form.id}/versions/${v.id}/xlsform`" class="text-gray-400 hover:text-gray-700 mr-2" title="Download the uploaded XLSForm">
                                        <ArrowDownTrayIcon class="h-4 w-4 inline" />
                                    </a>
                                    <button v-if="can.design && v.status === 'draft' && !v.errors.length" class="btn-primary btn-sm" @click="deploy(v)">
                                        <RocketLaunchIcon class="h-4 w-4" /> Deploy
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!versions.length">
                                <td colspan="6" class="table-td text-center text-gray-400 py-6">No XLSForm uploaded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Question preview -->
                <div v-if="preview && preview.rows" class="card">
                    <h3 class="font-semibold text-gray-900 mb-3">Questions (v{{ preview.version }})</h3>
                    <div class="divide-y divide-gray-50 text-sm">
                        <div v-for="(q, i) in preview.rows" :key="i" class="py-1.5 flex items-start gap-3" :style="{ paddingLeft: `${q.depth * 20}px` }">
                            <span class="w-28 shrink-0 text-xs text-gray-400">{{ TYPE_LABELS[q.type] ?? q.type }}</span>
                            <div class="flex-1">
                                <span :class="['group', 'repeat'].includes(q.type) ? 'font-semibold' : ''">{{ q.label || q.name }}</span>
                                <span v-if="q.required" class="text-red-500"> *</span>
                                <span v-if="q.pii" class="badge bg-purple-100 text-purple-700 ml-1">PII</span>
                                <p v-if="q.relevant" class="text-xs text-gray-400 font-mono">shown if {{ q.relevant }}</p>
                            </div>
                            <span class="text-xs text-gray-300 font-mono">{{ q.name }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Access -->
            <div class="space-y-4">
                <div v-if="can.design" class="card">
                    <h3 class="font-semibold text-gray-900 mb-1">Who can use this form</h3>
                    <p class="text-xs text-gray-500 mb-3">Enumerators collect in the app; analysts and viewers work with the data here.</p>

                    <form class="space-y-3" @submit.prevent="submitAccess">
                        <div>
                            <label class="label">People</label>
                            <input v-model="userSearch" class="input" placeholder="Search staff…" />
                            <div v-if="userSearch" class="border border-gray-100 rounded-lg mt-1 max-h-40 overflow-y-auto">
                                <button v-for="u in matchingUsers" :key="u.id" type="button" class="block w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50"
                                    @click="access.user_ids.push(u.id); userSearch = ''">
                                    {{ u.name }} <span class="text-xs text-gray-400">{{ u.role }}</span>
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-1 mt-2">
                                <span v-for="u in pickedUsers" :key="u.id" class="badge bg-gray-100 text-gray-700">
                                    {{ u.name }}
                                    <button type="button" class="ml-1" @click="access.user_ids = access.user_ids.filter(id => id !== u.id)">×</button>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="label">…or everyone with the CRM role</label>
                            <select v-model="access.crm_role" class="input">
                                <option value="">No role</option>
                                <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Role on this form</label>
                            <div class="flex flex-wrap gap-1 mb-2">
                                <button v-for="(perms, name) in presets" :key="name" type="button" class="btn-secondary btn-sm capitalize" @click="applyPreset(name)">{{ name }}</button>
                            </div>
                            <label v-for="(label, key) in PERMISSION_LABELS" :key="key" class="flex items-center gap-2 text-sm">
                                <input v-model="access.permissions" type="checkbox" :value="key" /> {{ label }}
                            </label>
                        </div>
                        <button type="submit" class="btn-primary btn-sm w-full justify-center" :disabled="access.processing">Give access</button>
                    </form>

                    <ul class="mt-4 divide-y divide-gray-50 text-sm">
                        <li v-for="a in assignments" :key="a.id" class="py-2 flex items-start justify-between gap-2">
                            <div>
                                <p class="font-medium">{{ a.user?.name ?? `Role: ${a.crm_role}` }}</p>
                                <p class="text-xs text-gray-500">{{ a.permissions.map(p => PERMISSION_LABELS[p] ?? p).join(', ') }}</p>
                            </div>
                            <button class="text-gray-400 hover:text-red-600" @click="removeAccess(a)"><TrashIcon class="h-4 w-4" /></button>
                        </li>
                        <li v-if="!assignments.length" class="py-2 text-gray-400">Nobody is assigned yet.</li>
                    </ul>
                </div>

                <div v-else class="card text-sm text-gray-600">
                    <p class="font-medium text-gray-900 mb-1">Your access</p>
                    {{ can.permissions.map(p => PERMISSION_LABELS[p] ?? p).join(', ') }}
                </div>

                <div v-if="activity.length" class="card">
                    <h3 class="font-semibold text-gray-900 mb-2">History</h3>
                    <ul class="space-y-1.5 text-xs text-gray-600">
                        <li v-for="log in activity" :key="log.id">
                            <span class="font-medium text-gray-800">{{ log.user?.name ?? 'Someone' }}</span>
                            {{ log.action.replaceAll('_', ' ') }}
                            <span class="text-gray-400">— {{ formatDateTime(log.created_at) }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
