<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ holidays: Array, year: Number });

const year = ref(props.year);
watch(year, (y) => router.get('/leave/holidays', { year: y }, { preserveState: true, replace: true }));

const showForm = ref(false);
const editing = ref(null);
const form = useForm({ name: '', date: '' });

function openNew() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function openEdit(h) {
    editing.value = h;
    form.clearErrors();
    form.name = h.name;
    form.date = h.date;
    showForm.value = true;
}

function submit() {
    const opts = { preserveScroll: true, onSuccess: () => { showForm.value = false; } };
    editing.value ? form.put(`/leave/holidays/${editing.value.id}`, opts) : form.post('/leave/holidays', opts);
}

function remove(h) {
    if (confirm(`Delete "${h.name}"? Leave already applied for is not recounted.`)) {
        form.delete(`/leave/holidays/${h.id}`, { preserveScroll: true });
    }
}

const fmt = (d) => new Date(d + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'long' });
</script>

<template>
    <AppLayout>
        <template #title>Public Holidays</template>
        <template #subtitle>These days are not counted when working out how many days a leave request uses.</template>
        <template #header-actions>
            <button @click="openNew" class="btn-primary btn-sm"><PlusIcon class="h-4 w-4" /> Add Holiday</button>
        </template>

        <div class="card mb-4 flex items-end gap-3">
            <div>
                <label class="label">Year</label>
                <input v-model.number="year" type="number" class="input w-28" />
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Date</th>
                        <th class="table-th">Holiday</th>
                        <th class="table-th w-20" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="h in holidays" :key="h.id" class="hover:bg-gray-50">
                        <td class="table-td whitespace-nowrap">{{ fmt(h.date) }}</td>
                        <td class="table-td font-medium">{{ h.name }}</td>
                        <td class="table-td text-right whitespace-nowrap">
                            <button @click="openEdit(h)" class="text-gray-400 hover:text-blue-600 mr-2"><PencilIcon class="h-4 w-4" /></button>
                            <button @click="remove(h)" class="text-gray-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                        </td>
                    </tr>
                    <tr v-if="!holidays.length">
                        <td colspan="3" class="table-td text-center text-gray-400 py-8">No public holidays for {{ year }}.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">{{ editing ? 'Edit' : 'Add' }} Public Holiday</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="label">Name</label>
                        <input v-model="form.name" class="input" required />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="label">Date</label>
                        <input v-model="form.date" type="date" class="input" required />
                        <p v-if="form.errors.date" class="mt-1 text-xs text-red-600">{{ form.errors.date }}</p>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
