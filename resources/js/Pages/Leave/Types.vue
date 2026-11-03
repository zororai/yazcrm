<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';

defineProps({ types: Array });

const showForm = ref(false);
const editing = ref(null);
const form = useForm({ name: '', default_days: 0, requires_attachment: false, is_paid: true, is_active: true });

function openNew() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function openEdit(t) {
    editing.value = t;
    form.clearErrors();
    Object.assign(form, {
        name: t.name, default_days: t.default_days, requires_attachment: t.requires_attachment, is_paid: t.is_paid, is_active: t.is_active,
    });
    showForm.value = true;
}

function submit() {
    const opts = { preserveScroll: true, onSuccess: () => { showForm.value = false; } };
    editing.value ? form.put(`/leave/types/${editing.value.id}`, opts) : form.post('/leave/types', opts);
}

function remove(t) {
    const msg = t.requests_count
        ? `"${t.name}" has ${t.requests_count} request(s), so it will be switched off, not deleted. Continue?`
        : `Delete leave type "${t.name}"?`;
    if (confirm(msg)) form.delete(`/leave/types/${t.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <template #title>Leave Types</template>
        <template #subtitle>The default entitlement applies to everyone unless set per person on the Entitlements page.</template>
        <template #header-actions>
            <button @click="openNew" class="btn-primary btn-sm"><PlusIcon class="h-4 w-4" /> New Leave Type</button>
        </template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Name</th>
                        <th class="table-th text-right">Default days / year</th>
                        <th class="table-th">Paid</th>
                        <th class="table-th">Attachment</th>
                        <th class="table-th">Active</th>
                        <th class="table-th text-right">Requests</th>
                        <th class="table-th w-20" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="t in types" :key="t.id" class="hover:bg-gray-50" :class="{ 'opacity-50': !t.is_active }">
                        <td class="table-td font-medium">{{ t.name }}</td>
                        <td class="table-td text-right">{{ t.is_paid ? t.default_days : '—' }}</td>
                        <td class="table-td">{{ t.is_paid ? 'Paid' : 'Unpaid (no limit)' }}</td>
                        <td class="table-td">{{ t.requires_attachment ? 'Required' : 'Optional' }}</td>
                        <td class="table-td">{{ t.is_active ? 'Yes' : 'No' }}</td>
                        <td class="table-td text-right">{{ t.requests_count }}</td>
                        <td class="table-td text-right whitespace-nowrap">
                            <button @click="openEdit(t)" class="text-gray-400 hover:text-blue-600 mr-2"><PencilIcon class="h-4 w-4" /></button>
                            <button @click="remove(t)" class="text-gray-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!types.length">
                        <td colspan="7" class="table-td text-center text-gray-400 py-8">No leave types yet. Run <code>php artisan db:seed --class=LeaveSeeder</code> for the defaults.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">{{ editing ? 'Edit' : 'New' }} Leave Type</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="label">Name</label>
                        <input v-model="form.name" class="input" required />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="label">Default working days per year</label>
                        <input v-model.number="form.default_days" type="number" min="0" max="366" step="0.5" class="input" required />
                        <p v-if="form.errors.default_days" class="mt-1 text-xs text-red-600">{{ form.errors.default_days }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.is_paid" type="checkbox" /> Paid (counts against a balance)</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.requires_attachment" type="checkbox" /> Needs a supporting document</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" /> Active (staff can apply for it)</label>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
