<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PencilIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ rows: Array, people: Array, filters: Object });

const filters = ref({ ...props.filters });
watch(filters, () => {
    router.get('/leave/entitlements', {
        year:    filters.value.year || undefined,
        user_id: filters.value.user_id || undefined,
    }, { preserveState: true, replace: true });
}, { deep: true });

const editing = ref(null);
const form = useForm({ user_id: null, leave_type_id: null, year: null, entitled_days: 0 });

function openEdit(row) {
    form.clearErrors();
    Object.assign(form, {
        user_id: row.user.id, leave_type_id: row.leave_type.id, year: row.year, entitled_days: row.entitled_days,
    });
    editing.value = row;
}

function submit() {
    form.post('/leave/entitlements', { preserveScroll: true, onSuccess: () => { editing.value = null; } });
}
</script>

<template>
    <AppLayout>
        <template #title>Leave Entitlements</template>
        <template #subtitle>
            Days each person is entitled to per leave type and year. "Used" only changes when leave is approved or a decision is corrected.
        </template>

        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="label">Year</label>
                <input v-model.number="filters.year" type="number" class="input w-28" />
            </div>
            <div>
                <label class="label">Person</label>
                <select v-model="filters.user_id" class="input">
                    <option value="">Everyone</option>
                    <option v-for="p in people" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>
        </div>

        <div class="card p-0 overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Person</th>
                        <th class="table-th">Leave type</th>
                        <th class="table-th text-right">Entitled</th>
                        <th class="table-th text-right">Used</th>
                        <th class="table-th text-right">Pending</th>
                        <th class="table-th text-right">Remaining</th>
                        <th class="table-th w-12" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in rows" :key="`${r.user.id}-${r.leave_type.id}`" class="hover:bg-gray-50">
                        <td class="table-td font-medium">{{ r.user.name }}</td>
                        <td class="table-td">{{ r.leave_type.name }}</td>
                        <td class="table-td text-right">{{ r.leave_type.is_paid ? r.entitled_days : '—' }}</td>
                        <td class="table-td text-right">{{ r.used_days }}</td>
                        <td class="table-td text-right">{{ r.pending_days }}</td>
                        <td class="table-td text-right font-medium" :class="r.remaining_days < 0 ? 'text-red-600' : ''">
                            {{ r.leave_type.is_paid ? r.remaining_days : '—' }}
                        </td>
                        <td class="table-td text-right">
                            <button v-if="r.leave_type.is_paid" @click="openEdit(r)" class="text-gray-400 hover:text-blue-600" title="Set entitlement">
                                <PencilIcon class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="7" class="table-td text-center text-gray-400 py-8">No active people or leave types.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-1">Set entitlement</h3>
                <p class="text-sm text-gray-500 mb-4">{{ editing.user.name }}, {{ editing.leave_type.name }} leave, {{ editing.year }}</p>
                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="label">Entitled working days</label>
                        <input v-model.number="form.entitled_days" type="number" min="0" max="366" step="0.5" class="input" required />
                        <p v-if="form.errors.entitled_days" class="mt-1 text-xs text-red-600">{{ form.errors.entitled_days }}</p>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="editing = null" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
