<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, TrashIcon, ChevronUpIcon, ChevronDownIcon, RectangleStackIcon, UserPlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    form: Object, versions: Array, assignments: { type: Array, default: () => [] },
    submissions: { type: Array, default: () => [] }, users: { type: Array, default: () => [] },
    isManager: Boolean,
});

const QUESTION_TYPES = [
    'text', 'long_text', 'number', 'decimal', 'integer', 'email', 'phone',
    'date', 'datetime', 'time', 'select_one', 'select_multiple', 'yes_no',
];
const OPTION_TYPES = ['select_one', 'select_multiple'];
const CONDITION_OPERATORS = [
    'equals', 'not_equals', 'greater_than', 'less_than',
    'greater_than_or_equal', 'less_than_or_equal', 'contains', 'is_empty', 'is_not_empty',
];

const draftVersion = computed(() => props.versions.find(v => v.status === 'draft'));
const publishedVersion = computed(() => props.versions.find(v => v.status === 'published'));

const schemaForm = useForm({ schema: draftVersion.value ? structuredClone(draftVersion.value.schema) : { title: props.form.name, sections: [] } });

function uid() { return Math.random().toString(36).slice(2, 10); }

function addSection() {
    schemaForm.schema.sections.push({ id: uid(), title: 'New Section', questions: [] });
}
function removeSection(i) {
    schemaForm.schema.sections.splice(i, 1);
}
function moveSection(i, dir) {
    const arr = schemaForm.schema.sections;
    const j = i + dir;
    if (j < 0 || j >= arr.length) return;
    [arr[i], arr[j]] = [arr[j], arr[i]];
}

function addQuestion(section) {
    section.questions.push({ id: uid(), type: 'text', label: '', required: false, options: [] });
}

const allQuestions = computed(() =>
    schemaForm.schema.sections.flatMap(s => s.questions.map(q => ({ id: q.id, label: q.label || q.id })))
);

function toggleCondition(question) {
    question.visible_if = question.visible_if ? null : { question: '', operator: 'equals', value: '' };
}
function removeQuestion(section, i) {
    section.questions.splice(i, 1);
}
function moveQuestion(section, i, dir) {
    const arr = section.questions;
    const j = i + dir;
    if (j < 0 || j >= arr.length) return;
    [arr[i], arr[j]] = [arr[j], arr[i]];
}
function addOption(question) {
    question.options ??= [];
    question.options.push({ value: '', label: '' });
}
function removeOption(question, i) {
    question.options.splice(i, 1);
}

function saveDraft() {
    schemaForm.put(`/data-collection/forms/${props.form.id}/versions/${draftVersion.value.id}`);
}

function publishVersion() {
    if (!confirm('Publish this version? Once published it cannot be edited — you would need to create a new version.')) return;
    router.post(`/data-collection/forms/${props.form.id}/versions/${draftVersion.value.id}/publish`);
}

function createNewVersion() {
    router.post(`/data-collection/forms/${props.form.id}/versions`);
}

const versionStatusColor = {
    draft: 'bg-gray-100 text-gray-700',
    published: 'bg-green-100 text-green-800',
    retired: 'bg-gray-200 text-gray-500',
};

const submissionStatusColor = {
    draft: 'bg-gray-100 text-gray-700',
    submitted: 'bg-amber-100 text-amber-800',
    under_review: 'bg-blue-100 text-blue-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    correction_required: 'bg-orange-100 text-orange-800',
};

const showAssign = ref(false);
const assignForm = useForm({ assigned_to: '', due_date: '' });

function submitAssign() {
    assignForm.post(`/data-collection/forms/${props.form.id}/assignments`, {
        onSuccess: () => { showAssign.value = false; assignForm.reset(); },
    });
}

function openSubmission(s) {
    router.get(`/data-collection/submissions/${s.id}`);
}
</script>

<template>
    <AppLayout>
        <template #title>{{ form.name }}</template>
        <template #header-actions>
            <div class="flex gap-2" v-if="isManager">
                <button v-if="form.current_version_id" @click="showAssign = true" class="btn-secondary btn-sm">
                    <UserPlusIcon class="h-4 w-4" /> Assign
                </button>
                <button v-if="!draftVersion" @click="createNewVersion" class="btn-secondary btn-sm">
                    <RectangleStackIcon class="h-4 w-4" /> New Version
                </button>
            </div>
        </template>

        <div class="card mb-4 flex items-center gap-4 text-sm text-gray-600">
            <span><span class="text-gray-400">Code:</span> {{ form.code }}</span>
            <span><span class="text-gray-400">Project:</span> {{ form.project?.name }}</span>
            <span v-if="publishedVersion">Currently live: <strong>v{{ publishedVersion.version_number }}</strong></span>
        </div>

        <!-- Version history -->
        <div class="card mb-4">
            <h3 class="font-semibold text-gray-900 mb-2 text-sm">Versions</h3>
            <ul class="text-sm space-y-1">
                <li v-for="v in versions" :key="v.id" class="flex items-center gap-2">
                    <span class="font-medium">v{{ v.version_number }}</span>
                    <span :class="['badge', versionStatusColor[v.status]]">{{ v.status }}</span>
                    <span v-if="v.published_at" class="text-xs text-gray-400">
                        published {{ new Date(v.published_at).toLocaleDateString() }} by {{ v.published_by?.name }}
                    </span>
                </li>
            </ul>
        </div>

        <!-- Schema builder for the draft version -->
        <div v-if="draftVersion" class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">Building v{{ draftVersion.version_number }}</h3>
                <div class="flex gap-2" v-if="isManager">
                    <button @click="saveDraft" class="btn-secondary btn-sm" :disabled="schemaForm.processing">Save Draft</button>
                    <button @click="publishVersion" class="btn-primary btn-sm">Publish</button>
                </div>
            </div>

            <div v-for="(section, si) in schemaForm.schema.sections" :key="section.id" class="card">
                <div class="flex items-center gap-2 mb-3">
                    <input v-model="section.title" class="input font-medium flex-1" placeholder="Section title" :disabled="!isManager" />
                    <button @click="moveSection(si, -1)" class="text-gray-400 hover:text-gray-700" :disabled="!isManager"><ChevronUpIcon class="h-4 w-4" /></button>
                    <button @click="moveSection(si, 1)" class="text-gray-400 hover:text-gray-700" :disabled="!isManager"><ChevronDownIcon class="h-4 w-4" /></button>
                    <button @click="removeSection(si)" class="text-gray-400 hover:text-red-600" :disabled="!isManager"><TrashIcon class="h-4 w-4" /></button>
                </div>

                <div v-for="(q, qi) in section.questions" :key="q.id" class="border border-gray-100 rounded-lg p-3 mb-2">
                    <div class="grid grid-cols-[1fr_140px_auto] gap-2 items-start">
                        <div>
                            <label class="label">Label</label>
                            <input v-model="q.label" class="input" placeholder="Question text" :disabled="!isManager" />
                        </div>
                        <div>
                            <label class="label">Type</label>
                            <select v-model="q.type" class="input" :disabled="!isManager">
                                <option v-for="t in QUESTION_TYPES" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>
                        <div class="flex items-end gap-1 pb-0.5">
                            <button @click="moveQuestion(section, qi, -1)" class="text-gray-400 hover:text-gray-700" :disabled="!isManager"><ChevronUpIcon class="h-4 w-4" /></button>
                            <button @click="moveQuestion(section, qi, 1)" class="text-gray-400 hover:text-gray-700" :disabled="!isManager"><ChevronDownIcon class="h-4 w-4" /></button>
                            <button @click="removeQuestion(section, qi)" class="text-gray-400 hover:text-red-600" :disabled="!isManager"><TrashIcon class="h-4 w-4" /></button>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 mt-2">
                        <label class="flex items-center gap-2 text-xs text-gray-600">
                            <input type="checkbox" v-model="q.required" :disabled="!isManager" /> Required
                        </label>
                        <label class="flex items-center gap-2 text-xs text-gray-600">
                            <input type="checkbox" :checked="!!q.visible_if" @change="toggleCondition(q)" :disabled="!isManager" /> Show conditionally
                        </label>
                    </div>

                    <div v-if="q.visible_if" class="mt-2 pl-3 border-l-2 border-blue-100 grid grid-cols-3 gap-2">
                        <div>
                            <label class="label">If question</label>
                            <select v-model="q.visible_if.question" class="input" :disabled="!isManager">
                                <option value="" disabled>Select…</option>
                                <option v-for="oq in allQuestions.filter(o => o.id !== q.id)" :key="oq.id" :value="oq.id">{{ oq.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Operator</label>
                            <select v-model="q.visible_if.operator" class="input" :disabled="!isManager">
                                <option v-for="op in CONDITION_OPERATORS" :key="op" :value="op">{{ op.replace(/_/g, ' ') }}</option>
                            </select>
                        </div>
                        <div v-if="!['is_empty', 'is_not_empty'].includes(q.visible_if.operator)">
                            <label class="label">Value</label>
                            <input v-model="q.visible_if.value" class="input" :disabled="!isManager" />
                        </div>
                    </div>

                    <div v-if="OPTION_TYPES.includes(q.type)" class="mt-2 pl-3 border-l-2 border-gray-100 space-y-1">
                        <div v-for="(opt, oi) in q.options" :key="oi" class="flex gap-2">
                            <input v-model="opt.value" class="input flex-1" placeholder="value" :disabled="!isManager" />
                            <input v-model="opt.label" class="input flex-1" placeholder="label" :disabled="!isManager" />
                            <button @click="removeOption(q, oi)" class="text-gray-400 hover:text-red-600" :disabled="!isManager"><TrashIcon class="h-4 w-4" /></button>
                        </div>
                        <button v-if="isManager" @click="addOption(q)" class="text-xs text-blue-600 hover:underline">+ Add option</button>
                    </div>
                </div>

                <button v-if="isManager" @click="addQuestion(section)" class="text-xs text-blue-600 hover:underline flex items-center gap-1">
                    <PlusIcon class="h-3 w-3" /> Add question
                </button>
            </div>

            <button v-if="isManager" @click="addSection" class="btn-secondary btn-sm">
                <PlusIcon class="h-4 w-4" /> Add Section
            </button>
        </div>

        <div v-else class="card text-sm text-gray-400 text-center py-8">
            This form has no draft version to edit. Click "New Version" to start one.
        </div>

        <div v-if="isManager" class="card mt-4">
            <h3 class="font-semibold text-gray-900 mb-2 text-sm">Assignments</h3>
            <ul class="text-sm divide-y divide-gray-50">
                <li v-for="a in assignments" :key="a.id" class="flex items-center justify-between py-2">
                    <span>{{ a.assignee?.name }} <span class="text-xs text-gray-400">v{{ a.form_version?.version_number }}</span></span>
                    <span class="badge bg-gray-100 text-gray-700">{{ a.status }}</span>
                </li>
                <li v-if="!assignments.length" class="text-gray-400 text-center py-4">Nobody assigned yet.</li>
            </ul>
        </div>

        <div class="card mt-4">
            <h3 class="font-semibold text-gray-900 mb-2 text-sm">{{ isManager ? 'Submissions' : 'My Submissions' }}</h3>
            <ul class="text-sm divide-y divide-gray-50">
                <li v-for="s in submissions" :key="s.id" @click="openSubmission(s)" class="flex items-center justify-between py-2 cursor-pointer hover:bg-gray-50 -mx-2 px-2 rounded">
                    <span>{{ s.submitted_by?.name }} <span class="text-xs text-gray-400">{{ s.completion_percentage }}%</span></span>
                    <span :class="['badge', submissionStatusColor[s.status]]">{{ s.status }}</span>
                </li>
                <li v-if="!submissions.length" class="text-gray-400 text-center py-4">No submissions yet.</li>
            </ul>
        </div>

        <div v-if="showAssign" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Assign Form</h3>
                <form @submit.prevent="submitAssign" class="space-y-3">
                    <div>
                        <label class="label">Assign To</label>
                        <select v-model="assignForm.assigned_to" class="input" required>
                            <option value="" disabled>Select…</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Due Date</label>
                        <input v-model="assignForm.due_date" type="date" class="input" />
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showAssign = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="assignForm.processing">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
