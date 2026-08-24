<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ submission: Object, canEdit: Boolean, canReview: Boolean, isManager: Boolean });

const answers = reactive({ ...(props.submission.answers ?? {}) });
const processing = ref(false);

// Mirrors App\Support\DataCollection\ConditionEvaluator — keep in sync.
function isVisible(question) {
    const cond = question.visible_if;
    if (!cond) return true;

    const target = answers[cond.question];
    const value = cond.value;
    const isEmpty = v => v === null || v === undefined || v === '' || (Array.isArray(v) && v.length === 0);

    switch (cond.operator) {
        case 'equals': return String(target ?? '') === String(value ?? '');
        case 'not_equals': return String(target ?? '') !== String(value ?? '');
        case 'greater_than': return Number(target) > Number(value);
        case 'less_than': return Number(target) < Number(value);
        case 'greater_than_or_equal': return Number(target) >= Number(value);
        case 'less_than_or_equal': return Number(target) <= Number(value);
        case 'contains': return Array.isArray(target) ? target.includes(value) : String(target ?? '').includes(String(value ?? ''));
        case 'is_empty': return isEmpty(target);
        case 'is_not_empty': return !isEmpty(target);
        default: return true;
    }
}

function startReview() {
    router.post(`/data-collection/submissions/${props.submission.id}/start-review`);
}

function approve() {
    if (!confirm('Approve this submission?')) return;
    router.post(`/data-collection/submissions/${props.submission.id}/approve`);
}

function reject() {
    const comment = prompt('Reason for rejecting this submission:');
    if (!comment) return;
    router.post(`/data-collection/submissions/${props.submission.id}/reject`, { comment });
}

function requestCorrection() {
    const comment = prompt('What needs to be corrected?');
    if (!comment) return;
    router.post(`/data-collection/submissions/${props.submission.id}/request-correction`, { comment });
}

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
            <div class="flex gap-2" v-else-if="canReview">
                <button v-if="submission.status === 'submitted'" @click="startReview" class="btn-secondary btn-sm">Start Review</button>
                <template v-if="submission.status === 'under_review'">
                    <button @click="requestCorrection" class="btn-secondary btn-sm">Request Correction</button>
                    <button @click="reject" class="btn-danger btn-sm">Reject</button>
                    <button @click="approve" class="btn-primary btn-sm">Approve</button>
                </template>
            </div>
            <span v-else class="badge bg-green-100 text-green-800">{{ submission.status.replace('_', ' ') }}</span>
        </template>

        <div v-if="submission.status === 'correction_required' && canEdit" class="card bg-amber-50 border-amber-200 mb-4 text-sm text-amber-800">
            A reviewer requested a correction on this submission. Fix the answers below and submit again.
        </div>

        <div class="space-y-4 max-w-3xl">
            <div v-for="section in submission.form_version?.schema?.sections ?? []" :key="section.id" class="card">
                <h3 class="font-semibold text-gray-900 mb-4">{{ section.title }}</h3>

                <div v-for="q in section.questions" v-show="isVisible(q)" :key="q.id" class="mb-4">
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

            <div v-if="submission.reviews?.length" class="card">
                <h3 class="font-semibold text-gray-900 mb-3 text-sm">Review History</h3>
                <ul class="space-y-2 text-sm">
                    <li v-for="r in submission.reviews" :key="r.id" class="border-b border-gray-50 pb-2 last:border-0">
                        <p><span class="font-medium text-gray-900">{{ r.reviewer?.name }}</span> — {{ r.decision.replace('_', ' ') }}
                            <span class="text-xs text-gray-400">{{ new Date(r.created_at).toLocaleString() }}</span>
                        </p>
                        <p v-if="r.comment" class="text-xs text-gray-500 italic">{{ r.comment }}</p>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
