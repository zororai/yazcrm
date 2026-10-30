<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { FunnelIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { STATUS_LABELS, currentQuery, filterQuery } from './answers';

// Date range, enumerator, status, free text and "question = answer"
// filters. Applying keeps the page's other parameters (sort, group-by...).
const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    fields: { type: Array, default: () => [] },
    enumerators: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    showDeleted: { type: Boolean, default: false },
});

const state = reactive({
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    enumerator: props.filters.enumerator ?? '',
    status: props.filters.status ?? '',
    q: props.filters.q ?? '',
    deleted: props.filters.deleted ?? '',
    answers: { ...(props.filters.answers ?? {}) },
});

const answerField = ref('');
const answerValue = ref('');

// Questions with fixed answers make the clearest filters.
const filterable = computed(() => props.fields.filter(f => !f.hidden && ['select_one', 'select_multiple', 'integer', 'text', 'date'].includes(f.type)));
const chosenField = computed(() => props.fields.find(f => f.path === answerField.value));
const fieldLabel = path => props.fields.find(f => f.path === path)?.label ?? path;
const answerLabel = (path, value) => props.fields.find(f => f.path === path)?.choices?.[value] ?? value;

function addAnswer() {
    if (!answerField.value || answerValue.value === '') return;
    state.answers[answerField.value] = answerValue.value;
    answerField.value = '';
    answerValue.value = '';
    apply();
}

function removeAnswer(path) {
    delete state.answers[path];
    apply();
}

function apply() {
    const keep = Object.fromEntries(Object.entries(currentQuery())
        .filter(([k]) => !['from', 'to', 'enumerator', 'status', 'q', 'deleted', 'page'].includes(k) && !k.startsWith('answers[')));
    router.get(window.location.pathname, { ...keep, ...filterQuery(state) }, { preserveState: false, preserveScroll: true });
}

function clear() {
    Object.assign(state, { from: '', to: '', enumerator: '', status: '', q: '', deleted: '', answers: {} });
    apply();
}

const active = computed(() => Object.keys(filterQuery(props.filters)).length > 0);
</script>

<template>
    <div class="card mb-4 p-4">
        <form class="grid grid-cols-2 md:grid-cols-6 gap-3 items-end" @submit.prevent="apply">
            <div>
                <label class="label">From</label>
                <input v-model="state.from" type="date" class="input" />
            </div>
            <div>
                <label class="label">To</label>
                <input v-model="state.to" type="date" class="input" />
            </div>
            <div>
                <label class="label">Enumerator</label>
                <select v-model="state.enumerator" class="input">
                    <option value="">Everyone</option>
                    <option v-for="u in enumerators" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
            </div>
            <div>
                <label class="label">Status</label>
                <select v-model="state.status" class="input">
                    <option value="">Any</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ STATUS_LABELS[s] ?? s }}</option>
                </select>
            </div>
            <div>
                <label class="label">Search answers</label>
                <input v-model="state.q" class="input" placeholder="Text, device, UUID" />
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary btn-sm"><FunnelIcon class="h-4 w-4" /> Apply</button>
                <button v-if="active" type="button" class="btn-secondary btn-sm" @click="clear">Clear</button>
            </div>
        </form>

        <div class="flex flex-wrap items-end gap-2 mt-3">
            <div class="w-56">
                <label class="label text-xs">Filter by an answer</label>
                <select v-model="answerField" class="input py-1.5" @change="answerValue = ''">
                    <option value="">Choose a question…</option>
                    <option v-for="f in filterable" :key="f.path" :value="f.path">{{ f.label }}{{ f.repeat ? ` (${f.repeat})` : '' }}</option>
                </select>
            </div>
            <div v-if="answerField" class="w-48">
                <select v-if="chosenField && Object.keys(chosenField.choices ?? {}).length" v-model="answerValue" class="input py-1.5">
                    <option value="">Choose…</option>
                    <option v-for="(label, name) in chosenField.choices" :key="name" :value="name">{{ label }}</option>
                </select>
                <input v-else v-model="answerValue" :type="chosenField?.type === 'date' ? 'date' : 'text'" class="input py-1.5" placeholder="Exact answer" />
            </div>
            <button v-if="answerField" type="button" class="btn-secondary btn-sm" @click="addAnswer">Add</button>

            <span v-for="(value, path) in state.answers" :key="path" class="badge bg-brand-50 text-brand-700 gap-1">
                {{ fieldLabel(path) }} = {{ answerLabel(path, value) }}
                <button type="button" @click="removeAnswer(path)"><XMarkIcon class="h-3 w-3" /></button>
            </span>

            <label v-if="showDeleted" class="ml-auto flex items-center gap-2 text-xs text-gray-600">
                <input type="checkbox" :checked="state.deleted === 'only'" @change="state.deleted = $event.target.checked ? 'only' : ''; apply()" />
                Show deleted submissions
            </label>
        </div>
    </div>
</template>
