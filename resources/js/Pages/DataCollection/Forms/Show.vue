<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, TrashIcon, ChevronUpIcon, ChevronDownIcon, RectangleStackIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ form: Object, versions: Array, isManager: Boolean });

const QUESTION_TYPES = [
    'text', 'long_text', 'number', 'decimal', 'integer', 'email', 'phone',
    'date', 'datetime', 'time', 'select_one', 'select_multiple', 'yes_no',
];
const OPTION_TYPES = ['select_one', 'select_multiple'];

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
</script>

<template>
    <AppLayout>
        <template #title>{{ form.name }}</template>
        <template #header-actions>
            <div class="flex gap-2" v-if="isManager">
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
                    <label class="flex items-center gap-2 text-xs text-gray-600 mt-2">
                        <input type="checkbox" v-model="q.required" :disabled="!isManager" /> Required
                    </label>

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
    </AppLayout>
</template>
