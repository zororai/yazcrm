<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon } from '@heroicons/vue/24/outline';

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
    item_code: '', name: '', category_id: '', description: '', unit_of_measure: '',
    minimum_stock: 0, maximum_stock: '', reorder_level: 0, default_store_id: '',
});

function submit() {
    form.post('/items', { onSuccess: () => { showForm.value = false; form.reset(); } });
}

function open(item) {
    router.get(`/items/${item.id}`);
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
            <input v-model="search" class="input" placeholder="Search by name or item code…" />
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Code</th>
                        <th class="table-th">Name</th>
                        <th class="table-th">Category</th>
                        <th class="table-th">Unit</th>
                        <th class="table-th">Reorder Level</th>
                        <th class="table-th">Default Store</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="i in items" :key="i.id" class="hover:bg-gray-50 cursor-pointer" @click="open(i)">
                        <td class="table-td font-medium">{{ i.item_code }}</td>
                        <td class="table-td">{{ i.name }}</td>
                        <td class="table-td">{{ i.category?.name ?? '—' }}</td>
                        <td class="table-td">{{ i.unit_of_measure ?? '—' }}</td>
                        <td class="table-td">{{ i.reorder_level }}</td>
                        <td class="table-td">{{ i.default_store?.name ?? '—' }}</td>
                    </tr>
                    <tr v-if="!items.length">
                        <td colspan="6" class="table-td text-center text-gray-400 py-8">No items match.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">New Item</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="label">Item Code</label>
                        <input v-model="form.item_code" class="input" required />
                    </div>
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
    </AppLayout>
</template>
