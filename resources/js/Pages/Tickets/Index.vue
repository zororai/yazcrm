<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { debounce } from 'lodash-es';

const props    = defineProps({ tickets: Object, clients: Array, agents: Array, filters: Object });
const search   = ref(props.filters.search ?? '');
const status   = ref(props.filters.status ?? '');
const priority = ref(props.filters.priority ?? '');
const showAdd  = ref(false);

const addForm = useForm({
    subject: '', description: '', client_id: '', priority: 'medium',
});

function apply() {
    router.get('/tickets', {
        search:   search.value || undefined,
        status:   status.value || undefined,
        priority: priority.value || undefined,
    }, { preserveState: true, replace: true });
}

const debouncedApply = debounce(apply, 350);
watch(search, debouncedApply);
watch([status, priority], apply);

function store() {
    addForm.post('/tickets', { onSuccess: () => { showAdd.value = false; addForm.reset(); } });
}

const priorityColor = {
    low:    'bg-gray-100 text-gray-600',
    medium: 'bg-blue-100 text-blue-800',
    high:   'bg-orange-100 text-orange-800',
    urgent: 'bg-red-100 text-red-800',
};
const statusColor = {
    open:        'bg-yellow-100 text-yellow-800',
    in_progress: 'bg-blue-100 text-blue-800',
    resolved:    'bg-green-100 text-green-800',
    closed:      'bg-gray-100 text-gray-600',
};
</script>

<template>
    <AppLayout>
        <template #title>Tickets</template>
        <template #header-actions>
            <button @click="showAdd = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Ticket
            </button>
        </template>

        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48">
                <label class="label">Search</label>
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                    <input v-model="search" class="input pl-9" placeholder="Subject or description…" />
                </div>
            </div>
            <div>
                <label class="label">Status</label>
                <select v-model="status" class="input w-36">
                    <option value="">All</option>
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                </select>
            </div>
            <div>
                <label class="label">Priority</label>
                <select v-model="priority" class="input w-32">
                    <option value="">All</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Subject</th>
                        <th class="table-th">Client</th>
                        <th class="table-th">Priority</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Agent</th>
                        <th class="table-th">Created</th>
                        <th class="table-th w-16" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!tickets.data.length">
                        <td colspan="7" class="py-12 text-center text-sm text-gray-400">No tickets found.</td>
                    </tr>
                    <tr v-for="t in tickets.data" :key="t.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium max-w-xs truncate">
                            <Link :href="`/tickets/${t.id}`" class="text-brand-600 hover:underline">{{ t.subject }}</Link>
                        </td>
                        <td class="table-td text-xs">{{ t.client?.name ?? '—' }}</td>
                        <td class="table-td">
                            <span :class="['badge', priorityColor[t.priority]]">{{ t.priority }}</span>
                        </td>
                        <td class="table-td">
                            <span :class="['badge', statusColor[t.status]]">{{ t.status.replace('_', ' ') }}</span>
                        </td>
                        <td class="table-td">{{ t.agent?.name ?? '—' }}</td>
                        <td class="table-td text-xs">{{ new Date(t.created_at).toLocaleDateString() }}</td>
                        <td class="table-td">
                            <Link :href="`/tickets/${t.id}`" class="btn-secondary btn-sm">View</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="tickets.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">Showing {{ tickets.from }}–{{ tickets.to }} of {{ tickets.total }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in tickets.links" :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                        preserve-state v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- New ticket modal -->
        <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-4">New Ticket</h3>
                <form @submit.prevent="store" class="space-y-3">
                    <div>
                        <label class="label">Subject *</label>
                        <input v-model="addForm.subject" class="input" :class="{ 'border-red-500': addForm.errors.subject }" required />
                        <p v-if="addForm.errors.subject" class="mt-1 text-xs text-red-600">{{ addForm.errors.subject }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">Client</label>
                            <select v-model="addForm.client_id" class="input">
                                <option value="">— none —</option>
                                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Priority</label>
                            <select v-model="addForm.priority" class="input">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="label">Description</label>
                        <textarea v-model="addForm.description" class="input h-24 resize-none" />
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showAdd = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="addForm.processing">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
