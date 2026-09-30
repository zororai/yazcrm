<script setup>
import { ref, computed } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ order: Object, isManager: Boolean });

const statusColor = {
    draft: 'bg-gray-200 text-gray-600',
    pending_approval: 'bg-amber-100 text-amber-800',
    approved: 'bg-blue-100 text-blue-800',
    rejected: 'bg-red-100 text-red-800',
    sent: 'bg-indigo-100 text-indigo-800',
    partially_received: 'bg-orange-100 text-orange-800',
    received: 'bg-green-100 text-green-800',
    cancelled: 'bg-gray-200 text-gray-500',
};

function act(action, message) {
    if (! confirm(message)) return;
    router.post(`/purchase-orders/${props.order.id}/${action}`);
}

const showReject = ref(false);
const rejectForm = useForm({ reason: '' });
function submitReject() {
    rejectForm.post(`/purchase-orders/${props.order.id}/reject`, { onSuccess: () => { showReject.value = false; rejectForm.reset(); } });
}

const showCancel = ref(false);
const cancelForm = useForm({ reason: '' });
function submitCancel() {
    cancelForm.post(`/purchase-orders/${props.order.id}/cancel`, { onSuccess: () => { showCancel.value = false; cancelForm.reset(); } });
}

const showReceive = ref(false);
const receivableItems = computed(() =>
    props.order.items.filter(i => i.item_id && i.quantity_received < i.quantity)
);
const receiveForm = useForm({ lines: [] });
function openReceive() {
    receiveForm.lines = receivableItems.value.map(i => ({
        purchase_order_item_id: i.id,
        quantity: i.quantity - i.quantity_received,
        max: i.quantity - i.quantity_received,
        label: i.item?.name ?? i.description,
    }));
    showReceive.value = true;
}
function submitReceive() {
    receiveForm.transform(data => ({
        lines: data.lines.filter(l => l.quantity > 0).map(l => ({ purchase_order_item_id: l.purchase_order_item_id, quantity: l.quantity })),
    })).post(`/purchase-orders/${props.order.id}/receive`, { onSuccess: () => { showReceive.value = false; } });
}

// ── Stage 10: confirm delivery / invoice ─────────────────────────────────
const showDelivery = ref(false);
const deliveryForm = useForm({ notes: '', invoice_reference: '', invoice_date: '' });
function submitDelivery() {
    deliveryForm.post(`/purchase-orders/${props.order.id}/confirm-delivery`, { onSuccess: () => { showDelivery.value = false; } });
}

// ── Stage 11: raise payment requisition ──────────────────────────────────
const showPayment = ref(false);
const paymentForm = useForm({ payee_name: props.order.supplier?.name ?? '', amount: props.order.total, payment_method: 'bank_transfer', description: '' });
function submitPayment() {
    paymentForm.post(`/purchase-orders/${props.order.id}/payments`, { onSuccess: () => { showPayment.value = false; } });
}
</script>

<template>
    <AppLayout>
        <template #title>{{ order.po_number }}</template>
        <template #header-actions>
            <div v-if="isManager" class="flex gap-2 flex-wrap">
                <button v-if="order.status === 'draft'" @click="act('submit', 'Submit this purchase order for approval?')" class="btn-primary btn-sm">Submit for Approval</button>
                <button v-if="order.status === 'pending_approval'" @click="act('approve', 'Approve this purchase order?')" class="btn-primary btn-sm">Approve</button>
                <button v-if="order.status === 'pending_approval'" @click="showReject = true" class="btn-secondary btn-sm">Reject</button>
                <button v-if="order.status === 'approved'" @click="act('mark-sent', 'Mark this purchase order as sent to the supplier?')" class="btn-primary btn-sm">Mark Sent</button>
                <button v-if="['sent','approved','partially_received'].includes(order.status) && order.store" @click="openReceive" class="btn-primary btn-sm">Receive Goods</button>
                <button v-if="['approved','sent','partially_received'].includes(order.status)" @click="showDelivery = true" class="btn-primary btn-sm">Confirm Delivery / Invoice</button>
                <button v-if="order.status === 'received'" @click="showPayment = true" class="btn-primary btn-sm">Raise Payment Requisition</button>
                <button v-if="!['received','cancelled'].includes(order.status)" @click="showCancel = true" class="btn-secondary btn-sm">Cancel</button>
            </div>
        </template>

        <div class="card mb-4">
            <div class="flex flex-wrap justify-between gap-4">
                <div>
                    <div class="text-sm text-gray-500">Supplier</div>
                    <div class="font-medium">{{ order.supplier?.name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Store</div>
                    <div class="font-medium">{{ order.store?.name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Requested By</div>
                    <div class="font-medium">{{ order.requested_by?.name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Approved By</div>
                    <div class="font-medium">{{ order.approved_by?.name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Status</div>
                    <span :class="['badge', statusColor[order.status]]">{{ order.status.replace('_', ' ') }}</span>
                </div>
            </div>
            <p v-if="order.notes" class="mt-3 text-sm text-gray-600">{{ order.notes }}</p>
        </div>

        <div class="card p-0 overflow-hidden mb-4">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Item</th>
                        <th class="table-th">Qty</th>
                        <th class="table-th">Unit Cost</th>
                        <th class="table-th">Line Total</th>
                        <th class="table-th">Received</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="i in order.items" :key="i.id">
                        <td class="table-td">{{ i.item?.name ?? i.description ?? '—' }}</td>
                        <td class="table-td">{{ i.quantity }}</td>
                        <td class="table-td">{{ Number(i.unit_cost).toFixed(2) }}</td>
                        <td class="table-td">{{ Number(i.line_total).toFixed(2) }}</td>
                        <td class="table-td">{{ i.quantity_received }} / {{ i.quantity }}</td>
                    </tr>
                </tbody>
                <tfoot class="border-t border-gray-100">
                    <tr>
                        <td colspan="3" class="table-td text-right font-medium">Subtotal</td>
                        <td class="table-td font-medium" colspan="2">{{ Number(order.subtotal).toFixed(2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="table-td text-right font-medium">Tax</td>
                        <td class="table-td font-medium" colspan="2">{{ Number(order.tax).toFixed(2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="table-td text-right font-semibold">Total</td>
                        <td class="table-td font-semibold" colspan="2">{{ order.currency }} {{ Number(order.total).toFixed(2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div v-if="order.receipts?.length" class="card mb-4">
            <h3 class="font-semibold text-gray-900 mb-2">Goods Received Notes</h3>
            <ul class="text-sm space-y-1">
                <li v-for="r in order.receipts" :key="r.id" class="text-gray-600">{{ r.receipt_number }} — {{ new Date(r.created_at).toLocaleDateString() }}</li>
            </ul>
        </div>

        <div v-if="order.delivered_at" class="card mb-4">
            <h3 class="font-semibold text-gray-900 mb-2">Delivery &amp; Invoice</h3>
            <div class="text-sm text-gray-600 space-y-1">
                <div>Confirmed by {{ order.delivery_confirmed_by?.name }} on {{ new Date(order.delivered_at).toLocaleDateString() }}</div>
                <div v-if="order.invoice_reference">Invoice: {{ order.invoice_reference }} ({{ order.invoice_date }})</div>
                <div v-if="order.delivery_notes" class="text-gray-500">{{ order.delivery_notes }}</div>
            </div>
        </div>

        <div v-if="order.payment_requisitions?.length" class="card mb-4">
            <h3 class="font-semibold text-gray-900 mb-2">Payment Requisitions</h3>
            <ul class="text-sm divide-y divide-gray-50">
                <li v-for="p in order.payment_requisitions" :key="p.id" class="py-2 flex items-center justify-between">
                    <Link :href="`/procurement-payments/${p.id}`" class="text-brand-600 hover:underline">{{ p.payment_number }}</Link>
                    <div class="flex items-center gap-2">
                        <span>{{ Number(p.amount).toFixed(2) }}</span>
                        <span class="badge bg-gray-100 text-gray-700">{{ p.status.replace(/_/g, ' ') }}</span>
                    </div>
                </li>
            </ul>
        </div>

        <div v-if="showReject" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Reject Purchase Order</h3>
                <label class="label">Reason</label>
                <textarea v-model="rejectForm.reason" class="input" rows="3" required></textarea>
                <p v-if="rejectForm.errors.reason" class="mt-1 text-xs text-red-600">{{ rejectForm.errors.reason }}</p>
                <div class="flex gap-2 justify-end mt-4">
                    <button @click="showReject = false" class="btn-secondary">Cancel</button>
                    <button @click="submitReject" class="btn-danger" :disabled="rejectForm.processing">Reject</button>
                </div>
            </div>
        </div>

        <div v-if="showCancel" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Cancel Purchase Order</h3>
                <label class="label">Reason</label>
                <textarea v-model="cancelForm.reason" class="input" rows="3" required></textarea>
                <p v-if="cancelForm.errors.reason" class="mt-1 text-xs text-red-600">{{ cancelForm.errors.reason }}</p>
                <div class="flex gap-2 justify-end mt-4">
                    <button @click="showCancel = false" class="btn-secondary">Back</button>
                    <button @click="submitCancel" class="btn-danger" :disabled="cancelForm.processing">Cancel Order</button>
                </div>
            </div>
        </div>

        <div v-if="showReceive" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[90vh] flex flex-col">
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">Receive Goods</h3>
                </div>
                <div class="overflow-y-auto flex-1 px-6 py-4 space-y-3">
                    <div v-for="(l, i) in receiveForm.lines" :key="l.purchase_order_item_id" class="flex items-center gap-2">
                        <div class="flex-1 text-sm">{{ l.label }}</div>
                        <input v-model.number="receiveForm.lines[i].quantity" type="number" min="0" :max="l.max" class="input w-24" />
                    </div>
                    <p v-if="!receiveForm.lines.length" class="text-sm text-gray-400">Nothing left to receive.</p>
                </div>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="showReceive = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="submitReceive" class="btn-primary" :disabled="receiveForm.processing || !receiveForm.lines.length">Receive</button>
                </div>
            </div>
        </div>

        <div v-if="showDelivery" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-1">Confirm Delivery / Invoice</h3>
                <p class="text-xs text-gray-400 mb-3">Stage 10 — confirms goods/services were delivered and records the supplier invoice.</p>
                <div class="space-y-3">
                    <div>
                        <label class="label">Invoice Reference</label>
                        <input v-model="deliveryForm.invoice_reference" class="input" />
                    </div>
                    <div>
                        <label class="label">Invoice Date</label>
                        <input v-model="deliveryForm.invoice_date" type="date" class="input" />
                    </div>
                    <div>
                        <label class="label">Notes</label>
                        <textarea v-model="deliveryForm.notes" class="input" rows="2"></textarea>
                    </div>
                </div>
                <div class="flex gap-2 justify-end mt-4">
                    <button @click="showDelivery = false" class="btn-secondary">Cancel</button>
                    <button @click="submitDelivery" class="btn-primary" :disabled="deliveryForm.processing">Confirm</button>
                </div>
            </div>
        </div>

        <div v-if="showPayment" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-1">Raise Payment Requisition</h3>
                <p class="text-xs text-gray-400 mb-3">Stage 11 — goes to Head of Finance for review, then Executive Director for approval.</p>
                <div class="space-y-3">
                    <div>
                        <label class="label">Payee</label>
                        <input v-model="paymentForm.payee_name" class="input" required />
                    </div>
                    <div>
                        <label class="label">Amount</label>
                        <input v-model.number="paymentForm.amount" type="number" min="0.01" step="0.01" class="input" required />
                    </div>
                    <div>
                        <label class="label">Payment Method</label>
                        <select v-model="paymentForm.payment_method" class="input">
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="cash">Cash</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Description</label>
                        <textarea v-model="paymentForm.description" class="input" rows="2"></textarea>
                    </div>
                </div>
                <div class="flex gap-2 justify-end mt-4">
                    <button @click="showPayment = false" class="btn-secondary">Cancel</button>
                    <button @click="submitPayment" class="btn-primary" :disabled="paymentForm.processing">Create</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
