<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PencilIcon } from '@heroicons/vue/24/outline';
const props = defineProps({
    asset: Object,
});

function formatDate(val) {
    if (!val) return '—';
    return new Date(val).toLocaleDateString();
}

function formatCurrency(val) {
    if (val == null) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val);
}

const bandColors = { red: 'bg-red-100 text-red-800', amber: 'bg-yellow-100 text-yellow-800', green: 'bg-green-100 text-green-800' };

function band(score) {
    if (score == null) return 'green';
    if (score >= 15) return 'red';
    if (score >= 7)  return 'amber';
    return 'green';
}
</script>

<template>
    <AppLayout>
        <template #title>{{ asset.name }}</template>
        <template #header-actions>
            <Link :href="`/registry/assets/${asset.id}/edit`" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                <PencilIcon class="h-4 w-4" /> Edit
            </Link>
            <Link href="/registry" class="btn-secondary btn-sm">Back</Link>
        </template>

        <div class="max-w-4xl space-y-6">
            <!-- Identity & Technical -->
            <div class="card">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Identity &amp; Technical</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs text-gray-400">Asset Tag</p>
                        <p class="font-mono font-semibold text-gray-800">{{ asset.asset_tag }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Name</p>
                        <p class="font-semibold text-gray-900">{{ asset.name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Type</p>
                        <p class="capitalize text-gray-800">{{ asset.type }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Category</p>
                        <p class="text-gray-800">{{ asset.category?.name ?? 'Uncategorized' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Location</p>
                        <p class="text-gray-800">{{ asset.location ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">IP Address</p>
                        <p class="font-mono text-gray-800">{{ asset.ip_address ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Owner</p>
                        <p class="text-gray-800">{{ asset.owner ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">OS / Version</p>
                        <p class="text-gray-800">{{ asset.os_version ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Patch Status</p>
                        <p class="capitalize text-gray-800">{{ asset.patch_status }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Data Sensitivity</p>
                        <p class="capitalize text-gray-800">{{ asset.data_sensitivity }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Criticality</p>
                        <p class="capitalize text-gray-800">{{ asset.criticality_393 }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Source</p>
                        <p class="capitalize text-gray-800">{{ asset.source }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Last Scanned</p>
                        <p class="text-gray-800">{{ asset.last_scanned_at ? formatDate(asset.last_scanned_at) : '—' }}</p>
                    </div>
                </div>
            </div>

            <!-- Procurement & Lifecycle -->
            <div class="card">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Procurement &amp; Lifecycle</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs text-gray-400">Serial Number</p>
                        <p class="font-mono text-gray-800">{{ asset.serial_number ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Supplier</p>
                        <p class="text-gray-800">{{ asset.supplier ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Acquired On</p>
                        <p class="text-gray-800">{{ formatDate(asset.acquired_on) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Cost</p>
                        <p class="font-semibold text-gray-900">{{ formatCurrency(asset.cost) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Warranty Expires</p>
                        <p class="text-gray-800">{{ formatDate(asset.warranty_expires_on) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Lifecycle Status</p>
                        <p class="capitalize text-gray-800">{{ asset.lifecycle_status?.replace('_', ' ') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Replace Due On</p>
                        <p class="text-gray-800">{{ formatDate(asset.replace_due_on) }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs text-gray-400">Notes</p>
                        <p class="text-gray-800 whitespace-pre-line">{{ asset.notes ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <!-- Linked Risks -->
            <div class="card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Linked Risks ({{ asset.risks?.length ?? 0 }})</h3>
                    <Link href="/risk/risks" class="btn-secondary btn-sm">View Risk Register</Link>
                </div>
                <div v-if="!asset.risks?.length" class="text-sm text-gray-400 py-4 text-center">No risks linked to this asset.</div>
                <div v-else class="space-y-3">
                    <div v-for="risk in asset.risks" :key="risk.id"
                        class="flex items-start justify-between border border-gray-100 rounded-lg p-3">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-mono text-xs font-semibold text-gray-600">{{ risk.risk_ref }}</span>
                                <span :class="['badge', bandColors[band(risk.residual_score)]]">
                                    {{ band(risk.residual_score).toUpperCase() }}
                                </span>
                                <span class="badge bg-gray-100 text-gray-600 capitalize">{{ risk.category?.replace('_', ' ') }}</span>
                            </div>
                            <p class="text-sm text-gray-700">{{ risk.description }}</p>
                        </div>
                        <div class="text-right ml-4 shrink-0">
                            <p class="text-xs text-gray-400">Residual</p>
                            <p class="text-lg font-bold" :class="{ 'text-red-600': band(risk.residual_score) === 'red', 'text-yellow-600': band(risk.residual_score) === 'amber', 'text-green-600': band(risk.residual_score) === 'green' }">
                                {{ risk.residual_score ?? '—' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="card">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Activity Log</h3>
                <div v-if="!asset.activity_logs?.length" class="text-sm text-gray-400 py-4 text-center">No activity recorded yet.</div>
                <ul v-else class="space-y-3">
                    <li v-for="log in asset.activity_logs" :key="log.id" class="flex items-start justify-between text-sm border-b border-gray-50 pb-2 last:border-0">
                        <div>
                            <span class="badge bg-gray-100 text-gray-700 capitalize mr-2">{{ log.action }}</span>
                            <span class="text-gray-600">{{ log.user?.name ?? 'System' }}</span>
                            <span v-if="log.changed_fields?.length" class="text-gray-400"> — {{ log.changed_fields.join(', ') }}</span>
                        </div>
                        <span class="text-xs text-gray-400 shrink-0 ml-3">{{ new Date(log.created_at).toLocaleString() }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
