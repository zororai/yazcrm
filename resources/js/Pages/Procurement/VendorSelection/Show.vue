<script setup>
import { ref } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { TrashIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    requisition: Object, suppliers: Array,
    isProcurementTeam: Boolean, isHop: Boolean, isHof: Boolean, isApprover: Boolean,
});

const bid = props.requisition.bid_process;

const statusColor = {
    draft: 'bg-gray-200 text-gray-600',
    bids_requested: 'bg-amber-100 text-amber-800',
    evaluated: 'bg-blue-100 text-blue-800',
    hop_reviewed: 'bg-indigo-100 text-indigo-800',
    hof_reviewed: 'bg-purple-100 text-purple-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-200 text-gray-500',
};

const methodLabel = {
    request_for_quotes: 'Request for Quotes',
    sole_source: 'Sole Source',
    public_invitation: 'Public Invitation',
};

// ── Stage 4: start bid process ──────────────────────────────────────────
const startForm = useForm({
    bid_method: 'request_for_quotes', bid_reference: '',
    quotes: [{ supplier_id: '', quoted_amount: '', notes: '' }],
});
function addQuoteLine() { startForm.quotes.push({ supplier_id: '', quoted_amount: '', notes: '' }); }
function removeQuoteLine(i) { startForm.quotes.splice(i, 1); }
function submitStart() {
    startForm.post(`/procurement-requisitions/${props.requisition.id}/selection`);
}

// ── Stage 5: evaluate ────────────────────────────────────────────────────
const evalForm = useForm({ recommended_supplier_id: '', notes: '' });
function submitEvaluate() {
    evalForm.post(`/procurement-bids/${bid.id}/evaluate`);
}

// ── Stages 6-8: review/approve ───────────────────────────────────────────
function act(action, message) {
    if (! confirm(message)) return;
    router.post(`/procurement-bids/${bid.id}/${action}`);
}

const showReject = ref(false);
const rejectForm = useForm({ reason: '' });
function submitReject() {
    rejectForm.post(`/procurement-bids/${bid.id}/reject`, { onSuccess: () => { showReject.value = false; rejectForm.reset(); } });
}

const showCancel = ref(false);
const cancelForm = useForm({ reason: '' });
function submitCancel() {
    cancelForm.post(`/procurement-bids/${bid.id}/cancel`, { onSuccess: () => { showCancel.value = false; cancelForm.reset(); } });
}
</script>

<template>
    <AppLayout>
        <template #title>Vendor Selection</template>
        <template #subtitle>{{ requisition.requisition_number }} — {{ requisition.title }}</template>
        <template #header-actions>
            <div class="flex gap-2 flex-wrap">
                <Link :href="`/procurement-requisitions/${requisition.id}`" class="btn-secondary btn-sm inline-flex items-center gap-1">
                    <ArrowLeftIcon class="h-4 w-4" /> Back to Requisition
                </Link>
                <button v-if="bid && !['approved','rejected','cancelled'].includes(bid.status)" @click="showCancel = true" class="btn-secondary btn-sm">Cancel Selection</button>
            </div>
        </template>

        <!-- Stage 4: no bid process yet — start one -->
        <div v-if="!bid" class="card max-w-2xl">
            <h3 class="font-semibold text-gray-900 mb-1">Stage 4 — Request for Bids</h3>
            <p class="text-xs text-gray-400 mb-4">Choose how you're sourcing this, and list the suppliers being asked to quote (if applicable).</p>

            <div v-if="!isProcurementTeam" class="text-sm text-gray-400 italic">Only the procurement team can start vendor selection for this requisition.</div>
            <form v-else @submit.prevent="submitStart" class="space-y-3">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="label">Bid Method</label>
                        <select v-model="startForm.bid_method" class="input">
                            <option value="request_for_quotes">Request for Quotes</option>
                            <option value="sole_source">Sole Source</option>
                            <option value="public_invitation">Public Invitation</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Reference (tender/RFQ #)</label>
                        <input v-model="startForm.bid_reference" class="input" placeholder="Optional" />
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="label mb-0">Suppliers Invited to Quote</label>
                        <button type="button" @click="addQuoteLine" class="text-xs text-brand-600 hover:underline">+ Add supplier</button>
                    </div>
                    <div v-for="(line, i) in startForm.quotes" :key="i" class="grid grid-cols-12 gap-2 items-center mb-2">
                        <select v-model="line.supplier_id" class="input col-span-5">
                            <option value="">Select supplier…</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <input v-model.number="line.quoted_amount" type="number" min="0" step="0.01" class="input col-span-3" placeholder="Quoted amount" />
                        <input v-model="line.notes" class="input col-span-3" placeholder="Notes" />
                        <button type="button" @click="removeQuoteLine(i)" class="col-span-1 text-gray-400 hover:text-red-600">
                            <TrashIcon class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary" :disabled="startForm.processing">Request Bids</button>
            </form>
        </div>

        <template v-else>
            <div class="card mb-4">
                <div class="flex flex-wrap justify-between gap-4">
                    <div>
                        <div class="text-sm text-gray-500">Bid Method</div>
                        <div class="font-medium">{{ methodLabel[bid.bid_method] ?? bid.bid_method }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Reference</div>
                        <div class="font-medium">{{ bid.bid_reference || '—' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Recommended Supplier</div>
                        <div class="font-medium">{{ bid.recommended_supplier?.name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Status</div>
                        <span :class="['badge', statusColor[bid.status]]">{{ bid.status.replace(/_/g, ' ') }}</span>
                    </div>
                </div>
            </div>

            <!-- Quotes table -->
            <div class="card p-0 overflow-hidden mb-4">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="table-th">Supplier</th>
                            <th class="table-th">Quoted Amount</th>
                            <th class="table-th">Notes</th>
                            <th class="table-th">Recommended</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="q in bid.quotes" :key="q.id">
                            <td class="table-td font-medium">{{ q.supplier?.name }}</td>
                            <td class="table-td">{{ q.quoted_amount !== null ? Number(q.quoted_amount).toFixed(2) : '—' }}</td>
                            <td class="table-td">{{ q.notes || '—' }}</td>
                            <td class="table-td">
                                <span v-if="q.is_recommended" class="badge bg-green-100 text-green-800">Recommended</span>
                            </td>
                        </tr>
                        <tr v-if="!bid.quotes?.length">
                            <td colspan="4" class="table-td text-center text-gray-400 py-6">No supplier quotes recorded.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Stage 5: Evaluation -->
            <div v-if="bid.status === 'bids_requested'" class="card max-w-xl mb-4">
                <h3 class="font-semibold text-gray-900 mb-1">Stage 5 — Evaluation &amp; Selection</h3>
                <div v-if="!isProcurementTeam" class="text-sm text-gray-400 italic">Only the procurement/evaluation team can evaluate bids.</div>
                <form v-else @submit.prevent="submitEvaluate" class="space-y-3">
                    <div>
                        <label class="label">Recommended Supplier / Vendor / Contractor</label>
                        <select v-model="evalForm.recommended_supplier_id" class="input" required>
                            <option value="">Select…</option>
                            <option v-for="q in bid.quotes" :key="q.supplier_id" :value="q.supplier_id">{{ q.supplier?.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Evaluation Notes</label>
                        <textarea v-model="evalForm.notes" class="input" rows="3" required placeholder="Basis for the recommendation"></textarea>
                    </div>
                    <button type="submit" class="btn-primary" :disabled="evalForm.processing">Submit Recommendation</button>
                </form>
            </div>

            <!-- Stage 6: HoP review -->
            <div v-if="bid.status === 'evaluated'" class="card max-w-xl mb-4">
                <h3 class="font-semibold text-gray-900 mb-1">Stage 6 — Head of Programs Review</h3>
                <p class="text-sm text-gray-600 mb-3">Reviewing recommendation of <strong>{{ bid.recommended_supplier?.name }}</strong>.</p>
                <div v-if="!isHop" class="text-sm text-gray-400 italic">Waiting on the Head of Programs.</div>
                <div v-else class="flex gap-2">
                    <button @click="act('hop-review', 'Confirm review and send to Head of Finance?')" class="btn-primary btn-sm">Review &amp; Forward</button>
                    <button @click="showReject = true" class="btn-secondary btn-sm">Reject</button>
                </div>
            </div>

            <!-- Stage 7: HoF review -->
            <div v-if="bid.status === 'hop_reviewed'" class="card max-w-xl mb-4">
                <h3 class="font-semibold text-gray-900 mb-1">Stage 7 — Head of Finance Review &amp; Documentation</h3>
                <div v-if="!isHof" class="text-sm text-gray-400 italic">Waiting on the Head of Finance.</div>
                <div v-else class="flex gap-2">
                    <button @click="act('hof-review', 'Confirm documentation is complete and forward for approval?')" class="btn-primary btn-sm">Confirm &amp; Forward</button>
                    <button @click="showReject = true" class="btn-secondary btn-sm">Reject</button>
                </div>
            </div>

            <!-- Stage 8: ED/Board approval -->
            <div v-if="bid.status === 'hof_reviewed'" class="card max-w-xl mb-4">
                <h3 class="font-semibold text-gray-900 mb-1">Stage 8 — Executive Director / Board Approval</h3>
                <div v-if="!isApprover" class="text-sm text-gray-400 italic">Waiting on the Executive Director / Board.</div>
                <div v-else class="flex gap-2">
                    <button @click="act('approve', 'Approve this vendor?')" class="btn-primary btn-sm">Approve Vendor</button>
                    <button @click="showReject = true" class="btn-secondary btn-sm">Reject</button>
                </div>
            </div>

            <div v-if="bid.status === 'approved'" class="card max-w-xl mb-4 bg-green-50 border-green-200">
                <p class="text-sm text-green-800">Vendor approved — <strong>{{ bid.recommended_supplier?.name }}</strong> can now proceed to Purchase Order / Contract issuance (Stage 9).</p>
            </div>

            <div v-if="bid.activity_logs?.length" class="card">
                <h3 class="font-semibold text-gray-900 mb-2">History</h3>
                <ul class="text-sm divide-y divide-gray-50">
                    <li v-for="log in bid.activity_logs" :key="log.id" class="py-2 flex items-center justify-between">
                        <div>
                            <span class="font-medium">{{ log.action.replace(/_/g, ' ') }}</span>
                            <span class="text-gray-400 ml-2">by {{ log.user?.name ?? 'System' }}</span>
                            <p v-if="log.notes" class="text-xs text-gray-500">{{ log.notes }}</p>
                        </div>
                        <span class="text-xs text-gray-400">{{ new Date(log.created_at).toLocaleString() }}</span>
                    </li>
                </ul>
            </div>
        </template>

        <div v-if="showReject" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Reject Vendor Selection</h3>
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
                <h3 class="font-semibold text-gray-900 mb-3">Cancel Vendor Selection</h3>
                <label class="label">Reason</label>
                <textarea v-model="cancelForm.reason" class="input" rows="3" required></textarea>
                <div class="flex gap-2 justify-end mt-4">
                    <button @click="showCancel = false" class="btn-secondary">Back</button>
                    <button @click="submitCancel" class="btn-danger" :disabled="cancelForm.processing">Cancel Selection</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
