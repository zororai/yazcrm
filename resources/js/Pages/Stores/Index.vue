<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ stores: Array, locations: Array, users: Array, isManager: Boolean });

const showForm = ref(false);
const form = useForm({ code: '', name: '', description: '', location_id: '', manager_id: '', storekeeper_id: '' });

function submit() {
    form.post('/stores', { onSuccess: () => { showForm.value = false; form.reset(); } });
}

function open(store) {
    router.get(`/stores/${store.id}`);
}
</script>

<template>
    <AppLayout>
        <template #title>Stores</template>
        <template #header-actions>
            <button v-if="isManager" @click="showForm = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Store
            </button>
        </template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Code</th>
                        <th class="table-th">Name</th>
                        <th class="table-th">Location</th>
                        <th class="table-th">Storekeeper</th>
                        <th class="table-th">Items in Stock</th>
                        <th class="table-th">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in stores" :key="s.id" class="hover:bg-gray-50 cursor-pointer" @click="open(s)">
                        <td class="table-td font-medium">{{ s.code }}</td>
                        <td class="table-td">{{ s.name }}</td>
                        <td class="table-td">{{ s.location?.name }}</td>
                        <td class="table-td">{{ s.storekeeper?.name ?? '—' }}</td>
                        <td class="table-td">{{ s.stock_count }}</td>
                        <td class="table-td">
                            <span :class="['badge', s.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600']">
                                {{ s.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!stores.length">
                        <td colspan="6" class="table-td text-center text-gray-400 py-8">No stores yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">New Store</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="label">Code</label>
                        <input v-model="form.code" class="input" required />
                    </div>
                    <div>
                        <label class="label">Name</label>
                        <input v-model="form.name" class="input" required />
                    </div>
                    <div>
                        <label class="label">Location</label>
                        <select v-model="form.location_id" class="input" required>
                            <option value="" disabled>Select…</option>
                            <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Storekeeper</label>
                        <select v-model="form.storekeeper_id" class="input">
                            <option value="">None</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Description</label>
                        <textarea v-model="form.description" class="input" rows="2"></textarea>
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
