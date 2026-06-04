<script setup>
import { ref } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PencilSquareIcon, TrashIcon, CheckCircleIcon, XCircleIcon, PlusIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    type:                     String,
    label:                    String,
    items:                    Array,
    isLookup:                 Boolean,
    classificationCategories: Object,   // only present for service_requested type
});

const isService = props.type === 'service_requested';

const showAdd = ref(false);
const addForm = useForm(
    props.isLookup
        ? { type: props.type, name: '', sort_order: '', classification_categories: [] }
        : { name: '', sort_order: '' }
);

function store() {
    const url = props.isLookup ? '/lookup-items' : '/distress-domains';
    addForm.post(url, {
        onSuccess: () => {
            showAdd.value = false;
            addForm.reset();
            if (props.isLookup) { addForm.type = props.type; addForm.classification_categories = []; }
        },
    });
}

const editing  = ref(null);
const editForm = useForm({ name: '', sort_order: '', is_active: true, classification_categories: [] });

function openEdit(item) {
    editing.value                      = item.id;
    editForm.name                      = item.name;
    editForm.sort_order                = item.sort_order;
    editForm.is_active                 = item.is_active;
    editForm.classification_categories = item.classification_categories ?? [];
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
        <div v-if="showAdd" class="card mb-4 space-y-3">
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
            <!-- Classification categories (only for Services Requested) -->
            <div v-if="isService && classificationCategories">
                <label class="label">Classification Categories</label>
                <p class="text-xs text-gray-400 mb-2">Which classification sections should appear when this service is selected on a ticket?</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 rounded-lg border border-gray-200 p-3 bg-gray-50">
                    <label v-for="(catLabel, catKey) in classificationCategories" :key="catKey"
                        class="flex items-center gap-2 cursor-pointer text-xs text-gray-700 py-0.5">
                        <input type="checkbox"
                            :value="catKey"
                            v-model="addForm.classification_categories"
                            class="rounded border-gray-300 text-brand-600" />
                        {{ catLabel }}
                    </label>
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
                        <th v-if="isService" class="table-th">Classification Categories</th>
                        <th class="table-th w-24 text-center">Order</th>
                        <th class="table-th w-24 text-center">Active</th>
                        <th class="table-th w-24">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!items.length">
                        <td :colspan="isService ? 6 : 5" class="py-12 text-center text-sm text-gray-400">No items yet. Click "Add Item" to get started.</td>
                    </tr>
                    <template v-for="item in items" :key="item.id">
                        <!-- View row -->
                        <tr v-if="editing !== item.id" class="hover:bg-gray-50" :class="{ 'opacity-50': !item.is_active }">
                            <td class="table-td text-gray-400 text-xs">{{ item.sort_order }}</td>
                            <td class="table-td font-medium">{{ item.name }}</td>
                            <!-- Classification categories column -->
                            <td v-if="isService" class="table-td">
                                <div v-if="item.classification_categories?.length" class="flex flex-wrap gap-1">
                                    <span v-for="cat in item.classification_categories" :key="cat"
                                        class="text-[10px] px-1.5 py-0.5 bg-brand-100 text-brand-700 rounded font-medium">
                                        {{ classificationCategories?.[cat] ?? cat }}
                                    </span>
                                </div>
                                <span v-else class="text-xs text-gray-300 italic">None assigned</span>
                            </td>
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
                        <tr v-else class="bg-brand-50 align-top">
                            <td class="table-td text-gray-400 text-xs pt-3">{{ item.sort_order }}</td>
                            <td class="table-td">
                                <input v-model="editForm.name" class="input py-1 text-sm"
                                    :class="{ 'border-red-500': editForm.errors.name }" autofocus />
                                <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
                            </td>
                            <!-- Classification categories edit -->
                            <td v-if="isService" class="table-td">
                                <div class="grid grid-cols-1 gap-1 max-h-48 overflow-y-auto pr-1">
                                    <label v-for="(catLabel, catKey) in classificationCategories" :key="catKey"
                                        class="flex items-center gap-1.5 cursor-pointer text-xs text-gray-700 py-0.5">
                                        <input type="checkbox"
                                            :value="catKey"
                                            v-model="editForm.classification_categories"
                                            class="rounded border-gray-300 text-brand-600" />
                                        {{ catLabel }}
                                    </label>
                                </div>
                            </td>
                            <td class="table-td">
                                <input v-model="editForm.sort_order" type="number" min="0" class="input w-20 py-1 text-sm" />
                            </td>
                            <td class="table-td text-center pt-3">
                                <label class="flex items-center justify-center gap-1 cursor-pointer text-sm">
                                    <input type="checkbox" v-model="editForm.is_active" class="rounded border-gray-300 text-brand-600" />
                                    Active
                                </label>
                            </td>
                            <td class="table-td pt-3">
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
