<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { MagnifyingGlassIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { debounce } from 'lodash-es';

const props = defineProps({ clients: Object, filters: Object });

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');

function apply() {
    router.get('/clients', { search: search.value || undefined, status: status.value || undefined },
        { preserveState: true, replace: true });
}

const debouncedApply = debounce(apply, 350);
watch(search, debouncedApply);
watch(status, apply);

function destroy(client) {
    if (!confirm(`Delete ${client.name}?`)) return;
    router.delete(`/clients/${client.id}`);
}
</script>

<template>
    <AppLayout>
        <template #title>Clients</template>
        <template #header-actions>
            <Link href="/clients/create" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Client
            </Link>
        </template>

        <!-- Filters -->
        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48">
                <label class="label">Search</label>
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                    <input v-model="search" class="input pl-9" placeholder="Name, phone, email, company…" />
                </div>
            </div>
            <div>
                <label class="label">Status</label>
                <select v-model="status" class="input w-32">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Name</th>
                        <th class="table-th">Phone</th>
                        <th class="table-th">Email</th>
                        <th class="table-th">Company</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Calls</th>
                        <th class="table-th w-24" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!clients.data.length">
                        <td colspan="7" class="py-12 text-center text-sm text-gray-400">No clients found.</td>
                    </tr>
                    <tr v-for="c in clients.data" :key="c.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium">
                            <Link :href="`/clients/${c.id}`" class="text-brand-600 hover:underline">{{ c.name }}</Link>
                        </td>
                        <td class="table-td font-mono text-xs">{{ c.phone }}</td>
                        <td class="table-td text-xs">{{ c.email ?? '—' }}</td>
                        <td class="table-td text-xs">{{ c.company ?? '—' }}</td>
                        <td class="table-td">
                            <span :class="['badge', c.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600']">
                                {{ c.status }}
                            </span>
                        </td>
                        <td class="table-td">{{ c.calls_count }}</td>
                        <td class="table-td">
                            <div class="flex gap-1">
                                <Link :href="`/clients/${c.id}/edit`" class="btn-secondary btn-sm">Edit</Link>
                                <button @click="destroy(c)" class="btn-danger btn-sm">
                                    <TrashIcon class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="clients.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">Showing {{ clients.from }}–{{ clients.to }} of {{ clients.total }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in clients.links" :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                        preserve-state v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
