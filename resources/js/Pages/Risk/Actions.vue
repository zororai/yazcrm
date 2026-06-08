<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, XMarkIcon, TrashIcon, PencilIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    actions: Object,
    filters: Object,
});

const statusFilter   = ref(props.filters.status   ?? '');
const priorityFilter = ref(props.filters.priority ?? '');

function apply() {
    router.get('/risk/actions', {
        status:   statusFilter.value   || undefined,
        priority: priorityFilter.value || undefined,
    }, { preserveState: true, replace: true });
}
watch([statusFilter, priorityFilter], apply);

// ── Add Action Modal ────────────────────────────────────────────────────────
const showAdd = ref(false);
const addForm = useForm({
    risk_id:     '',
    action_ref:  '',
    description: '',
    owner:       '',
    target_date: '',
    status:      'open',
    priority:    'medium',
});
function store() {
    addForm.post('/risk/actions', { onSuccess: () => { showAdd.value = false; addForm.reset('risk_id', 'action_ref', 'description', 'owner', 'target_date'); } });
}

// ── Edit Action Modal ───────────────────────────────────────────────────────
const editingAction = ref(null);
const editForm = useForm({
    action_ref:  '',
    description: '',
    owner:       '',
    target_date: '',
    status:      'open',
    priority:    'medium',
});
function openEdit(action) {
    editingAction.value = action;
    editForm.action_ref  = action.action_ref;
    editForm.description = action.description;
    editForm.owner       = action.owner;
    editForm.target_date = action.target_date;
    editForm.status      = action.status;
    editForm.priority    = action.priority;
}
function update() {
    editForm.put(`/risk/actions/${editingAction.value.id}`, {
        onSuccess: () => { editingAction.value = null; },
    });
}

function changeStatus(action, newStatus) {
    router.put(`/risk/actions/${action.id}`, { status: newStatus }, { preserveScroll: true });
}

function deleteAction(action) {
    if (!confirm(`Delete action "${action.action_ref}"?`)) return;
    router.delete(`/risk/actions/${action.id}`, { preserveScroll: true });
}

function isOverdue(action) {
    return action.status !== 'done' && action.target_date && new Date(action.target_date) < new Date();
}

const statusColors = {
    open:        'bg-yellow-100 text-yellow-800',
    in_progress: 'bg-blue-100 text-blue-800',
    done:        'bg-green-100 text-green-800',
};
const priorityColors = {
    low:      'bg-gray-100 text-gray-600',
    medium:   'bg-blue-100 text-blue-800',
    high:     'bg-orange-100 text-orange-800',
    critical: 'bg-red-100 text-red-800',
};
</script>

<template>
    <AppLayout>
        <template #title>Priority Actions</template>
        <template #header-actions>
            <Link href="/risk" class="btn-secondary btn-sm">Dashboard</Link>
            <Link href="/risk/risks" class="btn-secondary btn-sm">Risk Register</Link>
            <button @click="showAdd = true" class="btn-primary btn-sm inline-flex items-center gap-1.5">
                <PlusIcon class="h-4 w-4" /> Add Action
            </button>
        </template>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="flex gap-3 items-end">
                <div>
                    <label class="label">Status</label>
                    <select v-model="statusFilter" class="input w-36">
                        <option value="">All</option>
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="done">Done</option>
                    </select>
                </div>
                <div>
                    <label class="label">Priority</label>
                    <select v-model="priorityFilter" class="input w-32">
                        <option value="">All</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
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
                        <th class="table-th">Risk / Asset</th>
                        <th class="table-th">Description</th>
                        <th class="table-th">Owner</th>
                        <th class="table-th">Target Date</th>
                        <th class="table-th">Priority</th>
                        <th class="table-th">Status</th>
                        <th class="table-th w-20"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!actions.data.length">
                        <td colspan="8" class="py-12 text-center text-sm text-gray-400">No actions found.</td>
                    </tr>
                    <tr v-for="a in actions.data" :key="a.id"
                        :class="['hover:bg-gray-50', isOverdue(a) ? 'bg-red-50' : '']">
                        <td class="table-td font-mono text-xs font-semibold">{{ a.action_ref }}</td>
                        <td class="table-td text-sm">
                            <p class="font-medium text-gray-700">{{ a.risk?.risk_ref ?? '—' }}</p>
                            <p class="text-gray-400 text-xs">{{ a.risk?.asset?.name ?? '—' }}</p>
                        </td>
                        <td class="table-td text-sm max-w-xs">
                            <p class="truncate" :title="a.description">{{ a.description }}</p>
                        </td>
                        <td class="table-td text-sm">{{ a.owner }}</td>
                        <td class="table-td text-sm" :class="isOverdue(a) ? 'text-red-600 font-semibold' : ''">
                            {{ a.target_date ? new Date(a.target_date).toLocaleDateString() : '—' }}
                            <span v-if="isOverdue(a)" class="block text-xs text-red-500">Overdue</span>
                        </td>
                        <td class="table-td">
                            <span :class="['badge', priorityColors[a.priority]]">{{ a.priority }}</span>
                        </td>
                        <td class="table-td">
                            <select
                                :value="a.status"
                                @change="changeStatus(a, $event.target.value)"
                                :class="['text-xs rounded border-0 font-semibold cursor-pointer px-1 py-0.5', statusColors[a.status]]"
                            >
                                <option value="open">open</option>
                                <option value="in_progress">in progress</option>
                                <option value="done">done</option>
                            </select>
                        </td>
                        <td class="table-td">
                            <div class="flex items-center gap-1">
                                <button @click="openEdit(a)" class="text-gray-400 hover:text-blue-600" title="Edit">
                                    <PencilIcon class="h-4 w-4" />
                                </button>
                                <button @click="deleteAction(a)" class="text-gray-400 hover:text-red-600" title="Delete">
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="actions.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">Showing {{ actions.from }}–{{ actions.to }} of {{ actions.total }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in actions.links" :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                        preserve-state v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Add Action Modal -->
        <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-xl flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Add Priority Action</h3>
                    <button @click="showAdd = false" class="text-gray-400 hover:text-gray-600"><XMarkIcon class="h-5 w-5" /></button>
                </div>
                <form @submit.prevent="store" class="overflow-y-auto flex-1 px-6 py-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Action Ref *</label>
                            <input v-model="addForm.action_ref" class="input" :class="{ 'border-red-500': addForm.errors.action_ref }" required placeholder="ACT-001" />
                            <p v-if="addForm.errors.action_ref" class="mt-1 text-xs text-red-600">{{ addForm.errors.action_ref }}</p>
                        </div>
                        <div>
                            <label class="label">Risk ID (optional)</label>
                            <input v-model="addForm.risk_id" class="input" placeholder="Risk numeric ID" />
                        </div>
                        <div class="col-span-2">
                            <label class="label">Description *</label>
                            <textarea v-model="addForm.description" class="input h-20 resize-none" required></textarea>
                        </div>
                        <div>
                            <label class="label">Owner *</label>
                            <input v-model="addForm.owner" class="input" required />
                        </div>
                        <div>
                            <label class="label">Target Date *</label>
                            <input v-model="addForm.target_date" type="date" class="input" required />
                        </div>
                        <div>
                            <label class="label">Priority</label>
                            <select v-model="addForm.priority" class="input">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Status</label>
                            <select v-model="addForm.status" class="input">
                                <option value="open">Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="done">Done</option>
                            </select>
                        </div>
                    </div>
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100">
                    <button type="button" @click="showAdd = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="store" class="btn-primary" :disabled="addForm.processing">
                        {{ addForm.processing ? 'Saving…' : 'Add Action' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Action Modal -->
        <div v-if="editingAction" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-xl flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Edit Action — {{ editingAction.action_ref }}</h3>
                    <button @click="editingAction = null" class="text-gray-400 hover:text-gray-600"><XMarkIcon class="h-5 w-5" /></button>
                </div>
                <form @submit.prevent="update" class="overflow-y-auto flex-1 px-6 py-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Action Ref</label>
                            <input v-model="editForm.action_ref" class="input" required />
                        </div>
                        <div class="col-span-2">
                            <label class="label">Description</label>
                            <textarea v-model="editForm.description" class="input h-20 resize-none" required></textarea>
                        </div>
                        <div>
                            <label class="label">Owner</label>
                            <input v-model="editForm.owner" class="input" required />
                        </div>
                        <div>
                            <label class="label">Target Date</label>
                            <input v-model="editForm.target_date" type="date" class="input" required />
                        </div>
                        <div>
                            <label class="label">Priority</label>
                            <select v-model="editForm.priority" class="input">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Status</label>
                            <select v-model="editForm.status" class="input">
                                <option value="open">Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="done">Done</option>
                            </select>
                        </div>
                    </div>
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100">
                    <button type="button" @click="editingAction = null" class="btn-secondary">Cancel</button>
                    <button type="button" @click="update" class="btn-primary" :disabled="editForm.processing">
                        {{ editForm.processing ? 'Saving…' : 'Update Action' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
