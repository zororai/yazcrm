<script setup>
import { ref } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ requisition: Object, isReviewer: Boolean, isApprover: Boolean, isOwner: Boolean });

const statusColor = {
    draft: 'bg-gray-200 text-gray-600',
    pending_review: 'bg-amber-100 text-amber-800',
    pending_approval: 'bg-blue-100 text-blue-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-200 text-gray-500',
};

function act(action, message) {
    if (! confirm(message)) return;
    router.post(`/procurement-requisitions/${props.requisition.id}/${action}`);
}

const showReview = ref(false);
const reviewForm = useForm({ notes: '' });
function submitReview() {
    reviewForm.post(`/procurement-requisitions/${props.requisition.id}/review`, { onSuccess: () => { showReview.value = false; reviewForm.reset(); } });
}

const showApprove = ref(false);
const approveForm = useForm({ notes: '' });
function submitApprove() {
    approveForm.post(`/procurement-requisitions/${props.requisition.id}/approve`, { onSuccess: () => { showApprove.value = false; approveForm.reset(); } });
}

const showReject = ref(false);
const rejectStage = ref('review'); // 'review' or 'approval'
const rejectForm = useForm({ reason: '' });
function openReject(stage) {
    rejectStage.value = stage;
    showReject.value = true;
}
function submitReject() {
    const action = rejectStage.value === 'review' ? 'reject-at-review' : 'reject-at-approval';
    rejectForm.post(`/procurement-requisitions/${props.requisition.id}/${action}`, { onSuccess: () => { showReject.value = false; rejectForm.reset(); } });
}

const showCancel = ref(false);
const cancelForm = useForm({ reason: '' });
function submitCancel() {
    cancelForm.post(`/procurement-requisitions/${props.requisition.id}/cancel`, { onSuccess: () => { showCancel.value = false; cancelForm.reset(); } });
}
</script>

<template>
    <AppLayout>
        <template #title>{{ requisition.requisition_number }}</template>
        <template #subtitle>{{ requisition.title }}</template>
        <template #header-actions>
            <div class="flex gap-2 flex-wrap">
                <button v-if="isOwner && requisition.status === 'draft'" @click="act('submit', 'Submit this requisition for review?')" class="btn-primary btn-sm">Submit for Review</button>

                <button v-if="isReviewer && requisition.status === 'pending_review'" @click="showReview = true" class="btn-primary btn-sm">Review &amp; Send to Approval</button>
                <button v-if="isReviewer && requisition.status === 'pending_review'" @click="openReject('review')" class="btn-secondary btn-sm">Reject</button>

                <button v-if="isApprover && requisition.status === 'pending_approval'" @click="showApprove = true" class="btn-primary btn-sm">Approve</button>
                <button v-if="isApprover && requisition.status === 'pending_approval'" @click="openReject('approval')" class="btn-secondary btn-sm">Reject</button>

                <button v-if="(isOwner || isApprover) && !['approved','rejected','cancelled'].includes(requisition.status)" @click="showCancel = true" class="btn-secondary btn-sm">Cancel</button>

                <Link v-if="requisition.status === 'approved'" :href="`/procurement-requisitions/${requisition.id}/selection`" class="btn-primary btn-sm">Vendor Selection →</Link>
            </div>
        </template>

        <div class="card mb-4">
            <div class="flex flex-wrap justify-between gap-4">
                <div>
                    <div class="text-sm text-gray-500">Requested By</div>
                    <div class="font-medium">{{ requisition.requested_by?.name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Department</div>
                    <div class="font-medium">{{ requisition.department?.name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Required By</div>
                    <div class="font-medium">{{ requisition.required_by_date ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Reviewed By (Head of Programs)</div>
                    <div class="font-medium">{{ requisition.reviewed_by?.name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Approved By (Executive Director)</div>
                    <div class="font-medium">{{ requisition.approved_by?.name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Status</div>
                    <span :class="['badge', statusColor[requisition.status]]">{{ requisition.status.replace('_', ' ') }}</span>
                </div>
            </div>
            <p v-if="requisition.justification" class="mt-3 text-sm text-gray-600">{{ requisition.justification }}</p>
            <p v-if="requisition.review_notes" class="mt-2 text-sm"><span class="text-gray-500">Review notes:</span> {{ requisition.review_notes }}</p>
            <p v-if="requisition.approval_notes" class="mt-2 text-sm"><span class="text-gray-500">Approval notes:</span> {{ requisition.approval_notes }}</p>
        </div>

        <div class="card p-0 overflow-hidden mb-4">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Item</th>
                        <th class="table-th">Qty</th>
                        <th class="table-th">Est. Unit Cost</th>
                        <th class="table-th">Line Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="i in requisition.items" :key="i.id">
                        <td class="table-td">{{ i.item?.name ?? i.description ?? '—' }}</td>
                        <td class="table-td">{{ i.quantity }}</td>
                        <td class="table-td">{{ Number(i.estimated_unit_cost).toFixed(2) }}</td>
                        <td class="table-td">{{ Number(i.line_total).toFixed(2) }}</td>
                    </tr>
                </tbody>
                <tfoot class="border-t border-gray-100">
                    <tr>
                        <td colspan="3" class="table-td text-right font-semibold">Estimated Total</td>
                        <td class="table-td font-semibold">{{ requisition.currency }} {{ Number(requisition.estimated_total).toFixed(2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div v-if="requisition.activity_logs?.length" class="card">
            <h3 class="font-semibold text-gray-900 mb-2">History</h3>
            <ul class="text-sm divide-y divide-gray-50">
                <li v-for="log in requisition.activity_logs" :key="log.id" class="py-2 flex items-center justify-between">
                    <div>
                        <span class="font-medium">{{ log.action.replace(/_/g, ' ') }}</span>
                        <span class="text-gray-400 ml-2">by {{ log.user?.name ?? 'System' }}</span>
                        <p v-if="log.notes" class="text-xs text-gray-500">{{ log.notes }}</p>
                    </div>
                    <span class="text-xs text-gray-400">{{ new Date(log.created_at).toLocaleString() }}</span>
                </li>
            </ul>
        </div>

        <div v-if="showReview" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Review — Send to Executive Director</h3>
                <label class="label">Notes (optional)</label>
                <textarea v-model="reviewForm.notes" class="input" rows="3"></textarea>
                <div class="flex gap-2 justify-end mt-4">
                    <button @click="showReview = false" class="btn-secondary">Cancel</button>
                    <button @click="submitReview" class="btn-primary" :disabled="reviewForm.processing">Send for Approval</button>
                </div>
            </div>
        </div>

        <div v-if="showApprove" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Approve Requisition</h3>
                <label class="label">Notes (optional)</label>
                <textarea v-model="approveForm.notes" class="input" rows="3"></textarea>
                <div class="flex gap-2 justify-end mt-4">
                    <button @click="showApprove = false" class="btn-secondary">Cancel</button>
                    <button @click="submitApprove" class="btn-primary" :disabled="approveForm.processing">Approve</button>
                </div>
            </div>
        </div>

        <div v-if="showReject" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Reject Requisition</h3>
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
                <h3 class="font-semibold text-gray-900 mb-3">Cancel Requisition</h3>
                <label class="label">Reason</label>
                <textarea v-model="cancelForm.reason" class="input" rows="3" required></textarea>
                <p v-if="cancelForm.errors.reason" class="mt-1 text-xs text-red-600">{{ cancelForm.errors.reason }}</p>
                <div class="flex gap-2 justify-end mt-4">
                    <button @click="showCancel = false" class="btn-secondary">Back</button>
                    <button @click="submitCancel" class="btn-danger" :disabled="cancelForm.processing">Cancel Requisition</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
