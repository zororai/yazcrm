<script setup>
import { ref } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ payment: Object, isOwner: Boolean, isHof: Boolean, isApprover: Boolean, isFinance: Boolean });

const statusColor = {
    draft: 'bg-gray-200 text-gray-600',
    pending_review: 'bg-amber-100 text-amber-800',
    pending_approval: 'bg-blue-100 text-blue-800',
    approved: 'bg-indigo-100 text-indigo-800',
    loaded: 'bg-purple-100 text-purple-800',
    released: 'bg-teal-100 text-teal-800',
    recorded: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-200 text-gray-500',
};

function act(action, message) {
    if (! confirm(message)) return;
    router.post(`/procurement-payments/${props.payment.id}/${action}`);
}

const showReject = ref(false);
const rejectStage = ref('review');
const rejectForm = useForm({ reason: '' });
function openReject(stage) {
    rejectStage.value = stage;
    showReject.value = true;
}
function submitReject() {
    const action = rejectStage.value === 'review' ? 'reject-at-review' : 'reject-at-approval';
    rejectForm.post(`/procurement-payments/${props.payment.id}/${action}`, { onSuccess: () => { showReject.value = false; rejectForm.reset(); } });
}

const showLoad = ref(false);
const loadForm = useForm({ bank_reference: '', notes: '' });
function submitLoad() {
    loadForm.post(`/procurement-payments/${props.payment.id}/load-to-bank`, { onSuccess: () => { showLoad.value = false; loadForm.reset(); } });
}

const showRecord = ref(false);
const recordForm = useForm({ recording_reference: '', notes: '' });
function submitRecord() {
    recordForm.post(`/procurement-payments/${props.payment.id}/record`, { onSuccess: () => { showRecord.value = false; recordForm.reset(); } });
}

const showCancel = ref(false);
const cancelForm = useForm({ reason: '' });
function submitCancel() {
    cancelForm.post(`/procurement-payments/${props.payment.id}/cancel`, { onSuccess: () => { showCancel.value = false; cancelForm.reset(); } });
}
</script>

<template>
    <AppLayout>
        <template #title>{{ payment.payment_number }}</template>
        <template #subtitle>{{ payment.payee_name }}</template>
        <template #header-actions>
            <div class="flex gap-2 flex-wrap">
                <Link v-if="payment.purchase_order" :href="`/purchase-orders/${payment.purchase_order.id}`" class="btn-secondary btn-sm inline-flex items-center gap-1">
                    <ArrowLeftIcon class="h-4 w-4" /> Back to PO
                </Link>

                <button v-if="isOwner && payment.status === 'draft'" @click="act('submit', 'Submit this payment requisition for review?')" class="btn-primary btn-sm">Submit for Review</button>

                <button v-if="isHof && payment.status === 'pending_review'" @click="act('review', 'Confirm review and send to Executive Director?')" class="btn-primary btn-sm">Review &amp; Forward</button>
                <button v-if="isHof && payment.status === 'pending_review'" @click="openReject('review')" class="btn-secondary btn-sm">Reject</button>

                <button v-if="isApprover && payment.status === 'pending_approval'" @click="act('approve', 'Approve this payment?')" class="btn-primary btn-sm">Approve</button>
                <button v-if="isApprover && payment.status === 'pending_approval'" @click="openReject('approval')" class="btn-secondary btn-sm">Reject</button>

                <button v-if="isHof && payment.status === 'approved'" @click="showLoad = true" class="btn-primary btn-sm">Load to Bank</button>
                <button v-if="isApprover && payment.status === 'loaded'" @click="act('release', 'Authorize and release this payment?')" class="btn-primary btn-sm">Authorize &amp; Release</button>
                <button v-if="isFinance && payment.status === 'released'" @click="showRecord = true" class="btn-primary btn-sm">Record Payment</button>

                <button v-if="(isOwner || isApprover) && !['loaded','released','recorded','rejected','cancelled'].includes(payment.status)" @click="showCancel = true" class="btn-secondary btn-sm">Cancel</button>
            </div>
        </template>

        <div class="card mb-4">
            <div class="flex flex-wrap justify-between gap-4">
                <div>
                    <div class="text-sm text-gray-500">Purchase Order</div>
                    <div class="font-medium">{{ payment.purchase_order?.po_number ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Prepared By (IO)</div>
                    <div class="font-medium">{{ payment.prepared_by?.name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Amount</div>
                    <div class="font-medium">{{ payment.currency }} {{ Number(payment.amount).toFixed(2) }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Method</div>
                    <div class="font-medium">{{ (payment.payment_method ?? '—').replace(/_/g, ' ') }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Status</div>
                    <span :class="['badge', statusColor[payment.status]]">{{ payment.status.replace(/_/g, ' ') }}</span>
                </div>
            </div>
            <p v-if="payment.description" class="mt-3 text-sm text-gray-600">{{ payment.description }}</p>
        </div>

        <div class="card mb-4">
            <h3 class="font-semibold text-gray-900 mb-3 text-sm">Payment Chain</h3>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <dt class="text-gray-400 text-xs">Reviewed (Head of Finance)</dt>
                    <dd class="font-medium">{{ payment.reviewed_by?.name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs">Approved (Executive Director)</dt>
                    <dd class="font-medium">{{ payment.approved_by?.name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs">Loaded to Bank (Head of Finance)</dt>
                    <dd class="font-medium">{{ payment.loaded_by?.name ?? '—' }} <span v-if="payment.bank_reference" class="text-gray-400">({{ payment.bank_reference }})</span></dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs">Released (ED / Board)</dt>
                    <dd class="font-medium">{{ payment.released_by?.name ?? '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-gray-400 text-xs">Recorded (Finance Officer)</dt>
                    <dd class="font-medium">{{ payment.recorded_by?.name ?? '—' }} <span v-if="payment.recording_reference" class="text-gray-400">({{ payment.recording_reference }})</span></dd>
                </div>
            </dl>
        </div>

        <div v-if="payment.activity_logs?.length" class="card">
            <h3 class="font-semibold text-gray-900 mb-2">History</h3>
            <ul class="text-sm divide-y divide-gray-50">
                <li v-for="log in payment.activity_logs" :key="log.id" class="py-2 flex items-center justify-between">
                    <div>
                        <span class="font-medium">{{ log.action.replace(/_/g, ' ') }}</span>
                        <span class="text-gray-400 ml-2">by {{ log.user?.name ?? 'System' }}</span>
                        <p v-if="log.notes" class="text-xs text-gray-500">{{ log.notes }}</p>
                    </div>
                    <span class="text-xs text-gray-400">{{ new Date(log.created_at).toLocaleString() }}</span>
                </li>
            </ul>
        </div>

        <div v-if="showReject" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Reject Payment Requisition</h3>
                <label class="label">Reason</label>
                <textarea v-model="rejectForm.reason" class="input" rows="3" required></textarea>
                <p v-if="rejectForm.errors.reason" class="mt-1 text-xs text-red-600">{{ rejectForm.errors.reason }}</p>
                <div class="flex gap-2 justify-end mt-4">
                    <button @click="showReject = false" class="btn-secondary">Cancel</button>
                    <button @click="submitReject" class="btn-danger" :disabled="rejectForm.processing">Reject</button>
                </div>
            </div>
        </div>

        <div v-if="showLoad" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-1">Load to Banking System</h3>
                <p class="text-xs text-gray-400 mb-3">Stage 12 — Head of Finance.</p>
                <label class="label">Bank Reference</label>
                <input v-model="loadForm.bank_reference" class="input" required />
                <label class="label mt-2">Notes</label>
                <textarea v-model="loadForm.notes" class="input" rows="2"></textarea>
                <div class="flex gap-2 justify-end mt-4">
                    <button @click="showLoad = false" class="btn-secondary">Cancel</button>
                    <button @click="submitLoad" class="btn-primary" :disabled="loadForm.processing">Load</button>
                </div>
            </div>
        </div>

        <div v-if="showRecord" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-1">Record Payment</h3>
                <p class="text-xs text-gray-400 mb-3">Stage 14 — Finance Officer. Final step.</p>
                <label class="label">Recording Reference</label>
                <input v-model="recordForm.recording_reference" class="input" />
                <label class="label mt-2">Notes</label>
                <textarea v-model="recordForm.notes" class="input" rows="2"></textarea>
                <div class="flex gap-2 justify-end mt-4">
                    <button @click="showRecord = false" class="btn-secondary">Cancel</button>
                    <button @click="submitRecord" class="btn-primary" :disabled="recordForm.processing">Record</button>
                </div>
            </div>
        </div>

        <div v-if="showCancel" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Cancel Payment Requisition</h3>
                <label class="label">Reason</label>
                <textarea v-model="cancelForm.reason" class="input" rows="3" required></textarea>
                <div class="flex gap-2 justify-end mt-4">
                    <button @click="showCancel = false" class="btn-secondary">Back</button>
                    <button @click="submitCancel" class="btn-danger" :disabled="cancelForm.processing">Cancel Requisition</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
