<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ suppliers: Array, isManager: Boolean });

const showForm = ref(false);
const editing = ref(null);
const form = useForm({
    supplier_code: '', name: '', contact_person: '', phone: '', email: '',
    address: '', tax_number: '', payment_terms: '', status: 'active',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function openEdit(supplier) {
    editing.value = supplier;
    form.supplier_code = supplier.supplier_code;
    form.name = supplier.name;
    form.contact_person = supplier.contact_person;
    form.phone = supplier.phone;
    form.email = supplier.email;
    form.address = supplier.address;
    form.tax_number = supplier.tax_number;
    form.payment_terms = supplier.payment_terms;
    form.status = supplier.status;
    form.clearErrors();
    showForm.value = true;
}

function submit() {
    if (editing.value) {
        form.put(`/suppliers/${editing.value.id}`, { onSuccess: () => { showForm.value = false; } });
    } else {
        form.post('/suppliers', { onSuccess: () => { showForm.value = false; } });
    }
}

function destroy(supplier) {
    if (! confirm(`Delete supplier "${supplier.name}"?`)) return;
    form.delete(`/suppliers/${supplier.id}`);
}
</script>

<template>
    <AppLayout>
        <template #title>Suppliers</template>
        <template #header-actions>
            <button v-if="isManager" @click="openCreate" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Supplier
            </button>
        </template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Code</th>
                        <th class="table-th">Name</th>
                        <th class="table-th">Contact</th>
                        <th class="table-th">Phone / Email</th>
                        <th class="table-th">Purchase Orders</th>
                        <th class="table-th">Status</th>
                        <th v-if="isManager" class="table-th"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in suppliers" :key="s.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium">{{ s.supplier_code }}</td>
                        <td class="table-td">{{ s.name }}</td>
                        <td class="table-td">{{ s.contact_person ?? '—' }}</td>
                        <td class="table-td">
                            <div>{{ s.phone ?? '—' }}</div>
                            <div class="text-xs text-gray-400">{{ s.email ?? '' }}</div>
                        </td>
                        <td class="table-td">{{ s.purchase_orders_count }}</td>
                        <td class="table-td">
                            <span :class="['badge', s.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-500']">{{ s.status }}</span>
                        </td>
                        <td v-if="isManager" class="table-td">
                            <div class="flex gap-2">
                                <button @click="openEdit(s)" class="text-gray-400 hover:text-gray-700"><PencilIcon class="h-4 w-4" /></button>
                                <button @click="destroy(s)" class="text-gray-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!suppliers.length">
                        <td colspan="7" class="table-td text-center text-gray-400 py-8">No suppliers yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[90vh] flex flex-col">
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">{{ editing ? 'Edit Supplier' : 'New Supplier' }}</h3>
                </div>
                <form @submit.prevent="submit" class="overflow-y-auto flex-1 px-6 py-4 space-y-3">
                    <div>
                        <label class="label">Supplier Code</label>
                        <input v-model="form.supplier_code" class="input" required />
                        <p v-if="form.errors.supplier_code" class="mt-1 text-xs text-red-600">{{ form.errors.supplier_code }}</p>
                    </div>
                    <div>
                        <label class="label">Name</label>
                        <input v-model="form.name" class="input" required />
                    </div>
                    <div>
                        <label class="label">Contact Person</label>
                        <input v-model="form.contact_person" class="input" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Phone</label>
                            <input v-model="form.phone" class="input" />
                        </div>
                        <div>
                            <label class="label">Email</label>
                            <input v-model="form.email" type="email" class="input" />
                        </div>
                    </div>
                    <div>
                        <label class="label">Address</label>
                        <textarea v-model="form.address" class="input" rows="2"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Tax Number</label>
                            <input v-model="form.tax_number" class="input" />
                        </div>
                        <div>
                            <label class="label">Payment Terms</label>
                            <input v-model="form.payment_terms" class="input" placeholder="e.g. Net 30" />
                        </div>
                    </div>
                    <div v-if="editing">
                        <label class="label">Status</label>
                        <select v-model="form.status" class="input">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="submit" class="btn-primary" :disabled="form.processing">Save</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
