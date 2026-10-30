<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormTabs from '@/Components/DataCollection/FormTabs.vue';
import { formatDateTime } from '@/Components/DataCollection/answers';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    form: Object, can: Object, fields: Array, repeats: Array, enumerators: Array, filters: Object, statuses: Array,
    mapping: Object, targets: Object, logs: Object, counts: Object, result: String,
});

const mappingForm = useForm({
    target: props.mapping?.target ?? 'beneficiary',
    mode: props.mapping?.mode ?? 'store_only',
    match_crm_field: props.mapping?.match_crm_field ?? '',
    match_form_field: props.mapping?.match_form_field ?? '',
    field_map: { ...(props.mapping?.field_map ?? {}) },
});

const target = computed(() => props.targets[mappingForm.target]);
const formFields = computed(() => props.fields.filter(f => !f.repeat));

const MODES = {
    store_only: 'Store only: keep the data here, don\'t touch CRM records',
    create: 'Create a new record (a match on the key field is treated as a duplicate)',
    update: 'Update the existing record that matches the key field',
};
const RESULT_COLORS = {
    created: 'bg-green-100 text-green-800', updated: 'bg-blue-100 text-blue-800', duplicate: 'bg-amber-100 text-amber-800',
    skipped: 'bg-gray-100 text-gray-700', failed: 'bg-red-100 text-red-800',
};

function save() {
    mappingForm.put(`/data-collection/forms/${props.form.id}/mapping`, { preserveScroll: true });
}
function rerun(failedOnly) {
    const message = failedOnly ? 'Retry every failed submission?' : 'Re-run the mapping for every submission of this form? Linked records are updated, not duplicated.';
    if (confirm(message)) router.post(`/data-collection/forms/${props.form.id}/mapping/rerun`, { failed_only: failedOnly }, { preserveScroll: true });
}
function retry(log) {
    router.post(`/data-collection/forms/${props.form.id}/mapping/logs/${log.id}/retry`, {}, { preserveScroll: true });
}
function filterResult(result) {
    router.get(window.location.pathname, result ? { result } : {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <template #title>{{ form.name }}: CRM mapping</template>

        <FormTabs :form="form" :can="can" active="mapping" />

        <div class="grid grid-cols-1 xl:grid-cols-5 gap-4">
            <form class="card xl:col-span-2 space-y-4" @submit.prevent="save">
                <div>
                    <h3 class="font-semibold text-gray-900">Map answers onto a CRM record</h3>
                    <p class="text-xs text-gray-500">Runs in the background after each submission is accepted. A failure is logged here and never affects the submission itself.</p>
                </div>
                <div>
                    <label class="label">CRM record</label>
                    <select v-model="mappingForm.target" class="input">
                        <option v-for="(t, key) in targets" :key="key" :value="key">{{ t.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Mode</label>
                    <label v-for="(label, key) in MODES" :key="key" class="flex items-start gap-2 text-sm mb-1">
                        <input v-model="mappingForm.mode" type="radio" :value="key" class="mt-1" /> {{ label }}
                    </label>
                </div>
                <div v-if="mappingForm.mode !== 'store_only' && target.match.length" class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="label">Key field (CRM)</label>
                        <select v-model="mappingForm.match_crm_field" class="input">
                            <option value="">None</option>
                            <option v-for="m in target.match" :key="m" :value="m">{{ target.fields.find(f => f.key === m)?.label ?? m }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Matched with question</label>
                        <select v-model="mappingForm.match_form_field" class="input">
                            <option value="">None</option>
                            <option v-for="f in formFields" :key="f.path" :value="f.path">{{ f.label }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="mappingForm.mode !== 'store_only'">
                    <label class="label">Fields</label>
                    <div class="divide-y divide-gray-50">
                        <div v-for="f in target.fields" :key="f.key" class="grid grid-cols-2 gap-2 items-center py-1.5">
                            <span class="text-sm">
                                {{ f.label }}<span v-if="target.required.includes(f.key)" class="text-red-500"> *</span>
                                <span class="block text-xs text-gray-400">{{ f.type }}</span>
                            </span>
                            <select v-model="mappingForm.field_map[f.key]" class="input py-1.5">
                                <option :value="undefined">Not mapped</option>
                                <option v-for="q in formFields" :key="q.path" :value="q.path">{{ q.label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn-primary btn-sm" :disabled="mappingForm.processing">Save mapping</button>
                    <button v-if="mapping" type="button" class="btn-secondary btn-sm" @click="rerun(false)"><ArrowPathIcon class="h-4 w-4" /> Re-run for all</button>
                </div>
            </form>

            <div class="card xl:col-span-3 p-0 overflow-hidden">
                <div class="px-6 pt-5 pb-3 flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold text-gray-900 mr-auto">Sync log</h3>
                    <button :class="['badge', !result ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700']" @click="filterResult(null)">All</button>
                    <button v-for="(n, r) in counts" :key="r" :class="['badge', result === r ? 'bg-gray-800 text-white' : RESULT_COLORS[r]]" @click="filterResult(r)">{{ r }} {{ n }}</button>
                    <button v-if="counts.failed" class="btn-secondary btn-sm" @click="rerun(true)">Retry failed</button>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50"><tr>
                        <th class="table-th">Submission</th><th class="table-th">Result</th><th class="table-th">CRM record</th>
                        <th class="table-th">Attempts</th><th class="table-th">Last run</th><th class="table-th"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="log in logs.data" :key="log.id">
                            <td class="table-td"><Link :href="`/data-collection/submissions/${log.submission_id}`" class="text-brand-600 hover:underline">#{{ log.submission_id }}</Link></td>
                            <td class="table-td">
                                <span :class="['badge', RESULT_COLORS[log.result]]">{{ log.result }}</span>
                                <p v-if="log.error" class="text-xs text-red-600 mt-1">{{ log.error }}</p>
                            </td>
                            <td class="table-td text-xs">{{ log.crm_record_id ? `${log.crm_record_type} #${log.crm_record_id}` : '—' }}</td>
                            <td class="table-td">{{ log.attempts }}</td>
                            <td class="table-td text-xs whitespace-nowrap">{{ formatDateTime(log.updated_at) }}</td>
                            <td class="table-td text-right">
                                <button v-if="log.result === 'failed'" class="btn-secondary btn-sm" @click="retry(log)">Retry</button>
                            </td>
                        </tr>
                        <tr v-if="!logs.data.length"><td colspan="6" class="table-td text-center text-gray-400 py-8">No mapping runs yet.</td></tr>
                    </tbody>
                </table>
                <div v-if="logs.last_page > 1" class="flex gap-1 p-3">
                    <template v-for="link in logs.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" preserve-scroll :class="['px-3 py-1 rounded border text-sm', link.active ? 'bg-brand-600 text-white' : 'border-gray-200']" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
