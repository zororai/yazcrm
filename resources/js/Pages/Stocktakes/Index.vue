<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ stocktakes: Array, stores: Array, isManager: Boolean });

const showForm = ref(false);
const form = useForm({ store_id: '' });

function submit() {
    form.post('/stocktakes', { onSuccess: () => { showForm.value = false; } });
}

function open(s) {
    router.get(`/stocktakes/${s.id}`);
}

const statusColor = {
    counting: 'bg-amber-100 text-amber-800',
    completed: 'bg-green-100 text-green-800',
};
</script>

<template>
    <AppLayout>
        <template #title>Stocktakes</template>
        <template #header-actions>
            <button v-if="isManager" @click="showForm = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Stocktake
            </button>
        </template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Number</th>
                        <th class="table-th">Store</th>
                        <th class="table-th">Started By</th>
                        <th class="table-th">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in stocktakes" :key="s.id" class="hover:bg-gray-50 cursor-pointer" @click="open(s)">
                        <td class="table-td font-medium">{{ s.stocktake_number }}</td>
                        <td class="table-td">{{ s.store?.name }}</td>
                        <td class="table-td">{{ s.started_by?.name }}</td>
                        <td class="table-td"><span :class="['badge', statusColor[s.status]]">{{ s.status }}</span></td>
                    </tr>
                    <tr v-if="!stocktakes.length">
                        <td colspan="4" class="table-td text-center text-gray-400 py-8">No stocktakes yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Start Stocktake</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="label">Store</label>
                        <select v-model="form.store_id" class="input" required>
                            <option value="" disabled>Select…</option>
                            <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <p class="text-xs text-gray-500">This snapshots the store's current system quantities for counting.</p>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">Start</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
