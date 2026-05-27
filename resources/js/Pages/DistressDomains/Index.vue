<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PencilSquareIcon, TrashIcon, CheckCircleIcon, XCircleIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    domains: Array,
    lookups: Object,
    lookupTypes: Object,
});

// ── Distress Domains ──────────────────────────────────────────────
const showAdd  = ref(false);
const addForm  = useForm({ name: '', sort_order: '' });

function store() {
    addForm.post('/distress-domains', {
        onSuccess: () => { showAdd.value = false; addForm.reset(); },
    });
}

const editing  = ref(null);
const editForm = useForm({ name: '', sort_order: '', is_active: true });

function openEdit(d) {
    editing.value        = d.id;
    editForm.name        = d.name;
    editForm.sort_order  = d.sort_order;
    editForm.is_active   = d.is_active;
}

function saveEdit(d) {
    editForm.put(`/distress-domains/${d.id}`, {
        onSuccess: () => { editing.value = null; editForm.reset(); },
    });
}

function cancelEdit() { editing.value = null; editForm.reset(); }

function remove(d) {
    if (!confirm(`Remove "${d.name}"?`)) return;
    router.delete(`/distress-domains/${d.id}`);
}

// ── Lookup Items (generic for all 5 types) ────────────────────────
const showAddLookup  = ref({});
const addLookupForm  = ref({});
const editingLookup  = ref(null);
const editLookupForm = useForm({ name: '', sort_order: '', is_active: true });

function getLookupAddForm(type) {
    if (!addLookupForm.value[type]) {
        addLookupForm.value[type] = useForm({ type, name: '', sort_order: '' });
    }
    return addLookupForm.value[type];
}

function storeLookup(type) {
    const form = getLookupAddForm(type);
    form.post('/lookup-items', {
        onSuccess: () => { showAddLookup.value[type] = false; form.reset(); form.type = type; },
    });
}

function openEditLookup(item) {
    editingLookup.value      = item.id;
    editLookupForm.name       = item.name;
    editLookupForm.sort_order = item.sort_order;
    editLookupForm.is_active  = item.is_active;
}

function saveEditLookup(item) {
    editLookupForm.put(`/lookup-items/${item.id}`, {
        onSuccess: () => { editingLookup.value = null; editLookupForm.reset(); },
    });
}

function cancelEditLookup() { editingLookup.value = null; editLookupForm.reset(); }

function removeLookup(item) {
    if (!confirm(`Remove "${item.name}"?`)) return;
    router.delete(`/lookup-items/${item.id}`);
}
</script>

<template>
    <AppLayout>
        <template #title>Lookup Settings</template>

        <!-- ── Distress Domains ───────────────────────────────────── -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-semibold text-gray-800">Distress Domains</h2>
                <button @click="showAdd = !showAdd" class="btn-primary btn-sm">
                    <PlusIcon class="h-4 w-4" /> Add Domain
                </button>
            </div>

            <div v-if="showAdd" class="card mb-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">New Domain</h3>
                <div class="flex gap-3 items-end">
                    <div class="flex-1">
                        <label class="label">Domain Name *</label>
                        <input v-model="addForm.name" class="input" :class="{ 'border-red-500': addForm.errors.name }"
                            placeholder="e.g. Mental Health / Psychosocial Support" autofocus />
                        <p v-if="addForm.errors.name" class="mt-1 text-xs text-red-600">{{ addForm.errors.name }}</p>
                    </div>
                    <div class="w-28">
                        <label class="label">Order</label>
                        <input v-model="addForm.sort_order" type="number" min="0" class="input" placeholder="0" />
                    </div>
                    <div class="flex gap-2 pb-0.5">
                        <button @click="store" :disabled="addForm.processing" class="btn-primary">
                            <CheckCircleIcon class="h-4 w-4" /> Save
                        </button>
                        <button @click="showAdd = false; addForm.reset()" class="btn-secondary">
                            <XCircleIcon class="h-4 w-4" /> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <div class="card p-0 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="table-th w-12">#</th>
                            <th class="table-th">Domain Name</th>
                            <th class="table-th w-24 text-center">Order</th>
                            <th class="table-th w-24 text-center">Active</th>
                            <th class="table-th w-24">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-if="!domains.length">
                            <td colspan="5" class="py-8 text-center text-sm text-gray-400">No domains yet.</td>
                        </tr>
                        <template v-for="d in domains" :key="d.id">
                            <tr v-if="editing !== d.id" class="hover:bg-gray-50" :class="{ 'opacity-50': !d.is_active }">
                                <td class="table-td text-gray-400 text-xs">{{ d.sort_order }}</td>
                                <td class="table-td font-medium">{{ d.name }}</td>
                                <td class="table-td text-center text-sm text-gray-500">{{ d.sort_order }}</td>
                                <td class="table-td text-center">
                                    <span :class="d.is_active ? 'badge bg-green-100 text-green-700' : 'badge bg-gray-100 text-gray-500'">
                                        {{ d.is_active ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="table-td">
                                    <div class="flex gap-1">
                                        <button @click="openEdit(d)" class="p-1.5 rounded text-gray-400 hover:text-brand-600 hover:bg-gray-100" title="Edit">
                                            <PencilSquareIcon class="h-4 w-4" />
                                        </button>
                                        <button @click="remove(d)" class="p-1.5 rounded text-gray-400 hover:text-red-600 hover:bg-red-50" title="Delete">
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else class="bg-brand-50">
                                <td class="table-td text-gray-400 text-xs">{{ d.sort_order }}</td>
                                <td class="table-td">
                                    <input v-model="editForm.name" class="input py-1 text-sm" :class="{ 'border-red-500': editForm.errors.name }" autofocus />
                                    <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
                                </td>
                                <td class="table-td">
                                    <input v-model="editForm.sort_order" type="number" min="0" class="input w-20 py-1 text-sm" />
                                </td>
                                <td class="table-td text-center">
                                    <label class="flex items-center justify-center gap-1 cursor-pointer text-sm">
                                        <input type="checkbox" v-model="editForm.is_active" class="rounded border-gray-300 text-brand-600" />
                                        Active
                                    </label>
                                </td>
                                <td class="table-td">
                                    <div class="flex gap-1">
                                        <button @click="saveEdit(d)" :disabled="editForm.processing" class="btn-primary btn-sm py-1">
                                            <CheckCircleIcon class="h-4 w-4" /> Save
                                        </button>
                                        <button @click="cancelEdit" class="btn-secondary btn-sm py-1">
                                            <XCircleIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-gray-400">
                Active domains appear in the distress domain dropdown when logging tickets.
            </p>
        </div>

        <!-- ── Lookup Cards (Purpose of Call, Services, etc.) ────── -->
        <div v-for="(label, type) in lookupTypes" :key="type" class="mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-semibold text-gray-800">{{ label }}</h2>
                <button @click="showAddLookup[type] = !showAddLookup[type]" class="btn-primary btn-sm">
                    <PlusIcon class="h-4 w-4" /> Add Item
                </button>
            </div>

            <div v-if="showAddLookup[type]" class="card mb-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">New {{ label }}</h3>
                <div class="flex gap-3 items-end">
                    <div class="flex-1">
                        <label class="label">Name *</label>
                        <input v-model="getLookupAddForm(type).name" class="input"
                            :class="{ 'border-red-500': getLookupAddForm(type).errors.name }"
                            :placeholder="`e.g. ${label}`" autofocus />
                        <p v-if="getLookupAddForm(type).errors.name" class="mt-1 text-xs text-red-600">
                            {{ getLookupAddForm(type).errors.name }}
                        </p>
                    </div>
                    <div class="w-28">
                        <label class="label">Order</label>
                        <input v-model="getLookupAddForm(type).sort_order" type="number" min="0" class="input" placeholder="0" />
                    </div>
                    <div class="flex gap-2 pb-0.5">
                        <button @click="storeLookup(type)" :disabled="getLookupAddForm(type).processing" class="btn-primary">
                            <CheckCircleIcon class="h-4 w-4" /> Save
                        </button>
                        <button @click="showAddLookup[type] = false; getLookupAddForm(type).reset(); getLookupAddForm(type).type = type" class="btn-secondary">
                            <XCircleIcon class="h-4 w-4" /> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <div class="card p-0 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="table-th w-12">#</th>
                            <th class="table-th">Name</th>
                            <th class="table-th w-24 text-center">Order</th>
                            <th class="table-th w-24 text-center">Active</th>
                            <th class="table-th w-24">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-if="!lookups[type]?.length">
                            <td colspan="5" class="py-8 text-center text-sm text-gray-400">No items yet.</td>
                        </tr>
                        <template v-for="item in lookups[type]" :key="item.id">
                            <tr v-if="editingLookup !== item.id" class="hover:bg-gray-50" :class="{ 'opacity-50': !item.is_active }">
                                <td class="table-td text-gray-400 text-xs">{{ item.sort_order }}</td>
                                <td class="table-td font-medium">{{ item.name }}</td>
                                <td class="table-td text-center text-sm text-gray-500">{{ item.sort_order }}</td>
                                <td class="table-td text-center">
                                    <span :class="item.is_active ? 'badge bg-green-100 text-green-700' : 'badge bg-gray-100 text-gray-500'">
                                        {{ item.is_active ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="table-td">
                                    <div class="flex gap-1">
                                        <button @click="openEditLookup(item)" class="p-1.5 rounded text-gray-400 hover:text-brand-600 hover:bg-gray-100" title="Edit">
                                            <PencilSquareIcon class="h-4 w-4" />
                                        </button>
                                        <button @click="removeLookup(item)" class="p-1.5 rounded text-gray-400 hover:text-red-600 hover:bg-red-50" title="Delete">
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else class="bg-brand-50">
                                <td class="table-td text-gray-400 text-xs">{{ item.sort_order }}</td>
                                <td class="table-td">
                                    <input v-model="editLookupForm.name" class="input py-1 text-sm"
                                        :class="{ 'border-red-500': editLookupForm.errors.name }" autofocus />
                                    <p v-if="editLookupForm.errors.name" class="mt-1 text-xs text-red-600">{{ editLookupForm.errors.name }}</p>
                                </td>
                                <td class="table-td">
                                    <input v-model="editLookupForm.sort_order" type="number" min="0" class="input w-20 py-1 text-sm" />
                                </td>
                                <td class="table-td text-center">
                                    <label class="flex items-center justify-center gap-1 cursor-pointer text-sm">
                                        <input type="checkbox" v-model="editLookupForm.is_active" class="rounded border-gray-300 text-brand-600" />
                                        Active
                                    </label>
                                </td>
                                <td class="table-td">
                                    <div class="flex gap-1">
                                        <button @click="saveEditLookup(item)" :disabled="editLookupForm.processing" class="btn-primary btn-sm py-1">
                                            <CheckCircleIcon class="h-4 w-4" /> Save
                                        </button>
                                        <button @click="cancelEditLookup" class="btn-secondary btn-sm py-1">
                                            <XCircleIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-gray-400">
                Active items appear in the {{ label }} dropdown when logging tickets.
            </p>
        </div>

    </AppLayout>
</template>
