<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon, PencilIcon, PhoneIcon, TicketIcon, QueueListIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ client: Object });

const activeTab = ref('calls');

const tabs = [
    { id: 'calls',     label: 'Calls',     icon: PhoneIcon },
    { id: 'tickets',   label: 'Tickets',   icon: TicketIcon },
    { id: 'callbacks', label: 'Callbacks', icon: QueueListIcon },
];

function fmt(s) {
    if (!s) return '—';
    return `${Math.floor(s / 60)}m ${s % 60}s`;
}
</script>

<template>
    <AppLayout>
        <template #title>{{ client.name }}</template>
        <template #header-actions>
            <Link :href="`/clients/${client.id}/edit`" class="btn-secondary btn-sm">
                <PencilIcon class="h-4 w-4" /> Edit
            </Link>
        </template>

        <div class="max-w-5xl space-y-6">
            <Link href="/clients" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeftIcon class="h-4 w-4" /> Back to clients
            </Link>

            <!-- Profile card -->
            <div class="card">
                <div class="flex items-start gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-600 text-white text-xl font-bold flex-shrink-0">
                        {{ client.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="flex-1 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div><dt class="text-gray-500">Phone</dt><dd class="font-medium font-mono">{{ client.phone }}</dd></div>
                        <div><dt class="text-gray-500">Email</dt><dd class="font-medium">{{ client.email ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Company</dt><dd class="font-medium">{{ client.company ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Status</dt>
                            <dd>
                                <span :class="['badge', client.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600']">
                                    {{ client.status }}
                                </span>
                            </dd>
                        </div>
                        <div v-if="client.notes" class="col-span-2 sm:col-span-4">
                            <dt class="text-gray-500">Notes</dt><dd>{{ client.notes }}</dd>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div>
                <div class="flex gap-1 border-b border-gray-200 mb-4">
                    <button
                        v-for="tab in tabs" :key="tab.id"
                        @click="activeTab = tab.id"
                        :class="['flex items-center gap-1.5 px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
                                 activeTab === tab.id ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Calls tab -->
                <div v-if="activeTab === 'calls'" class="card p-0 overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="table-th">Date</th>
                                <th class="table-th">Direction</th>
                                <th class="table-th">Status</th>
                                <th class="table-th">Duration</th>
                                <th class="table-th">Agent</th>
                                <th class="table-th w-16" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-if="!client.calls?.length">
                                <td colspan="6" class="py-8 text-center text-sm text-gray-400">No calls yet.</td>
                            </tr>
                            <tr v-for="call in client.calls" :key="call.id" class="hover:bg-gray-50">
                                <td class="table-td text-xs">{{ new Date(call.started_at).toLocaleString() }}</td>
                                <td class="table-td capitalize">{{ call.direction }}</td>
                                <td class="table-td capitalize">{{ call.status }}</td>
                                <td class="table-td">{{ fmt(call.duration) }}</td>
                                <td class="table-td">{{ call.agent?.name ?? '—' }}</td>
                                <td class="table-td"><Link :href="`/calls/${call.id}`" class="btn-secondary btn-sm">View</Link></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Tickets tab -->
                <div v-if="activeTab === 'tickets'" class="card p-0 overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="table-th">Subject</th>
                                <th class="table-th">Priority</th>
                                <th class="table-th">Status</th>
                                <th class="table-th">Agent</th>
                                <th class="table-th w-16" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-if="!client.tickets?.length">
                                <td colspan="5" class="py-8 text-center text-sm text-gray-400">No tickets.</td>
                            </tr>
                            <tr v-for="t in client.tickets" :key="t.id" class="hover:bg-gray-50">
                                <td class="table-td font-medium">{{ t.subject }}</td>
                                <td class="table-td capitalize">{{ t.priority }}</td>
                                <td class="table-td capitalize">{{ t.status }}</td>
                                <td class="table-td">{{ t.agent?.name ?? '—' }}</td>
                                <td class="table-td"><Link :href="`/tickets/${t.id}`" class="btn-secondary btn-sm">View</Link></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Callbacks tab -->
                <div v-if="activeTab === 'callbacks'" class="card p-0 overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="table-th">Notes</th>
                                <th class="table-th">Status</th>
                                <th class="table-th">Agent</th>
                                <th class="table-th">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-if="!client.callback_queue?.length">
                                <td colspan="4" class="py-8 text-center text-sm text-gray-400">No callbacks.</td>
                            </tr>
                            <tr v-for="cb in client.callback_queue" :key="cb.id" class="hover:bg-gray-50">
                                <td class="table-td">{{ cb.notes ?? '—' }}</td>
                                <td class="table-td capitalize">{{ cb.status }}</td>
                                <td class="table-td">{{ cb.agent?.name ?? '—' }}</td>
                                <td class="table-td text-xs">{{ new Date(cb.created_at).toLocaleDateString() }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
