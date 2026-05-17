<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, CheckIcon, UserIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props   = defineProps({ callbacks: Object, clients: Array, filters: Object });
const status  = ref(props.filters.status ?? '');
const showAdd = ref(false);

const addForm = useForm({ client_id: '', notes: '' });

watch(status, () => {
    router.get('/callbacks', { status: status.value || undefined }, { preserveState: true, replace: true });
});

function addCallback() {
    addForm.post('/callbacks', { onSuccess: () => { showAdd.value = false; addForm.reset(); } });
}

function assign(cb) {
    router.post(`/callbacks/${cb.id}/assign`);
}

function complete(cb) {
    router.post(`/callbacks/${cb.id}/complete`);
}

function destroy(cb) {
    if (!confirm('Remove this callback?')) return;
    router.delete(`/callbacks/${cb.id}`);
}

const statusColor = {
    pending:   'bg-yellow-100 text-yellow-800',
    assigned:  'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
};
</script>

<template>
    <AppLayout>
        <template #title>Callback Queue</template>
        <template #header-actions>
            <button @click="showAdd = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> Add Callback
            </button>
        </template>

        <!-- Filter -->
        <div class="card mb-4 flex items-end gap-3">
            <div>
                <label class="label">Status</label>
                <select v-model="status" class="input w-36">
                    <option value="">All</option>
                    <option value="pending">Pending</option>
                    <option value="assigned">Assigned</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Client</th>
                        <th class="table-th">Phone</th>
                        <th class="table-th">Notes</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Agent</th>
                        <th class="table-th">Created</th>
                        <th class="table-th w-32" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!callbacks.data.length">
                        <td colspan="7" class="py-12 text-center text-sm text-gray-400">Queue is empty.</td>
                    </tr>
                    <tr v-for="cb in callbacks.data" :key="cb.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium">
                            <Link v-if="cb.client" :href="`/clients/${cb.client.id}`" class="text-brand-600 hover:underline">
                                {{ cb.client.name }}
                            </Link>
                        </td>
                        <td class="table-td font-mono text-xs">{{ cb.client?.phone ?? '—' }}</td>
                        <td class="table-td text-xs max-w-xs truncate">{{ cb.notes ?? '—' }}</td>
                        <td class="table-td">
                            <span :class="['badge', statusColor[cb.status] ?? 'bg-gray-100 text-gray-600']">{{ cb.status }}</span>
                        </td>
                        <td class="table-td">{{ cb.agent?.name ?? '—' }}</td>
                        <td class="table-td text-xs">{{ new Date(cb.created_at).toLocaleDateString() }}</td>
                        <td class="table-td">
                            <div class="flex gap-1">
                                <button v-if="cb.status === 'pending'" @click="assign(cb)" class="btn-secondary btn-sm" title="Assign to me">
                                    <UserIcon class="h-3.5 w-3.5" />
                                </button>
                                <button v-if="cb.status !== 'completed'" @click="complete(cb)" class="btn-secondary btn-sm" title="Mark complete">
                                    <CheckIcon class="h-3.5 w-3.5" />
                                </button>
                                <button @click="destroy(cb)" class="btn-danger btn-sm">
                                    <TrashIcon class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="callbacks.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">Showing {{ callbacks.from }}–{{ callbacks.to }} of {{ callbacks.total }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in callbacks.links" :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                        preserve-state v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Add modal -->
        <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Queue Callback</h3>
                <form @submit.prevent="addCallback" class="space-y-4">
                    <div>
                        <label class="label">Client *</label>
                        <select v-model="addForm.client_id" class="input" required>
                            <option value="">— select —</option>
                            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }} ({{ c.phone }})</option>
                        </select>
                        <p v-if="addForm.errors.client_id" class="mt-1 text-xs text-red-600">{{ addForm.errors.client_id }}</p>
                    </div>
                    <div>
                        <label class="label">Notes</label>
                        <textarea v-model="addForm.notes" class="input h-20 resize-none" />
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button type="button" @click="showAdd = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="addForm.processing">Add to Queue</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
