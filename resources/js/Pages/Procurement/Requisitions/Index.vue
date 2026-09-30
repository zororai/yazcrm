<script setup>
import { ref, watch, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    requisitions: Array, departments: Array, items: Array, isReviewer: Boolean, isApprover: Boolean,
});

const filters = ref({ status: '' });
let debounce;
watch(filters, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/procurement-requisitions', { status: filters.value.status || undefined }, { preserveState: true, replace: true });
    }, 200);
}, { deep: true });

const showForm = ref(false);
const form = useForm({
    department_id: '', title: '', justification: '', required_by_date: '',
    lines: [{ item_id: '', description: '', quantity: 1, estimated_unit_cost: 0 }],
});

function addLine() {
    form.lines.push({ item_id: '', description: '', quantity: 1, estimated_unit_cost: 0 });
}
function removeLine(i) {
    form.lines.splice(i, 1);
}

const estimatedTotal = computed(() => form.lines.reduce((sum, l) => sum + (Number(l.quantity) || 0) * (Number(l.estimated_unit_cost) || 0), 0));

function submit() {
    form.post('/procurement-requisitions', { onSuccess: () => { showForm.value = false; form.reset(); } });
}

function open(r) {
    router.get(`/procurement-requisitions/${r.id}`);
}

const statusColor = {
    draft: 'bg-gray-200 text-gray-600',
    pending_review: 'bg-amber-100 text-amber-800',
    pending_approval: 'bg-blue-100 text-blue-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-200 text-gray-500',
};
</script>

<template>
    <AppLayout>
        <template #title>Procurement Requisitions</template>
        <template #header-actions>
            <button @click="showForm = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Requisition
            </button>
        </template>

        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="label">Status</label>
                <select v-model="filters.status" class="input">
                    <option value="">All</option>
                    <option value="draft">Draft</option>
                    <option value="pending_review">Pending Review</option>
                    <option value="pending_approval">Pending Approval</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <p class="text-xs text-gray-400 pb-2" v-if="!isReviewer && !isApprover">Showing your own requisitions.</p>
            <p class="text-xs text-gray-400 pb-2" v-else>Showing all requisitions (you can review/approve).</p>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Requisition #</th>
                        <th class="table-th">Title</th>
                        <th class="table-th">Department</th>
                        <th class="table-th">Requested By</th>
                        <th class="table-th">Estimated Total</th>
                        <th class="table-th">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in requisitions" :key="r.id" class="hover:bg-gray-50 cursor-pointer" @click="open(r)">
                        <td class="table-td font-medium">{{ r.requisition_number }}</td>
                        <td class="table-td">{{ r.title }}</td>
                        <td class="table-td">{{ r.department?.name ?? '—' }}</td>
                        <td class="table-td">{{ r.requested_by?.name ?? '—' }}</td>
                        <td class="table-td">{{ r.currency }} {{ Number(r.estimated_total).toFixed(2) }}</td>
                        <td class="table-td"><span :class="['badge', statusColor[r.status]]">{{ r.status.replace('_', ' ') }}</span></td>
                    </tr>
                    <tr v-if="!requisitions.length">
                        <td colspan="6" class="table-td text-center text-gray-400 py-8">No requisitions yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">New Procurement Requisition</h3>
                    <p class="text-xs text-gray-400 mt-1">Stage 1 of 3 — this goes to your Head of Programs for review, then the Executive Director for approval.</p>
                </div>
                <form @submit.prevent="submit" class="overflow-y-auto flex-1 px-6 py-4 space-y-3">
                    <div>
                        <label class="label">Title / Purpose</label>
                        <input v-model="form.title" class="input" required placeholder="e.g. Laptops for new field staff" />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Department</label>
                            <select v-model="form.department_id" class="input">
                                <option value="">None</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Required By</label>
                            <input v-model="form.required_by_date" type="date" class="input" />
                        </div>
                    </div>
                    <div>
                        <label class="label">Justification</label>
                        <textarea v-model="form.justification" class="input" rows="2" placeholder="Why is this needed?"></textarea>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="label mb-0">Line Items</label>
                            <button type="button" @click="addLine" class="text-xs text-brand-600 hover:underline">+ Add line</button>
                        </div>
                        <div v-for="(line, i) in form.lines" :key="i" class="grid grid-cols-12 gap-2 items-center mb-2">
                            <select v-model="line.item_id" class="input col-span-4">
                                <option value="">Custom / other…</option>
                                <option v-for="it in items" :key="it.id" :value="it.id">{{ it.name }}</option>
                            </select>
                            <input v-model="line.description" class="input col-span-3" placeholder="Description" />
                            <input v-model.number="line.quantity" type="number" min="1" class="input col-span-2" placeholder="Qty" />
                            <input v-model.number="line.estimated_unit_cost" type="number" min="0" step="0.01" class="input col-span-2" placeholder="Est. unit cost" />
                            <button type="button" @click="removeLine(i)" class="col-span-1 text-gray-400 hover:text-red-600" :disabled="form.lines.length === 1">
                                <TrashIcon class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div class="text-sm font-medium text-gray-700">
                        Estimated Total: {{ estimatedTotal.toFixed(2) }}
                    </div>
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="submit" class="btn-primary" :disabled="form.processing">Create</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
