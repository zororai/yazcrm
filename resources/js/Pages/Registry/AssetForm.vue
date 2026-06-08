<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    asset:   Object,
    options: Object,
});

const isEdit = !!props.asset;

const form = useForm({
    asset_tag:          props.asset?.asset_tag          ?? '',
    name:               props.asset?.name               ?? '',
    type:               props.asset?.type               ?? 'server',
    location:           props.asset?.location           ?? '',
    ip_address:         props.asset?.ip_address         ?? '',
    owner:              props.asset?.owner              ?? '',
    os_version:         props.asset?.os_version         ?? '',
    patch_status:       props.asset?.patch_status       ?? 'unknown',
    data_sensitivity:   props.asset?.data_sensitivity   ?? 'internal',
    criticality_393:    props.asset?.criticality_393    ?? 'medium',
    source:             props.asset?.source             ?? 'manual',
    last_scanned_at:    props.asset?.last_scanned_at    ?? '',
    serial_number:      props.asset?.serial_number      ?? '',
    supplier:           props.asset?.supplier           ?? '',
    acquired_on:        props.asset?.acquired_on        ?? '',
    cost:               props.asset?.cost               ?? '',
    warranty_expires_on: props.asset?.warranty_expires_on ?? '',
    lifecycle_status:   props.asset?.lifecycle_status   ?? 'active',
    replace_due_on:     props.asset?.replace_due_on     ?? '',
    notes:              props.asset?.notes              ?? '',
});

function submit() {
    if (isEdit) {
        form.put(`/registry/assets/${props.asset.id}`);
    } else {
        form.post('/registry/assets');
    }
}
</script>

<template>
    <AppLayout>
        <template #title>{{ isEdit ? 'Edit Asset' : 'Add Asset' }}</template>

        <div class="max-w-4xl">
            <form @submit.prevent="submit" class="space-y-6">

                <!-- Identity & Technical -->
                <div class="card space-y-4">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Identity &amp; Technical</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Asset Tag *</label>
                            <input v-model="form.asset_tag" class="input" :class="{ 'border-red-500': form.errors.asset_tag }" required />
                            <p v-if="form.errors.asset_tag" class="mt-1 text-xs text-red-600">{{ form.errors.asset_tag }}</p>
                        </div>
                        <div>
                            <label class="label">Name *</label>
                            <input v-model="form.name" class="input" :class="{ 'border-red-500': form.errors.name }" required />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="label">Type *</label>
                            <select v-model="form.type" class="input" required>
                                <option v-for="t in options.types" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Location</label>
                            <input v-model="form.location" class="input" />
                        </div>
                        <div>
                            <label class="label">IP Address</label>
                            <input v-model="form.ip_address" class="input" placeholder="e.g. 192.168.1.100" />
                        </div>
                        <div>
                            <label class="label">Owner</label>
                            <input v-model="form.owner" class="input" placeholder="Department or person" />
                        </div>
                        <div>
                            <label class="label">OS / Version</label>
                            <input v-model="form.os_version" class="input" placeholder="e.g. Ubuntu 22.04" />
                        </div>
                        <div>
                            <label class="label">Patch Status</label>
                            <select v-model="form.patch_status" class="input">
                                <option v-for="s in options.patchStatuses" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Data Sensitivity</label>
                            <select v-model="form.data_sensitivity" class="input">
                                <option v-for="s in options.dataSensitivities" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Criticality (IS0 393)</label>
                            <select v-model="form.criticality_393" class="input">
                                <option v-for="s in options.criticalities" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Source</label>
                            <select v-model="form.source" class="input">
                                <option v-for="s in options.sources" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Last Scanned At</label>
                            <input v-model="form.last_scanned_at" type="datetime-local" class="input" />
                        </div>
                    </div>
                </div>

                <!-- Procurement & Lifecycle -->
                <div class="card space-y-4">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Procurement &amp; Lifecycle</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Serial Number</label>
                            <input v-model="form.serial_number" class="input" />
                        </div>
                        <div>
                            <label class="label">Supplier</label>
                            <input v-model="form.supplier" class="input" />
                        </div>
                        <div>
                            <label class="label">Acquired On</label>
                            <input v-model="form.acquired_on" type="date" class="input" />
                        </div>
                        <div>
                            <label class="label">Cost (USD)</label>
                            <input v-model="form.cost" type="number" step="0.01" min="0" class="input" />
                        </div>
                        <div>
                            <label class="label">Warranty Expires On</label>
                            <input v-model="form.warranty_expires_on" type="date" class="input" />
                        </div>
                        <div>
                            <label class="label">Lifecycle Status</label>
                            <select v-model="form.lifecycle_status" class="input">
                                <option v-for="s in options.lifecycleStatuses" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Replace Due On</label>
                            <input v-model="form.replace_due_on" type="date" class="input" />
                        </div>
                        <div class="col-span-2">
                            <label class="label">Notes</label>
                            <textarea v-model="form.notes" class="input h-24 resize-none" placeholder="Additional notes…" />
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Update Asset' : 'Create Asset') }}
                    </button>
                    <Link href="/registry" class="btn-secondary">Cancel</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
