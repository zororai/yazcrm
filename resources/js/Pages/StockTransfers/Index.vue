<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ transfers: Array, stores: Array, items: Array, isManager: Boolean });

const showForm = ref(false);
const form = useForm({ from_store_id: '', to_store_id: '', notes: '', lines: [{ item_id: '', quantity: 1 }] });

function addLine() { form.lines.push({ item_id: '', quantity: 1 }); }
function removeLine(i) { form.lines.splice(i, 1); }

function submit() {
    form.post('/stock-transfers', { onSuccess: () => { showForm.value = false; } });
}

function open(t) {
    router.get(`/stock-transfers/${t.id}`);
}

const statusColor = {
    draft: 'bg-gray-100 text-gray-700',
    dispatched: 'bg-amber-100 text-amber-800',
    received: 'bg-green-100 text-green-800',
    cancelled: 'bg-gray-200 text-gray-500',
};
</script>

<template>
    <AppLayout>
        <template #title>Stock Transfers</template>
        <template #header-actions>
            <button v-if="isManager" @click="showForm = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Transfer
            </button>
        </template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Number</th>
                        <th class="table-th">From</th>
                        <th class="table-th">To</th>
                        <th class="table-th">Requested By</th>
                        <th class="table-th">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="t in transfers" :key="t.id" class="hover:bg-gray-50 cursor-pointer" @click="open(t)">
                        <td class="table-td font-medium">{{ t.transfer_number }}</td>
                        <td class="table-td">{{ t.from_store?.name }}</td>
                        <td class="table-td">{{ t.to_store?.name }}</td>
                        <td class="table-td">{{ t.requested_by?.name }}</td>
                        <td class="table-td"><span :class="['badge', statusColor[t.status]]">{{ t.status }}</span></td>
                    </tr>
                    <tr v-if="!transfers.length">
                        <td colspan="5" class="table-td text-center text-gray-400 py-8">No transfers yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-4">New Transfer</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">From Store</label>
                            <select v-model="form.from_store_id" class="input" required>
                                <option value="" disabled>Select…</option>
                                <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">To Store</label>
                            <select v-model="form.to_store_id" class="input" required>
                                <option value="" disabled>Select…</option>
                                <option v-for="s in stores" :key="s.id" :value="s.id" :disabled="s.id === form.from_store_id">{{ s.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div v-for="(line, i) in form.lines" :key="i" class="grid grid-cols-[1fr_80px_28px] gap-2 items-end">
                        <div>
                            <label class="label" v-if="i === 0">Item</label>
                            <select v-model="line.item_id" class="input" required>
                                <option value="" disabled>Select…</option>
                                <option v-for="it in items" :key="it.id" :value="it.id">{{ it.name }} ({{ it.item_code }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="label" v-if="i === 0">Qty</label>
                            <input v-model.number="line.quantity" type="number" min="1" class="input" required />
                        </div>
                        <button type="button" @click="removeLine(i)" class="text-gray-400 hover:text-red-600 pb-2" :disabled="form.lines.length === 1"><TrashIcon class="h-4 w-4" /></button>
                    </div>
                    <button type="button" @click="addLine" class="text-xs text-blue-600 hover:underline flex items-center gap-1"><PlusIcon class="h-3 w-3" /> Add line</button>
                    <div>
                        <label class="label">Notes</label>
                        <textarea v-model="form.notes" class="input" rows="2"></textarea>
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
