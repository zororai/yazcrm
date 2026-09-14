<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, PencilIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ items: Array, categories: Array, stores: Array, isManager: Boolean });

const search = ref('');
let debounce;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/items', { search: search.value || undefined }, { preserveState: true, replace: true });
    }, 300);
});

const showForm = ref(false);
const form = useForm({
    name: '', category_id: '', description: '', unit_of_measure: '',
    minimum_stock: 0, maximum_stock: '', reorder_level: 0, default_store_id: '',
});

function submit() {
    form.post('/items', { onSuccess: () => { showForm.value = false; form.reset(); } });
}

function open(item) {
    router.get(`/items/${item.id}`);
}

// ── Edit ──────────────────────────────────────────────────────────────────
const editItem = ref(null);
const editForm = useForm({
    name: '', category_id: '', description: '', unit_of_measure: '',
    minimum_stock: 0, maximum_stock: '', reorder_level: 0, default_store_id: '', is_active: true,
});

function openEdit(item) {
    editItem.value          = item;
    editForm.name            = item.name;
    editForm.category_id     = item.category_id ?? '';
    editForm.description     = item.description ?? '';
    editForm.unit_of_measure = item.unit_of_measure ?? '';
    editForm.minimum_stock   = item.minimum_stock ?? 0;
    editForm.maximum_stock   = item.maximum_stock ?? '';
    editForm.reorder_level   = item.reorder_level ?? 0;
    editForm.default_store_id = item.default_store_id ?? '';
    editForm.is_active       = item.is_active;
}

function submitEdit() {
    editForm.put(`/items/${editItem.value.id}`, { onSuccess: () => { editItem.value = null; } });
}
</script>

<template>
    <AppLayout>
        <template #title>Items</template>
        <template #header-actions>
            <button v-if="isManager" @click="showForm = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Item
            </button>
        </template>

        <div class="card mb-4">
            <input v-model="search" class="input" placeholder="Search by name…" />
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Name</th>
                        <th class="table-th">Category</th>
                        <th class="table-th">Unit</th>
                        <th class="table-th">Reorder Level</th>
                        <th class="table-th">Default Store</th>
                        <th class="table-th w-10" v-if="isManager" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="i in items" :key="i.id" class="hover:bg-gray-50 cursor-pointer" @click="open(i)">
                        <td class="table-td font-medium">{{ i.name }}</td>
                        <td class="table-td">{{ i.category?.name ?? '—' }}</td>
                        <td class="table-td">{{ i.unit_of_measure ?? '—' }}</td>
                        <td class="table-td">{{ i.reorder_level }}</td>
                        <td class="table-td">{{ i.default_store?.name ?? '—' }}</td>
                        <td class="table-td text-right" v-if="isManager" @click.stop>
                            <button @click="openEdit(i)" class="btn-secondary btn-sm" title="Edit">
                                <PencilIcon class="h-3.5 w-3.5" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!items.length">
                        <td :colspan="isManager ? 6 : 5" class="table-td text-center text-gray-400 py-8">No items match.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">New Item</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="label">Name</label>
                        <input v-model="form.name" class="input" required />
                    </div>
                    <div>
                        <label class="label">Category</label>
                        <select v-model="form.category_id" class="input">
                            <option value="">None</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Unit of Measure</label>
                        <input v-model="form.unit_of_measure" class="input" placeholder="e.g. Ream, Box, Litre" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Minimum Stock</label>
                            <input v-model.number="form.minimum_stock" type="number" min="0" class="input" />
                        </div>
                        <div>
                            <label class="label">Reorder Level</label>
                            <input v-model.number="form.reorder_level" type="number" min="0" class="input" />
                        </div>
                    </div>
                    <div>
                        <label class="label">Default Store</label>
                        <select v-model="form.default_store_id" class="input">
                            <option value="">None</option>
                            <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">Create</button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="editItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Edit {{ editItem.name }}</h3>
                <form @submit.prevent="submitEdit" class="space-y-3">
                    <div v-if="Object.keys(editForm.errors).length" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700 space-y-0.5">
                        <p v-for="(msg, field) in editForm.errors" :key="field">{{ msg }}</p>
                    </div>
                    <div>
                        <label class="label">Name</label>
                        <input v-model="editForm.name" class="input" required />
                    </div>
                    <div>
                        <label class="label">Category</label>
                        <select v-model="editForm.category_id" class="input">
                            <option value="">None</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Unit of Measure</label>
                        <input v-model="editForm.unit_of_measure" class="input" placeholder="e.g. Ream, Box, Litre" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Minimum Stock</label>
                            <input v-model.number="editForm.minimum_stock" type="number" min="0" class="input" />
                        </div>
                        <div>
                            <label class="label">Reorder Level</label>
                            <input v-model.number="editForm.reorder_level" type="number" min="0" class="input" />
                        </div>
                    </div>
                    <div>
                        <label class="label">Default Store</label>
                        <select v-model="editForm.default_store_id" class="input">
                            <option value="">None</option>
                            <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" v-model="editForm.is_active" class="rounded border-gray-300 text-brand-600" />
                        Active
                    </label>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="editItem = null" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="editForm.processing">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
