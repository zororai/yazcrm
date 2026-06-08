<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, XMarkIcon, TrashIcon, PencilIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    risks:   Object,
    assets:  Array,
    filters: Object,
});

const category = ref(props.filters.category ?? '');
const band     = ref(props.filters.band     ?? '');

function apply() {
    router.get('/risk/risks', {
        category: category.value || undefined,
        band:     band.value     || undefined,
    }, { preserveState: true, replace: true });
}
watch([category, band], apply);

// ── Add Risk Modal ──────────────────────────────────────────────────────────
const showAddRisk = ref(false);
const riskForm = useForm({
    asset_id:    '',
    risk_ref:    '',
    category:    'infrastructure',
    description: '',
    cause:       '',
    likelihood:  3,
    impact:      3,
    risk_owner:  '',
});
function storeRisk() {
    riskForm.post('/risk/risks', { onSuccess: () => { showAddRisk.value = false; riskForm.reset(); } });
}

// ── Edit Risk Modal ─────────────────────────────────────────────────────────
const editingRisk = ref(null);
const editForm = useForm({
    asset_id:    '',
    risk_ref:    '',
    category:    '',
    description: '',
    cause:       '',
    likelihood:  1,
    impact:      1,
    risk_owner:  '',
});
function openEditRisk(risk) {
    editingRisk.value = risk;
    editForm.asset_id    = risk.asset_id ?? '';
    editForm.risk_ref    = risk.risk_ref;
    editForm.category    = risk.category;
    editForm.description = risk.description;
    editForm.cause       = risk.cause ?? '';
    editForm.likelihood  = risk.likelihood;
    editForm.impact      = risk.impact;
    editForm.risk_owner  = risk.risk_owner ?? '';
}
function updateRisk() {
    editForm.put(`/risk/risks/${editingRisk.value.id}`, {
        onSuccess: () => { editingRisk.value = null; },
    });
}

function deleteRisk(risk) {
    if (!confirm(`Delete risk "${risk.risk_ref}"? Controls and actions will also be deleted.`)) return;
    router.delete(`/risk/risks/${risk.id}`, { preserveScroll: true });
}

// ── Add Control Panel ───────────────────────────────────────────────────────
const addingControlForRisk = ref(null);
const controlForm = useForm({ risk_id: '', description: '', effectiveness: 'medium' });
function openAddControl(risk) {
    addingControlForRisk.value = risk;
    controlForm.risk_id = risk.id;
    controlForm.description = '';
    controlForm.effectiveness = 'medium';
}
function storeControl() {
    controlForm.post('/risk/controls', {
        onSuccess: () => { addingControlForRisk.value = null; controlForm.reset(); },
    });
}
function deleteControl(controlId) {
    if (!confirm('Delete this control?')) return;
    router.delete(`/risk/controls/${controlId}`, { preserveScroll: true });
}

// ── Add Action Panel ────────────────────────────────────────────────────────
const addingActionForRisk = ref(null);
const actionForm = useForm({ risk_id: '', action_ref: '', description: '', owner: '', target_date: '', status: 'open', priority: 'medium' });
function openAddAction(risk) {
    addingActionForRisk.value = risk;
    actionForm.risk_id = risk.id;
    actionForm.action_ref = '';
    actionForm.description = '';
    actionForm.owner = '';
    actionForm.target_date = '';
    actionForm.status = 'open';
    actionForm.priority = 'medium';
}
function storeAction() {
    actionForm.post('/risk/actions', {
        onSuccess: () => { addingActionForRisk.value = null; actionForm.reset(); },
    });
}

const bandColors = { red: 'bg-red-100 text-red-800', amber: 'bg-yellow-100 text-yellow-800', green: 'bg-green-100 text-green-800' };
function riskBand(score) {
    if (score == null) return 'green';
    if (score >= 15) return 'red';
    if (score >= 7)  return 'amber';
    return 'green';
}

const categoryColors = {
    infrastructure:    'bg-blue-100 text-blue-800',
    software:          'bg-purple-100 text-purple-800',
    data_protection:   'bg-red-100 text-red-800',
    cybersecurity:     'bg-orange-100 text-orange-800',
    continuity:        'bg-yellow-100 text-yellow-800',
    people_process:    'bg-teal-100 text-teal-800',
};
</script>

<template>
    <AppLayout>
        <template #title>Risk Register</template>
        <template #header-actions>
            <Link href="/risk" class="btn-secondary btn-sm">Dashboard</Link>
            <Link href="/risk/actions" class="btn-secondary btn-sm">Actions</Link>
            <button @click="showAddRisk = true" class="btn-primary btn-sm inline-flex items-center gap-1.5">
                <PlusIcon class="h-4 w-4" /> Add Risk
            </button>
        </template>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="label">Category</label>
                    <select v-model="category" class="input w-44">
                        <option value="">All Categories</option>
                        <option value="infrastructure">Infrastructure</option>
                        <option value="software">Software</option>
                        <option value="data_protection">Data Protection</option>
                        <option value="cybersecurity">Cybersecurity</option>
                        <option value="continuity">Continuity</option>
                        <option value="people_process">People &amp; Process</option>
                    </select>
                </div>
                <div>
                    <label class="label">Band</label>
                    <select v-model="band" class="input w-32">
                        <option value="">All</option>
                        <option value="red">Red (&ge;15)</option>
                        <option value="amber">Amber (7–14)</option>
                        <option value="green">Green (&le;6)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Ref</th>
                        <th class="table-th">Asset</th>
                        <th class="table-th">Category</th>
                        <th class="table-th">Description</th>
                        <th class="table-th text-center">L×I=Inh</th>
                        <th class="table-th text-center">Residual</th>
                        <th class="table-th">Band</th>
                        <th class="table-th">Owner</th>
                        <th class="table-th">Controls</th>
                        <th class="table-th w-28"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!risks.data.length">
                        <td colspan="10" class="py-12 text-center text-sm text-gray-400">No risks found.</td>
                    </tr>
                    <template v-for="r in risks.data" :key="r.id">
                        <tr class="hover:bg-gray-50">
                            <td class="table-td font-mono text-xs font-semibold">{{ r.risk_ref }}</td>
                            <td class="table-td text-sm text-gray-600">{{ r.asset?.name ?? '—' }}</td>
                            <td class="table-td">
                                <span :class="['badge', categoryColors[r.category] ?? 'bg-gray-100 text-gray-600']" style="font-size:10px">
                                    {{ r.category?.replace(/_/g, ' ') }}
                                </span>
                            </td>
                            <td class="table-td text-sm max-w-xs">
                                <p class="truncate" :title="r.description">{{ r.description }}</p>
                            </td>
                            <td class="table-td text-center font-mono text-sm">{{ r.likelihood }}×{{ r.impact }}={{ r.inherent_score }}</td>
                            <td class="table-td text-center font-bold">{{ r.residual_score ?? '—' }}</td>
                            <td class="table-td">
                                <span :class="['badge', bandColors[riskBand(r.residual_score)]]">{{ riskBand(r.residual_score) }}</span>
                            </td>
                            <td class="table-td text-sm text-gray-600">{{ r.risk_owner ?? '—' }}</td>
                            <td class="table-td text-xs text-gray-500">{{ r.controls?.length ?? 0 }}</td>
                            <td class="table-td">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <button @click="openEditRisk(r)" class="text-gray-400 hover:text-blue-600" title="Edit">
                                        <PencilIcon class="h-4 w-4" />
                                    </button>
                                    <button @click="deleteRisk(r)" class="text-gray-400 hover:text-red-600" title="Delete">
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                    <button @click="openAddControl(r)" class="text-xs px-1.5 py-0.5 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded" title="Add Control">+Control</button>
                                    <button v-if="!r.priority_action" @click="openAddAction(r)" class="text-xs px-1.5 py-0.5 bg-purple-50 text-purple-700 hover:bg-purple-100 rounded" title="Add Action">+Action</button>
                                </div>
                            </td>
                        </tr>
                        <!-- Controls detail row -->
                        <tr v-if="r.controls?.length" class="bg-gray-50">
                            <td colspan="10" class="px-6 py-2">
                                <div class="flex flex-wrap gap-2">
                                    <div v-for="c in r.controls" :key="c.id"
                                        class="flex items-center gap-2 text-xs bg-white border border-gray-200 rounded-lg px-2 py-1">
                                        <span class="text-gray-700">{{ c.description }}</span>
                                        <span :class="['badge', c.effectiveness === 'high' ? 'bg-green-100 text-green-700' : c.effectiveness === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700']">
                                            {{ c.effectiveness }}
                                        </span>
                                        <button @click="deleteControl(c.id)" class="text-gray-300 hover:text-red-500 ml-1">
                                            <XMarkIcon class="h-3 w-3" />
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <!-- Add Control inline form -->
                        <tr v-if="addingControlForRisk?.id === r.id">
                            <td colspan="10" class="bg-blue-50 px-6 py-3">
                                <div class="flex gap-3 items-end">
                                    <div class="flex-1">
                                        <label class="label">Control Description</label>
                                        <input v-model="controlForm.description" class="input" placeholder="Describe the control…" />
                                    </div>
                                    <div>
                                        <label class="label">Effectiveness</label>
                                        <select v-model="controlForm.effectiveness" class="input w-32">
                                            <option value="low">Low</option>
                                            <option value="medium">Medium</option>
                                            <option value="high">High</option>
                                        </select>
                                    </div>
                                    <button @click="storeControl" :disabled="controlForm.processing" class="btn-primary btn-sm">Add</button>
                                    <button @click="addingControlForRisk = null" class="btn-secondary btn-sm">Cancel</button>
                                </div>
                            </td>
                        </tr>
                        <!-- Add Action inline form -->
                        <tr v-if="addingActionForRisk?.id === r.id">
                            <td colspan="10" class="bg-purple-50 px-6 py-3">
                                <div class="grid grid-cols-3 gap-3 items-end">
                                    <div>
                                        <label class="label">Action Ref</label>
                                        <input v-model="actionForm.action_ref" class="input" placeholder="e.g. ACT-001" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="label">Description</label>
                                        <input v-model="actionForm.description" class="input" />
                                    </div>
                                    <div>
                                        <label class="label">Owner</label>
                                        <input v-model="actionForm.owner" class="input" />
                                    </div>
                                    <div>
                                        <label class="label">Target Date</label>
                                        <input v-model="actionForm.target_date" type="date" class="input" />
                                    </div>
                                    <div>
                                        <label class="label">Priority</label>
                                        <select v-model="actionForm.priority" class="input">
                                            <option value="low">Low</option>
                                            <option value="medium">Medium</option>
                                            <option value="high">High</option>
                                            <option value="critical">Critical</option>
                                        </select>
                                    </div>
                                    <div class="col-span-3 flex gap-2">
                                        <button @click="storeAction" :disabled="actionForm.processing" class="btn-primary btn-sm">Add Action</button>
                                        <button @click="addingActionForRisk = null" class="btn-secondary btn-sm">Cancel</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div v-if="risks.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">Showing {{ risks.from }}–{{ risks.to }} of {{ risks.total }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in risks.links" :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                        preserve-state v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Add Risk Modal -->
        <div v-if="showAddRisk" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Add Risk</h3>
                    <button @click="showAddRisk = false" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <form @submit.prevent="storeRisk" class="overflow-y-auto flex-1 px-6 py-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Risk Ref *</label>
                            <input v-model="riskForm.risk_ref" class="input" :class="{ 'border-red-500': riskForm.errors.risk_ref }" required />
                            <p v-if="riskForm.errors.risk_ref" class="mt-1 text-xs text-red-600">{{ riskForm.errors.risk_ref }}</p>
                        </div>
                        <div>
                            <label class="label">Asset</label>
                            <select v-model="riskForm.asset_id" class="input">
                                <option value="">— none —</option>
                                <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_tag }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Category *</label>
                            <select v-model="riskForm.category" class="input" required>
                                <option value="infrastructure">Infrastructure</option>
                                <option value="software">Software</option>
                                <option value="data_protection">Data Protection</option>
                                <option value="cybersecurity">Cybersecurity</option>
                                <option value="continuity">Continuity</option>
                                <option value="people_process">People &amp; Process</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Risk Owner</label>
                            <input v-model="riskForm.risk_owner" class="input" placeholder="Name or department" />
                        </div>
                        <div class="col-span-2">
                            <label class="label">Description *</label>
                            <textarea v-model="riskForm.description" class="input h-20 resize-none" required :class="{ 'border-red-500': riskForm.errors.description }"></textarea>
                        </div>
                        <div class="col-span-2">
                            <label class="label">Cause</label>
                            <textarea v-model="riskForm.cause" class="input h-16 resize-none"></textarea>
                        </div>
                        <div>
                            <label class="label">Likelihood (1–5)</label>
                            <input v-model.number="riskForm.likelihood" type="number" min="1" max="5" class="input" />
                        </div>
                        <div>
                            <label class="label">Impact (1–5)</label>
                            <input v-model.number="riskForm.impact" type="number" min="1" max="5" class="input" />
                        </div>
                        <div class="col-span-2 flex items-center gap-2 text-sm text-gray-500">
                            Inherent Score: <span class="font-bold text-gray-900">{{ riskForm.likelihood * riskForm.impact }}</span>
                        </div>
                    </div>
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100">
                    <button type="button" @click="showAddRisk = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="storeRisk" class="btn-primary" :disabled="riskForm.processing">
                        {{ riskForm.processing ? 'Saving…' : 'Add Risk' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Risk Modal -->
        <div v-if="editingRisk" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Edit Risk — {{ editingRisk.risk_ref }}</h3>
                    <button @click="editingRisk = null" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <form @submit.prevent="updateRisk" class="overflow-y-auto flex-1 px-6 py-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Risk Ref *</label>
                            <input v-model="editForm.risk_ref" class="input" required />
                        </div>
                        <div>
                            <label class="label">Asset</label>
                            <select v-model="editForm.asset_id" class="input">
                                <option value="">— none —</option>
                                <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_tag }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Category *</label>
                            <select v-model="editForm.category" class="input" required>
                                <option value="infrastructure">Infrastructure</option>
                                <option value="software">Software</option>
                                <option value="data_protection">Data Protection</option>
                                <option value="cybersecurity">Cybersecurity</option>
                                <option value="continuity">Continuity</option>
                                <option value="people_process">People &amp; Process</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Risk Owner</label>
                            <input v-model="editForm.risk_owner" class="input" />
                        </div>
                        <div class="col-span-2">
                            <label class="label">Description *</label>
                            <textarea v-model="editForm.description" class="input h-20 resize-none" required></textarea>
                        </div>
                        <div class="col-span-2">
                            <label class="label">Cause</label>
                            <textarea v-model="editForm.cause" class="input h-16 resize-none"></textarea>
                        </div>
                        <div>
                            <label class="label">Likelihood (1–5)</label>
                            <input v-model.number="editForm.likelihood" type="number" min="1" max="5" class="input" />
                        </div>
                        <div>
                            <label class="label">Impact (1–5)</label>
                            <input v-model.number="editForm.impact" type="number" min="1" max="5" class="input" />
                        </div>
                    </div>
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100">
                    <button type="button" @click="editingRisk = null" class="btn-secondary">Cancel</button>
                    <button type="button" @click="updateRisk" class="btn-primary" :disabled="editForm.processing">
                        {{ editForm.processing ? 'Saving…' : 'Update Risk' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
