<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowDownTrayIcon, ArrowUpTrayIcon, AdjustmentsHorizontalIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ store: Object, stock: Array, items: Array, departments: Array, isManager: Boolean });

// ── Receive ──────────────────────────────────────────────────────────────
const showReceive = ref(false);
const receiveForm = useForm({ supplier_name: '', reference_number: '', notes: '', lines: [{ item_id: '', quantity: 1, unit_cost: '' }] });

function addReceiveLine() { receiveForm.lines.push({ item_id: '', quantity: 1, unit_cost: '' }); }
function removeReceiveLine(i) { receiveForm.lines.splice(i, 1); }
function submitReceive() {
    receiveForm.post(`/stores/${props.store.id}/receipts`, {
        onSuccess: () => { showReceive.value = false; receiveForm.reset(); receiveForm.lines = [{ item_id: '', quantity: 1, unit_cost: '' }]; },
    });
}

// ── Issue ─────────────────────────────────────────────────────────────────
const showIssue = ref(false);
const issueForm = useForm({ department_id: '', issued_to: '', reason: '', lines: [{ item_id: '', quantity: 1 }] });

function addIssueLine() { issueForm.lines.push({ item_id: '', quantity: 1 }); }
function removeIssueLine(i) { issueForm.lines.splice(i, 1); }
function submitIssue() {
    issueForm.post(`/stores/${props.store.id}/issues`, {
        onSuccess: () => { showIssue.value = false; issueForm.reset(); issueForm.lines = [{ item_id: '', quantity: 1 }]; },
    });
}

// ── Adjust ────────────────────────────────────────────────────────────────
const adjusting = ref(null);
const adjustForm = useForm({ physical_quantity: 0, reason: 'counting_error', notes: '' });

function openAdjust(s) {
    adjusting.value = s;
    adjustForm.physical_quantity = s.quantity;
    adjustForm.reason = 'counting_error';
    adjustForm.notes = '';
}
function submitAdjust() {
    adjustForm.post(`/stores/${props.store.id}/items/${adjusting.value.item.id}/adjust`, {
        onSuccess: () => { adjusting.value = null; },
    });
}
</script>

<template>
    <AppLayout>
        <template #title>{{ store.name }}</template>
        <template #header-actions>
            <div class="flex gap-2" v-if="isManager">
                <button @click="showReceive = true" class="btn-secondary btn-sm"><ArrowDownTrayIcon class="h-4 w-4" /> Receive Stock</button>
                <button @click="showIssue = true" class="btn-secondary btn-sm"><ArrowUpTrayIcon class="h-4 w-4" /> Issue Stock</button>
            </div>
        </template>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="card text-sm text-gray-600 space-y-1">
                <p><span class="text-gray-400">Code:</span> {{ store.code }}</p>
                <p><span class="text-gray-400">Location:</span> {{ store.location?.name }}</p>
                <p><span class="text-gray-400">Manager:</span> {{ store.manager?.name ?? '—' }}</p>
                <p><span class="text-gray-400">Storekeeper:</span> {{ store.storekeeper?.name ?? '—' }}</p>
            </div>
            <div class="card text-sm text-gray-600">
                <p>{{ store.description || 'No description.' }}</p>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Item</th>
                        <th class="table-th">Unit</th>
                        <th class="table-th">Quantity</th>
                        <th class="table-th">Reserved</th>
                        <th class="table-th">Available</th>
                        <th class="table-th w-10" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in stock" :key="s.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium">{{ s.item?.name }} <span class="text-xs text-gray-400">({{ s.item?.item_code }})</span></td>
                        <td class="table-td">{{ s.item?.unit_of_measure ?? '—' }}</td>
                        <td class="table-td">{{ s.quantity }}</td>
                        <td class="table-td">{{ s.reserved_quantity }}</td>
                        <td class="table-td font-medium">{{ s.available_quantity }}</td>
                        <td class="table-td text-right" v-if="isManager">
                            <button @click="openAdjust(s)" class="text-gray-400 hover:text-blue-600" title="Adjust"><AdjustmentsHorizontalIcon class="h-4 w-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!stock.length">
                        <td colspan="6" class="table-td text-center text-gray-400 py-8">No stock recorded for this store yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Receive Stock -->
        <div v-if="showReceive" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Receive Stock</h3>
                <form @submit.prevent="submitReceive" class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Supplier</label>
                            <input v-model="receiveForm.supplier_name" class="input" />
                        </div>
                        <div>
                            <label class="label">Reference / Invoice #</label>
                            <input v-model="receiveForm.reference_number" class="input" />
                        </div>
                    </div>
                    <div v-for="(line, i) in receiveForm.lines" :key="i" class="grid grid-cols-[1fr_80px_90px_28px] gap-2 items-end">
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
                        <div>
                            <label class="label" v-if="i === 0">Unit Cost</label>
                            <input v-model.number="line.unit_cost" type="number" min="0" step="0.01" class="input" />
                        </div>
                        <button type="button" @click="removeReceiveLine(i)" class="text-gray-400 hover:text-red-600 pb-2" :disabled="receiveForm.lines.length === 1"><TrashIcon class="h-4 w-4" /></button>
                    </div>
                    <button type="button" @click="addReceiveLine" class="text-xs text-blue-600 hover:underline flex items-center gap-1"><PlusIcon class="h-3 w-3" /> Add line</button>
                    <div>
                        <label class="label">Notes</label>
                        <textarea v-model="receiveForm.notes" class="input" rows="2"></textarea>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showReceive = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="receiveForm.processing">Receive</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Issue Stock -->
        <div v-if="showIssue" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Issue Stock</h3>
                <form @submit.prevent="submitIssue" class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Department</label>
                            <select v-model="issueForm.department_id" class="input">
                                <option value="">None</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Issued To</label>
                            <input v-model="issueForm.issued_to" class="input" placeholder="Recipient name" />
                        </div>
                    </div>
                    <div v-for="(line, i) in issueForm.lines" :key="i" class="grid grid-cols-[1fr_80px_28px] gap-2 items-end">
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
                        <button type="button" @click="removeIssueLine(i)" class="text-gray-400 hover:text-red-600 pb-2" :disabled="issueForm.lines.length === 1"><TrashIcon class="h-4 w-4" /></button>
                    </div>
                    <button type="button" @click="addIssueLine" class="text-xs text-blue-600 hover:underline flex items-center gap-1"><PlusIcon class="h-3 w-3" /> Add line</button>
                    <div>
                        <label class="label">Reason</label>
                        <textarea v-model="issueForm.reason" class="input" rows="2"></textarea>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showIssue = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="issueForm.processing">Issue</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Adjust Stock -->
        <div v-if="adjusting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-1">Adjust: {{ adjusting.item?.name }}</h3>
                <p class="text-xs text-gray-500 mb-4">System quantity: {{ adjusting.quantity }}</p>
                <form @submit.prevent="submitAdjust" class="space-y-3">
                    <div>
                        <label class="label">Physical Quantity</label>
                        <input v-model.number="adjustForm.physical_quantity" type="number" min="0" class="input" required />
                    </div>
                    <div>
                        <label class="label">Reason</label>
                        <select v-model="adjustForm.reason" class="input" required>
                            <option value="damaged">Damaged</option>
                            <option value="expired">Expired</option>
                            <option value="lost">Lost</option>
                            <option value="found">Found</option>
                            <option value="counting_error">Counting Error</option>
                            <option value="data_correction">Data Correction</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Notes</label>
                        <textarea v-model="adjustForm.notes" class="input" rows="2"></textarea>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="adjusting = null" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="adjustForm.processing">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
