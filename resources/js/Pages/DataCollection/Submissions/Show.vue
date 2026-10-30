<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { STATUS_COLORS, STATUS_LABELS, formatDateTime } from '@/Components/DataCollection/answers';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    submission: Object, schema: Object, hidden: Array, media: Array, audits: Array, statuses: Array, can: Object, editable: Array,
});

const s = computed(() => props.submission);
const choices = computed(() => props.schema.choices ?? {});
const mediaFor = (field, filename) => props.media.find(m => m.field_name === field && m.filename === filename);

function label(value) {
    if (value && typeof value === 'object') return value.default ?? Object.values(value)[0] ?? '';
    return value ?? '';
}
function choiceLabel(node, name) {
    const list = node.choices ?? choices.value[node.list_name] ?? [];
    return label(list.find(c => String(c.name) === String(name))?.label) || name;
}
function display(node, value) {
    if (value === undefined || value === null || value === '') return '—';
    if (value === '•••') return value;
    if (node.type === 'select_one') return choiceLabel(node, value);
    if (node.type === 'select_multiple' && Array.isArray(value)) return value.map(v => choiceLabel(node, v)).join(', ');
    if (node.type === 'geopoint' && typeof value === 'object') return `${value.lat}, ${value.lng}` + (value.accuracy != null ? ` (±${Math.round(value.accuracy)} m)` : '');
    return String(value);
}

// Flatten schema + answers into rows to show, groups as headings and repeats
// as numbered entries.
const rows = computed(() => {
    const out = [];
    const walk = (nodes, data, depth, prefix) => {
        for (const node of nodes) {
            if (node.type === 'group') {
                out.push({ heading: label(node.label) || node.name, depth });
                walk(node.children ?? [], data, depth + 1, prefix);
            } else if (node.type === 'repeat') {
                const entries = Array.isArray(data[node.name]) ? data[node.name] : [];
                out.push({ heading: `${label(node.label) || node.name} (${entries.length})`, depth });
                entries.forEach((entry, i) => {
                    out.push({ heading: `#${i + 1}`, depth: depth + 1, minor: true });
                    walk(node.children ?? [], entry ?? {}, depth + 2, `${prefix}${node.name}/`);
                });
            } else if (node.type !== 'note') {
                out.push({ node, depth, value: data[node.name], answered: node.name in data, path: prefix + node.name });
            }
        }
    };
    walk(props.schema.children ?? [], s.value.data ?? {}, 0, '');
    return out;
});

// ── Validation status ──────────────────────────────────────────────────────
const statusForm = useForm({ status: s.value.status, note: s.value.status_note ?? '' });
function saveStatus() {
    statusForm.post(`/data-collection/submissions/${s.value.id}/status`, { preserveScroll: true });
}

// ── Editing ────────────────────────────────────────────────────────────────
const editing = ref(false);
const editForm = useForm({ changes: {}, note: '' });
function startEdit() {
    editForm.changes = Object.fromEntries(props.editable.map(f => [f.name, s.value.data?.[f.name] ?? (f.type === 'select_multiple' ? [] : '')]));
    editing.value = true;
}
function saveEdit() {
    // Inputs give strings; send numbers as numbers so validation matches the app.
    const changes = {};
    for (const f of props.editable) {
        let v = editForm.changes[f.name];
        if ((f.type === 'integer' || f.type === 'decimal') && v !== '' && v !== null) v = Number(v);
        if (f.type === 'select_multiple' && Array.isArray(v) && !v.length) v = null;
        if (v === '') v = null;
        const before = s.value.data?.[f.name] ?? null;
        if (JSON.stringify(before) !== JSON.stringify(v)) changes[f.name] = v;
    }
    editForm.transform(data => ({ ...data, changes })).put(`/data-collection/submissions/${s.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editing.value = false; },
    });
}

function destroy() {
    const reason = prompt('Why is this submission being deleted? (It can be restored later.)');
    if (reason) router.delete(`/data-collection/submissions/${s.value.id}`, { data: { reason } });
}
function restore() {
    router.post(`/data-collection/submissions/${s.value.id}/restore`);
}

const ACTIONS = { received: 'Received from the app', edited: 'Edited answers', status_changed: 'Changed validation status', deleted: 'Deleted', restored: 'Restored', mapped: 'Mapped to the CRM' };
</script>

<template>
    <AppLayout>
        <template #title>Submission #{{ s.id }}</template>
        <template #header-actions>
            <div class="flex gap-2">
                <button v-if="can.edit && !editing" class="btn-secondary btn-sm" @click="startEdit"><PencilSquareIcon class="h-4 w-4" /> Edit answers</button>
                <button v-if="can.delete && !s.deleted_at" class="btn-danger btn-sm" @click="destroy"><TrashIcon class="h-4 w-4" /> Delete</button>
                <button v-if="can.delete && s.deleted_at" class="btn-secondary btn-sm" @click="restore">Restore</button>
            </div>
        </template>

        <p class="text-xs text-gray-500 mb-3">
            <Link href="/data-collection" class="hover:underline">Data Collection</Link>
            <template v-if="s.form.project"> / <Link :href="`/data-collection/projects/${s.form.project.id}`" class="hover:underline">{{ s.form.project.name }}</Link></template>
            / <Link :href="`/data-collection/forms/${s.form.id}/data`" class="hover:underline">{{ s.form.name }}</Link>
        </p>

        <div v-if="s.deleted_at" class="card mb-4 bg-red-50 border-red-200 text-sm text-red-700">Deleted {{ formatDateTime(s.deleted_at) }}. Kept for the audit trail.</div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 space-y-4">
                <!-- Edit -->
                <form v-if="editing" class="card space-y-3" @submit.prevent="saveEdit">
                    <h3 class="font-semibold text-gray-900">Edit answers</h3>
                    <p class="text-xs text-gray-500">Checked against form version {{ s.version }}. Every change is recorded in the history.</p>
                    <div v-for="f in editable" :key="f.name">
                        <label class="label">{{ f.label }}</label>
                        <select v-if="f.type === 'select_one'" v-model="editForm.changes[f.name]" class="input">
                            <option value="">—</option>
                            <option v-for="c in f.choices" :key="c.name" :value="c.name">{{ c.label }}</option>
                        </select>
                        <div v-else-if="f.type === 'select_multiple'" class="flex flex-wrap gap-3">
                            <label v-for="c in f.choices" :key="c.name" class="flex items-center gap-1 text-sm">
                                <input v-model="editForm.changes[f.name]" type="checkbox" :value="c.name" /> {{ c.label }}
                            </label>
                        </div>
                        <input v-else v-model="editForm.changes[f.name]" class="input"
                            :type="{ integer: 'number', decimal: 'number', date: 'date' }[f.type] ?? 'text'"
                            :step="f.type === 'decimal' ? 'any' : undefined" />
                        <p v-if="editForm.errors[`changes.${f.name}`]" class="text-xs text-red-600 mt-1">{{ editForm.errors[`changes.${f.name}`] }}</p>
                    </div>
                    <div>
                        <label class="label">Reason for the change</label>
                        <input v-model="editForm.note" class="input" />
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary btn-sm" :disabled="editForm.processing">Save</button>
                        <button type="button" class="btn-secondary btn-sm" @click="editing = false">Cancel</button>
                    </div>
                </form>

                <!-- Answers -->
                <div class="card">
                    <h3 class="font-semibold text-gray-900 mb-3">Answers</h3>
                    <div class="divide-y divide-gray-50 text-sm">
                        <template v-for="(row, i) in rows" :key="i">
                            <div v-if="row.heading" :class="['py-2', row.minor ? 'text-xs text-gray-400' : 'font-semibold text-gray-800']" :style="{ paddingLeft: `${row.depth * 16}px` }">{{ row.heading }}</div>
                            <div v-else class="py-2 grid grid-cols-5 gap-3" :style="{ paddingLeft: `${row.depth * 16}px` }">
                                <span class="col-span-2 text-gray-500">{{ label(row.node.label) || row.node.name }}</span>
                                <span class="col-span-3 text-gray-900">
                                    <template v-if="row.node.type === 'photo' && row.value && mediaFor(row.node.name, row.value)?.url">
                                        <a :href="mediaFor(row.node.name, row.value).url" target="_blank">
                                            <img :src="mediaFor(row.node.name, row.value).url" class="max-h-48 rounded border" :alt="row.value" />
                                        </a>
                                    </template>
                                    <template v-else-if="row.node.type === 'photo' && row.value && row.value !== '•••'">
                                        {{ row.value }} <span class="text-xs text-amber-600">(not uploaded yet)</span>
                                    </template>
                                    <template v-else>{{ display(row.node, row.value) }}</template>
                                </span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="card text-sm space-y-1.5">
                    <p><span class="text-gray-500">Form:</span> {{ s.form.name }} (v{{ s.version }})</p>
                    <p><span class="text-gray-500">Enumerator:</span> {{ s.submitted_by }}</p>
                    <p><span class="text-gray-500">Collected:</span> {{ formatDateTime(s.collected_at) }}</p>
                    <p><span class="text-gray-500">Received:</span> {{ formatDateTime(s.received_at) }}</p>
                    <p v-if="s.lat"><span class="text-gray-500">Device location:</span> {{ s.lat }}, {{ s.lng }}</p>
                    <p><span class="text-gray-500">Completion:</span> {{ s.completion ?? '—' }}%</p>
                    <p><span class="text-gray-500">Device / app:</span> {{ s.device_id ?? '—' }} / {{ s.app_version ?? '—' }}</p>
                    <p class="text-xs text-gray-400 font-mono break-all">{{ s.client_uuid }}</p>
                    <p v-if="s.crm_record"><span class="text-gray-500">CRM record:</span> {{ s.crm_record.type }} #{{ s.crm_record.id }}</p>
                    <p v-if="!can.pii" class="text-xs text-purple-600">Personal data is hidden for your role.</p>
                </div>

                <div class="card">
                    <h3 class="font-semibold text-gray-900 mb-2">Validation status</h3>
                    <span :class="['badge', STATUS_COLORS[s.status]]">{{ STATUS_LABELS[s.status] }}</span>
                    <p v-if="s.status_by" class="text-xs text-gray-500 mt-1">by {{ s.status_by }}, {{ formatDateTime(s.status_at) }}</p>
                    <p v-if="s.status_note" class="text-sm text-gray-700 mt-1 italic">"{{ s.status_note }}"</p>
                    <form v-if="can.edit" class="mt-3 space-y-2" @submit.prevent="saveStatus">
                        <select v-model="statusForm.status" class="input">
                            <option v-for="st in statuses" :key="st" :value="st">{{ STATUS_LABELS[st] }}</option>
                        </select>
                        <input v-model="statusForm.note" class="input" placeholder="Note (optional)" />
                        <button type="submit" class="btn-primary btn-sm" :disabled="statusForm.processing">Save status</button>
                    </form>
                </div>

                <div class="card">
                    <h3 class="font-semibold text-gray-900 mb-2">History</h3>
                    <ul class="space-y-3 text-xs">
                        <li v-for="a in audits" :key="a.id">
                            <p><span class="font-medium text-gray-800">{{ a.user }}</span> · {{ ACTIONS[a.action] ?? a.action }}</p>
                            <p class="text-gray-400">{{ formatDateTime(a.created_at) }}</p>
                            <p v-if="a.note" class="italic text-gray-600">"{{ a.note }}"</p>
                            <ul v-if="a.action === 'edited' && a.new" class="mt-1 text-gray-600">
                                <li v-for="(v, k) in a.new" :key="k"><span class="font-mono">{{ k }}</span>: {{ JSON.stringify(a.old?.[k]) }} → {{ JSON.stringify(v) }}</li>
                            </ul>
                            <p v-if="a.action === 'status_changed'" class="text-gray-600">{{ STATUS_LABELS[a.old?.status] ?? a.old?.status }} → {{ STATUS_LABELS[a.new?.status] ?? a.new?.status }}</p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
