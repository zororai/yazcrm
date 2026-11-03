<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PaperClipIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    requests: Array,
    people:   Array,
    filters:  Object,
    isAdmin:  Boolean,
});

const filters = ref({ ...props.filters });
watch(filters, () => {
    router.get('/leave/requests', {
        status:  filters.value.status || undefined,
        user_id: filters.value.user_id || undefined,
        year:    filters.value.year || undefined,
    }, { preserveState: true, replace: true });
}, { deep: true });

const statusColor = {
    pending:   'bg-amber-100 text-amber-800',
    approved:  'bg-green-100 text-green-800',
    rejected:  'bg-red-100 text-red-800',
    cancelled: 'bg-gray-100 text-gray-600',
};

const fmtDate = (d) => new Date(d + 'T00:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });

// ── Approve / reject ─────────────────────────────────────────────────────────
const deciding = ref(null);   // { request, action }
const decideForm = useForm({ comment: '' });

function openDecide(request, action) {
    decideForm.reset();
    decideForm.clearErrors();
    deciding.value = { request, action };
}

function submitDecide() {
    const { request, action } = deciding.value;
    decideForm.patch(`/leave/requests/${request.id}/${action}`, {
        preserveScroll: true,
        onSuccess: () => { deciding.value = null; },
    });
}

// ── Correct (leave admins) ───────────────────────────────────────────────────
const correcting = ref(null);
const correctForm = useForm({ status: '', reason: '', comment: '' });

function openCorrect(request) {
    correctForm.reset();
    correctForm.clearErrors();
    correctForm.status = request.status === 'approved' ? 'rejected' : 'approved';
    correctForm.comment = request.approver_comment ?? '';
    correcting.value = request;
}

function submitCorrect() {
    correctForm.patch(`/leave/requests/${correcting.value.id}/correct`, {
        preserveScroll: true,
        onSuccess: () => { correcting.value = null; },
    });
}
</script>

<template>
    <AppLayout>
        <template #title>Leave Requests</template>
        <template #subtitle>
            {{ isAdmin ? 'Everyone\'s leave. Nobody can decide their own request.' : 'Leave requests from the people who report to you.' }}
        </template>

        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="label">Status</label>
                <select v-model="filters.status" class="input">
                    <option value="">All</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div>
                <label class="label">Person</label>
                <select v-model="filters.user_id" class="input">
                    <option value="">Everyone</option>
                    <option v-for="p in people" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>
            <div>
                <label class="label">Year</label>
                <input v-model.number="filters.year" type="number" class="input w-28" />
            </div>
        </div>

        <div class="card p-0 overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Person</th>
                        <th class="table-th">Type</th>
                        <th class="table-th">Dates</th>
                        <th class="table-th text-right">Days</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Decision</th>
                        <th class="table-th" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in requests" :key="r.id" class="hover:bg-gray-50 align-top">
                        <td class="table-td font-medium">{{ r.user?.name }}</td>
                        <td class="table-td">
                            {{ r.leave_type?.name }}
                            <a v-if="r.has_attachment" :href="`/leave/requests/${r.id}/attachment`" class="ml-1 inline-flex text-gray-400 hover:text-blue-600" title="Download attachment">
                                <PaperClipIcon class="h-4 w-4" />
                            </a>
                        </td>
                        <td class="table-td whitespace-nowrap">
                            {{ fmtDate(r.start_date) }} – {{ fmtDate(r.end_date) }}
                            <p v-if="r.reason" class="text-xs text-gray-500 whitespace-normal max-w-xs">{{ r.reason }}</p>
                        </td>
                        <td class="table-td text-right">{{ r.days }}</td>
                        <td class="table-td">
                            <span class="badge capitalize" :class="statusColor[r.status]">{{ r.status }}</span>
                        </td>
                        <td class="table-td text-xs text-gray-600 max-w-xs">
                            <template v-if="r.approver">{{ r.approver.name }}<span v-if="r.approver_comment">: “{{ r.approver_comment }}”</span></template>
                            <p v-if="r.corrected_at" class="mt-1 text-amber-700">
                                Corrected by {{ r.corrector?.name }}: {{ r.correction_reason }}
                            </p>
                        </td>
                        <td class="table-td text-right whitespace-nowrap">
                            <template v-if="r.can?.decide">
                                <button class="btn-primary btn-sm mr-1" @click="openDecide(r, 'approve')">Approve</button>
                                <button class="btn-secondary btn-sm" @click="openDecide(r, 'reject')">Reject</button>
                            </template>
                            <button v-else-if="r.can_correct" class="btn-secondary btn-sm" @click="openCorrect(r)">Correct</button>
                        </td>
                    </tr>
                    <tr v-if="!requests.length">
                        <td colspan="7" class="table-td text-center text-gray-400 py-8">No leave requests match these filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Approve / reject -->
        <div v-if="deciding" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 class="font-semibold text-gray-900 mb-1">
                    {{ deciding.action === 'approve' ? 'Approve' : 'Reject' }} {{ deciding.request.user?.name }}'s leave
                </h3>
                <p class="text-sm text-gray-500 mb-4">
                    {{ deciding.request.leave_type?.name }}, {{ fmtDate(deciding.request.start_date) }} – {{ fmtDate(deciding.request.end_date) }} ({{ deciding.request.days }} days)
                </p>
                <form @submit.prevent="submitDecide" class="space-y-3">
                    <div>
                        <label class="label">Comment {{ deciding.action === 'reject' ? '(required)' : '(optional)' }}</label>
                        <textarea v-model="decideForm.comment" rows="3" class="input" :required="deciding.action === 'reject'" />
                        <p v-for="key in ['comment', 'days', 'status']" :key="key" v-show="decideForm.errors[key]" class="mt-1 text-xs text-red-600">{{ decideForm.errors[key] }}</p>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button type="button" class="btn-secondary" @click="deciding = null">Cancel</button>
                        <button type="submit" :class="deciding.action === 'approve' ? 'btn-primary' : 'btn-danger'" :disabled="decideForm.processing">
                            {{ deciding.action === 'approve' ? 'Approve' : 'Reject' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Correct a decision -->
        <div v-if="correcting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 class="font-semibold text-gray-900 mb-1">Correct a decision</h3>
                <p class="text-sm text-gray-500 mb-4">
                    {{ correcting.user?.name }}, {{ correcting.leave_type?.name }} {{ fmtDate(correcting.start_date) }} – {{ fmtDate(correcting.end_date) }}.
                    Currently <strong>{{ correcting.status }}</strong>. The old and new values and your reason go to the audit trail.
                </p>
                <form @submit.prevent="submitCorrect" class="space-y-3">
                    <div>
                        <label class="label">Change status to</label>
                        <select v-model="correctForm.status" class="input">
                            <option v-for="s in ['approved', 'rejected', 'cancelled'].filter(s => s !== correcting.status)" :key="s" :value="s" class="capitalize">{{ s }}</option>
                        </select>
                        <p v-if="correctForm.errors.status" class="mt-1 text-xs text-red-600">{{ correctForm.errors.status }}</p>
                        <p v-if="correctForm.errors.days" class="mt-1 text-xs text-red-600">{{ correctForm.errors.days }}</p>
                    </div>
                    <div>
                        <label class="label">Reason for the correction (required)</label>
                        <textarea v-model="correctForm.reason" rows="3" class="input" required minlength="5" />
                        <p v-if="correctForm.errors.reason" class="mt-1 text-xs text-red-600">{{ correctForm.errors.reason }}</p>
                    </div>
                    <div>
                        <label class="label">Comment shown to the employee</label>
                        <textarea v-model="correctForm.comment" rows="2" class="input" />
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button type="button" class="btn-secondary" @click="correcting = null">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="correctForm.processing">Save correction</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
