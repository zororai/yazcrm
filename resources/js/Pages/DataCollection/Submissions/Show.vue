<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ submission: Object, canEdit: Boolean });

const answers = reactive({ ...(props.submission.answers ?? {}) });
const processing = ref(false);

function saveDraft() {
    processing.value = true;
    router.put(`/data-collection/submissions/${props.submission.id}`, { answers: { ...answers } }, {
        preserveScroll: true,
        onFinish: () => { processing.value = false; },
    });
}

function submitForm() {
    if (!confirm('Submit this form? You will not be able to edit it afterwards.')) return;
    processing.value = true;
    router.put(`/data-collection/submissions/${props.submission.id}`, { answers: { ...answers } }, {
        preserveScroll: true,
        onSuccess: () => router.post(`/data-collection/submissions/${props.submission.id}/submit`, {}, {
            onFinish: () => { processing.value = false; },
        }),
        onError: () => { processing.value = false; },
    });
}

function toggleMultiple(id, value) {
    const current = Array.isArray(answers[id]) ? [...answers[id]] : [];
    const i = current.indexOf(value);
    if (i === -1) current.push(value); else current.splice(i, 1);
    answers[id] = current;
}
</script>

<template>
    <AppLayout>
        <template #title>{{ submission.form?.name }}</template>
        <template #subtitle>{{ submission.form_version?.schema?.title }} — v{{ submission.form_version?.version_number }}</template>
        <template #header-actions>
            <div class="flex gap-2" v-if="canEdit">
                <button @click="saveDraft" class="btn-secondary btn-sm" :disabled="processing">Save Draft</button>
                <button @click="submitForm" class="btn-primary btn-sm" :disabled="processing">Submit</button>
            </div>
            <span v-else class="badge bg-green-100 text-green-800">{{ submission.status }}</span>
        </template>

        <div class="space-y-4 max-w-3xl">
            <div v-for="section in submission.form_version?.schema?.sections ?? []" :key="section.id" class="card">
                <h3 class="font-semibold text-gray-900 mb-4">{{ section.title }}</h3>

                <div v-for="q in section.questions" :key="q.id" class="mb-4">
                    <label class="label">{{ q.label }} <span v-if="q.required" class="text-red-500">*</span></label>

                    <input v-if="['text','email','phone'].includes(q.type)" v-model="answers[q.id]" type="text" class="input" :disabled="!canEdit" />
                    <textarea v-else-if="q.type === 'long_text'" v-model="answers[q.id]" class="input" rows="3" :disabled="!canEdit"></textarea>
                    <input v-else-if="['number','decimal','integer'].includes(q.type)" v-model="answers[q.id]" type="number" class="input" :disabled="!canEdit" />
                    <input v-else-if="q.type === 'date'" v-model="answers[q.id]" type="date" class="input" :disabled="!canEdit" />
                    <input v-else-if="q.type === 'datetime'" v-model="answers[q.id]" type="datetime-local" class="input" :disabled="!canEdit" />
                    <input v-else-if="q.type === 'time'" v-model="answers[q.id]" type="time" class="input" :disabled="!canEdit" />

                    <select v-else-if="q.type === 'select_one'" v-model="answers[q.id]" class="input" :disabled="!canEdit">
                        <option value="" disabled>Select…</option>
                        <option v-for="opt in q.options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>

                    <div v-else-if="q.type === 'select_multiple'" class="space-y-1">
                        <label v-for="opt in q.options" :key="opt.value" class="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                :checked="(answers[q.id] ?? []).includes(opt.value)"
                                @change="toggleMultiple(q.id, opt.value)"
                                :disabled="!canEdit"
                            />
                            {{ opt.label }}
                        </label>
                    </div>

                    <select v-else-if="q.type === 'yes_no'" v-model="answers[q.id]" class="input" :disabled="!canEdit">
                        <option value="" disabled>Select…</option>
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>
                </div>
            </div>

            <p v-if="!submission.form_version?.schema?.sections?.length" class="text-sm text-gray-400 text-center py-8">
                This form version has no questions.
            </p>
        </div>
    </AppLayout>
</template>
