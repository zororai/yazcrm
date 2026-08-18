<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ appraisals: Array, staff: Array, isManager: Boolean });

const showNew = ref(false);
const newForm = useForm({ user_id: '' });

function startNew() {
    if (props.isManager) { showNew.value = true; return; }
    newForm.post('/appraisals');
}

function submitNew() {
    newForm.post('/appraisals', { onSuccess: () => { showNew.value = false; newForm.reset(); } });
}

function open(appraisal) {
    router.get(`/appraisals/${appraisal.id}`);
}

const statusColor = {
    draft:     'bg-gray-100 text-gray-700',
    submitted: 'bg-amber-100 text-amber-800',
    completed: 'bg-green-100 text-green-800',
};
</script>

<template>
    <AppLayout>
        <template #title>Staff Performance Appraisals</template>
        <template #header-actions>
            <button @click="startNew" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Appraisal
            </button>
        </template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Employee</th>
                        <th class="table-th">Supervisor</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Overall Rating</th>
                        <th class="table-th">Updated</th>
                        <th class="table-th w-24" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="a in appraisals" :key="a.id" class="hover:bg-gray-50 cursor-pointer" @click="open(a)">
                        <td class="table-td font-medium">{{ a.user?.name ?? '—' }}</td>
                        <td class="table-td">{{ a.supervisor?.name ?? '—' }}</td>
                        <td class="table-td">
                            <span :class="['badge', statusColor[a.status]]">{{ a.status }}</span>
                        </td>
                        <td class="table-td">{{ a.overall_rating ?? '—' }}</td>
                        <td class="table-td text-xs">{{ new Date(a.updated_at).toLocaleDateString() }}</td>
                        <td class="table-td text-right">
                            <ClipboardDocumentCheckIcon class="h-4 w-4 text-gray-400 inline-block" />
                        </td>
                    </tr>
                    <tr v-if="!appraisals.length">
                        <td colspan="6" class="table-td text-center text-gray-400 py-8">No appraisals yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- New appraisal modal (managers choose who it's for) -->
        <div v-if="showNew" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">New Appraisal</h3>
                <form @submit.prevent="submitNew" class="space-y-3">
                    <div>
                        <label class="label">Staff member</label>
                        <select v-model="newForm.user_id" class="input" required>
                            <option value="" disabled>Select…</option>
                            <option v-for="u in staff" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showNew = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="newForm.processing">Start</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
