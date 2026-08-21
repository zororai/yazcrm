<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ logs: Object, users: Array });

const filters = ref({
    user_id: new URLSearchParams(window.location.search).get('user_id') ?? '',
    method:  new URLSearchParams(window.location.search).get('method') ?? '',
    search:  new URLSearchParams(window.location.search).get('search') ?? '',
});

let debounce;
watch(filters, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/audit-log', {
            user_id: filters.value.user_id || undefined,
            method: filters.value.method || undefined,
            search: filters.value.search || undefined,
        }, { preserveState: true, replace: true });
    }, 300);
}, { deep: true });

const methodColor = {
    POST:   'bg-blue-100 text-blue-800',
    PUT:    'bg-amber-100 text-amber-800',
    PATCH:  'bg-amber-100 text-amber-800',
    DELETE: 'bg-red-100 text-red-800',
};

function statusColor(code) {
    if (!code) return 'text-gray-400';
    if (code >= 500) return 'text-red-600';
    if (code >= 400) return 'text-amber-600';
    return 'text-green-600';
}
</script>

<template>
    <AppLayout>
        <template #title>Audit Trail</template>
        <template #subtitle>Every action performed by anyone in the system, most recent first.</template>

        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div>
                <label class="label">Search</label>
                <input v-model="filters.search" class="input" placeholder="Path or route name…" />
            </div>
            <div>
                <label class="label">User</label>
                <select v-model="filters.user_id" class="input">
                    <option value="">Everyone</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
            </div>
            <div>
                <label class="label">Method</label>
                <select v-model="filters.method" class="input">
                    <option value="">All</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="PATCH">PATCH</option>
                    <option value="DELETE">DELETE</option>
                </select>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">When</th>
                        <th class="table-th">User</th>
                        <th class="table-th">Method</th>
                        <th class="table-th">Action</th>
                        <th class="table-th">Path</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50">
                        <td class="table-td text-xs whitespace-nowrap">{{ new Date(log.created_at).toLocaleString() }}</td>
                        <td class="table-td font-medium">{{ log.user?.name ?? log.user_name ?? 'Guest' }}</td>
                        <td class="table-td"><span :class="['badge', methodColor[log.method] ?? 'bg-gray-100 text-gray-700']">{{ log.method }}</span></td>
                        <td class="table-td text-xs">{{ log.route_name ?? '—' }}</td>
                        <td class="table-td text-xs font-mono">{{ log.path }}</td>
                        <td class="table-td text-xs font-medium" :class="statusColor(log.status_code)">{{ log.status_code ?? '—' }}</td>
                        <td class="table-td text-xs text-gray-400">{{ log.ip_address ?? '—' }}</td>
                    </tr>
                    <tr v-if="!logs.data.length">
                        <td colspan="7" class="table-td text-center text-gray-400 py-8">No activity recorded yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="logs.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-gray-500">
            <span>Page {{ logs.current_page }} of {{ logs.last_page }} ({{ logs.total }} total)</span>
            <div class="flex gap-2">
                <Link v-if="logs.prev_page_url" :href="logs.prev_page_url" class="btn-secondary btn-sm">Previous</Link>
                <Link v-if="logs.next_page_url" :href="logs.next_page_url" class="btn-secondary btn-sm">Next</Link>
            </div>
        </div>
    </AppLayout>
</template>
