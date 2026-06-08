<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    PlusIcon, ArrowDownTrayIcon, MagnifyingGlassIcon,
    PencilIcon, TrashIcon, EyeIcon,
} from '@heroicons/vue/24/outline';
import { debounce } from 'lodash-es';

const props = defineProps({
    assets:  Object,
    summary: Object,
    filters: Object,
});

const search          = ref(props.filters.search           ?? '');
const type            = ref(props.filters.type             ?? '');
const lifecycleStatus = ref(props.filters.lifecycle_status ?? '');
const dataSensitivity = ref(props.filters.data_sensitivity ?? '');
const criticality     = ref(props.filters.criticality_393  ?? '');

function apply() {
    router.get('/registry', {
        search:           search.value           || undefined,
        type:             type.value             || undefined,
        lifecycle_status: lifecycleStatus.value  || undefined,
        data_sensitivity: dataSensitivity.value  || undefined,
        criticality_393:  criticality.value       || undefined,
    }, { preserveState: true, replace: true });
}

const debouncedApply = debounce(apply, 350);
watch(search, debouncedApply);
watch([type, lifecycleStatus, dataSensitivity, criticality], apply);

function deleteAsset(asset) {
    if (!confirm(`Delete asset "${asset.name}" (${asset.asset_tag})? This cannot be undone.`)) return;
    router.delete(`/registry/assets/${asset.id}`, { preserveScroll: false });
}

function formatCurrency(val) {
    if (val == null) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 0 }).format(val);
}

function formatDate(val) {
    if (!val) return '—';
    return new Date(val).toLocaleDateString();
}

function warrantyClass(date) {
    if (!date) return '';
    const d = new Date(date);
    const now = new Date();
    if (d < now) return 'text-red-600 font-semibold';
    const soon = new Date(now);
    soon.setMonth(soon.getMonth() + 3);
    if (d <= soon) return 'text-orange-500 font-semibold';
    return '';
}

const typeColors = {
    server:      'bg-blue-100 text-blue-800',
    network:     'bg-purple-100 text-purple-800',
    endpoint:    'bg-cyan-100 text-cyan-800',
    telephony:   'bg-teal-100 text-teal-800',
    application: 'bg-indigo-100 text-indigo-800',
    power:       'bg-yellow-100 text-yellow-800',
    saas:        'bg-pink-100 text-pink-800',
};
const lifecycleColors = {
    active:    'bg-green-100 text-green-800',
    in_repair: 'bg-yellow-100 text-yellow-800',
    retired:   'bg-gray-100 text-gray-600',
    disposed:  'bg-red-100 text-red-800',
};
const patchColors = {
    current:  'bg-green-100 text-green-800',
    outdated: 'bg-orange-100 text-orange-800',
    eol:      'bg-red-100 text-red-800',
    unknown:  'bg-gray-100 text-gray-600',
};
const critColors = {
    low:    'bg-gray-100 text-gray-600',
    medium: 'bg-blue-100 text-blue-800',
    high:   'bg-red-100 text-red-800',
};
</script>

<template>
    <AppLayout>
        <template #title>Asset Register</template>
        <template #header-actions>
            <a href="/registry/export" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                <ArrowDownTrayIcon class="h-4 w-4" /> Export CSV
            </a>
            <Link href="/registry/assets/create" class="btn-primary btn-sm inline-flex items-center gap-1.5">
                <PlusIcon class="h-4 w-4" /> Add Asset
            </Link>
        </template>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <div class="card text-center">
                <p class="text-2xl font-bold text-gray-900">{{ summary.total }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Assets</p>
            </div>
            <div class="card text-center">
                <p class="text-xl font-bold text-gray-900">{{ formatCurrency(summary.book_value) }}</p>
                <p class="text-xs text-gray-500 mt-1">Book Value</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-orange-600">{{ summary.warranty_soon }}</p>
                <p class="text-xs text-gray-500 mt-1">Warranty Expiring</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-yellow-600">{{ summary.refresh_soon }}</p>
                <p class="text-xs text-gray-500 mt-1">Refresh Due</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-blue-600">{{ summary.in_repair }}</p>
                <p class="text-xs text-gray-500 mt-1">In Repair</p>
            </div>
            <div class="card text-center">
                <p class="text-xs font-medium text-gray-600 mb-1">By Type</p>
                <div class="space-y-0.5 text-left">
                    <div v-for="(count, t) in summary.by_type" :key="t" class="flex justify-between text-xs">
                        <span class="text-gray-500 capitalize">{{ t }}</span>
                        <span class="font-semibold">{{ count }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-48">
                    <label class="label">Search</label>
                    <div class="relative">
                        <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                        <input v-model="search" class="input pl-9" placeholder="Tag, name, location, owner…" />
                    </div>
                </div>
                <div>
                    <label class="label">Type</label>
                    <select v-model="type" class="input w-36">
                        <option value="">All</option>
                        <option value="server">Server</option>
                        <option value="network">Network</option>
                        <option value="endpoint">Endpoint</option>
                        <option value="telephony">Telephony</option>
                        <option value="application">Application</option>
                        <option value="power">Power</option>
                        <option value="saas">SaaS</option>
                    </select>
                </div>
                <div>
                    <label class="label">Status</label>
                    <select v-model="lifecycleStatus" class="input w-36">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="in_repair">In Repair</option>
                        <option value="retired">Retired</option>
                        <option value="disposed">Disposed</option>
                    </select>
                </div>
                <div>
                    <label class="label">Sensitivity</label>
                    <select v-model="dataSensitivity" class="input w-32">
                        <option value="">All</option>
                        <option value="none">None</option>
                        <option value="internal">Internal</option>
                        <option value="sensitive">Sensitive</option>
                    </select>
                </div>
                <div>
                    <label class="label">Criticality</label>
                    <select v-model="criticality" class="input w-32">
                        <option value="">All</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Tag</th>
                        <th class="table-th">Name</th>
                        <th class="table-th">Type</th>
                        <th class="table-th">Location</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Patch</th>
                        <th class="table-th">Criticality</th>
                        <th class="table-th">Cost</th>
                        <th class="table-th">Warranty Expires</th>
                        <th class="table-th w-24"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!assets.data.length">
                        <td colspan="10" class="py-12 text-center text-sm text-gray-400">No assets found.</td>
                    </tr>
                    <tr v-for="a in assets.data" :key="a.id" class="hover:bg-gray-50">
                        <td class="table-td font-mono text-xs font-semibold text-gray-700">{{ a.asset_tag }}</td>
                        <td class="table-td font-medium">
                            <Link :href="`/registry/assets/${a.id}`" class="text-brand-600 hover:underline">{{ a.name }}</Link>
                        </td>
                        <td class="table-td">
                            <span :class="['badge', typeColors[a.type] ?? 'bg-gray-100 text-gray-600']">{{ a.type }}</span>
                        </td>
                        <td class="table-td text-sm text-gray-600">{{ a.location ?? '—' }}</td>
                        <td class="table-td">
                            <span :class="['badge', lifecycleColors[a.lifecycle_status] ?? 'bg-gray-100 text-gray-600']">
                                {{ a.lifecycle_status?.replace('_', ' ') }}
                            </span>
                        </td>
                        <td class="table-td">
                            <span :class="['badge', patchColors[a.patch_status] ?? 'bg-gray-100 text-gray-600']">{{ a.patch_status }}</span>
                        </td>
                        <td class="table-td">
                            <span :class="['badge', critColors[a.criticality_393] ?? 'bg-gray-100 text-gray-600']">{{ a.criticality_393 }}</span>
                        </td>
                        <td class="table-td text-sm">{{ formatCurrency(a.cost) }}</td>
                        <td class="table-td text-sm" :class="warrantyClass(a.warranty_expires_on)">
                            {{ formatDate(a.warranty_expires_on) }}
                        </td>
                        <td class="table-td">
                            <div class="flex items-center gap-1">
                                <Link :href="`/registry/assets/${a.id}`" class="text-gray-400 hover:text-brand-600" title="View">
                                    <EyeIcon class="h-4 w-4" />
                                </Link>
                                <Link :href="`/registry/assets/${a.id}/edit`" class="text-gray-400 hover:text-blue-600" title="Edit">
                                    <PencilIcon class="h-4 w-4" />
                                </Link>
                                <button @click="deleteAsset(a)" class="text-gray-400 hover:text-red-600" title="Delete">
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="assets.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">Showing {{ assets.from }}–{{ assets.to }} of {{ assets.total }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in assets.links" :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                        preserve-state v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
