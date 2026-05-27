<script setup>
import { ref } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PencilSquareIcon, TrashIcon, CheckCircleIcon, XCircleIcon, PlusIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    type:     String,
    label:    String,
    items:    Array,
    isLookup: Boolean,
});

const showAdd = ref(false);
const addForm = useForm(
    props.isLookup
        ? { type: props.type, name: '', sort_order: '' }
        : { name: '', sort_order: '' }
);

function store() {
    const url = props.isLookup ? '/lookup-items' : '/distress-domains';
    addForm.post(url, {
        onSuccess: () => {
            showAdd.value = false;
            addForm.reset();
            if (props.isLookup) addForm.type = props.type;
        },
    });
}

const editing  = ref(null);
const editForm = useForm({ name: '', sort_order: '', is_active: true });

function openEdit(item) {
    editing.value       = item.id;
    editForm.name       = item.name;
    editForm.sort_order = item.sort_order;
    editForm.is_active  = item.is_active;
}

function saveEdit(item) {
    const url = props.isLookup ? `/lookup-items/${item.id}` : `/distress-domains/${item.id}`;
    editForm.put(url, {
        onSuccess: () => { editing.value = null; editForm.reset(); },
    });
}

function cancelEdit() { editing.value = null; editForm.reset(); }

function remove(item) {
    if (!confirm(`Remove "${item.name}"?`)) return;
    const url = props.isLookup ? `/lookup-items/${item.id}` : `/distress-domains/${item.id}`;
    router.delete(url);
}
</script>

<template>
    <AppLayout>
        <template #title>{{ label }}</template>
        <template #header-actions>
            <Link href="/distress-domains" class="btn-secondary btn-sm">
                <ArrowLeftIcon class="h-4 w-4" /> Back
            </Link>
            <button @click="showAdd = !showAdd" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> Add Item
            </button>
        </template>

        <!-- Add form -->
        <div v-if="showAdd" class="card mb-4">
            <div class="flex gap-3 items-end">
                <div class="flex-1">
                    <label class="label">Name *</label>
                    <input v-model="addForm.name" class="input"
                        :class="{ 'border-red-500': addForm.errors.name }"
                        :placeholder="`New ${label} item`" autofocus />
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
                    <button @click="showAdd = false; addForm.reset();" class="btn-secondary">
                        <XCircleIcon class="h-4 w-4" /> Cancel
                    </button>
                </div>
            </div>
        </div>

        <!-- Table -->
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
                    <tr v-if="!items.length">
                        <td colspan="5" class="py-12 text-center text-sm text-gray-400">No items yet. Click "Add Item" to get started.</td>
                    </tr>
                    <template v-for="item in items" :key="item.id">
                        <!-- View row -->
                        <tr v-if="editing !== item.id" class="hover:bg-gray-50" :class="{ 'opacity-50': !item.is_active }">
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
                                    <button @click="openEdit(item)" class="p-1.5 rounded text-gray-400 hover:text-brand-600 hover:bg-gray-100" title="Edit">
                                        <PencilSquareIcon class="h-4 w-4" />
                                    </button>
                                    <button @click="remove(item)" class="p-1.5 rounded text-gray-400 hover:text-red-600 hover:bg-red-50" title="Delete">
                                        <TrashIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <!-- Edit row -->
                        <tr v-else class="bg-brand-50">
                            <td class="table-td text-gray-400 text-xs">{{ item.sort_order }}</td>
                            <td class="table-td">
                                <input v-model="editForm.name" class="input py-1 text-sm"
                                    :class="{ 'border-red-500': editForm.errors.name }" autofocus />
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
                                    <button @click="saveEdit(item)" :disabled="editForm.processing" class="btn-primary btn-sm py-1">
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
            Active items appear in the {{ label }} dropdown when logging tickets.
        </p>
    </AppLayout>
</template>
