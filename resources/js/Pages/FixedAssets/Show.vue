<script setup>
import { onMounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { UserPlusIcon, ArrowUturnLeftIcon, TruckIcon, TrashIcon, WrenchScrewdriverIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline';
import QRCode from 'qrcode';

const props = defineProps({
    asset: Object, assignments: Array, activityLogs: Array,
    maintenanceRecords: { type: Array, default: () => [] },
    inspections: { type: Array, default: () => [] },
    users: Array, departments: Array, locations: Array, isManager: Boolean,
});

const qrDataUrl = ref('');
onMounted(async () => {
    qrDataUrl.value = await QRCode.toDataURL(window.location.href, { width: 160, margin: 1 });
});

const CONDITIONS = ['new', 'excellent', 'good', 'fair', 'poor', 'damaged', 'unserviceable'];

const showAssign = ref(false);
const assignForm = useForm({ assigned_to: '', department_id: '', location_id: '', notes: '' });
function submitAssign() {
    assignForm.post(`/fixed-assets/${props.asset.id}/assign`, { onSuccess: () => { showAssign.value = false; assignForm.reset(); } });
}

const showReturn = ref(false);
const returnForm = useForm({ condition: 'good', notes: '' });
function submitReturn() {
    returnForm.post(`/fixed-assets/${props.asset.id}/return`, { onSuccess: () => { showReturn.value = false; returnForm.reset(); } });
}

const showTransfer = ref(false);
const transferForm = useForm({ department_id: '', location_id: '', notes: '' });
function submitTransfer() {
    transferForm.post(`/fixed-assets/${props.asset.id}/transfer`, { onSuccess: () => { showTransfer.value = false; transferForm.reset(); } });
}

function dispose() {
    const reason = prompt('Reason for disposal:');
    if (!reason) return;
    router.post(`/fixed-assets/${props.asset.id}/dispose`, { reason });
}

const MAINTENANCE_TYPES = ['routine_service', 'repair', 'inspection', 'calibration', 'cleaning', 'upgrade', 'preventive', 'corrective'];

const showMaintenance = ref(false);
const maintenanceForm = useForm({
    maintenance_type: 'repair', description: '', service_provider: '',
    service_date: new Date().toISOString().slice(0, 10), cost: '', next_service_date: '', notes: '',
});
function submitMaintenance() {
    maintenanceForm.post(`/fixed-assets/${props.asset.id}/maintenance`, {
        onSuccess: () => { showMaintenance.value = false; maintenanceForm.reset(); },
    });
}
function completeMaintenance(record) {
    if (!confirm(`Mark maintenance "${record.maintenance_type}" as completed? The asset will become available again.`)) return;
    router.post(`/fixed-assets/${props.asset.id}/maintenance/${record.id}/complete`);
}
function cancelMaintenance(record) {
    if (!confirm('Cancel this maintenance record?')) return;
    router.post(`/fixed-assets/${props.asset.id}/maintenance/${record.id}/cancel`);
}

const showInspection = ref(false);
const inspectionForm = useForm({
    inspected_at: new Date().toISOString().slice(0, 10), condition: 'good',
    working_status: 'working', damage_notes: '', comments: '',
});
function submitInspection() {
    inspectionForm.post(`/fixed-assets/${props.asset.id}/inspections`, {
        onSuccess: () => { showInspection.value = false; inspectionForm.reset(); },
    });
}

const statusColor = {
    available: 'bg-green-100 text-green-800',
    reserved: 'bg-blue-100 text-blue-800',
    assigned: 'bg-amber-100 text-amber-800',
    in_transit: 'bg-blue-100 text-blue-800',
    under_maintenance: 'bg-orange-100 text-orange-800',
    damaged: 'bg-red-100 text-red-800',
    lost: 'bg-red-100 text-red-800',
    stolen: 'bg-red-100 text-red-800',
    retired: 'bg-gray-200 text-gray-500',
    disposed: 'bg-gray-200 text-gray-500',
};
</script>

<template>
    <AppLayout>
        <template #title>{{ asset.name }}</template>
        <template #subtitle>{{ asset.asset_number }}</template>
        <template #header-actions>
            <div class="flex gap-2" v-if="isManager">
                <button v-if="asset.status === 'available'" @click="showAssign = true" class="btn-secondary btn-sm"><UserPlusIcon class="h-4 w-4" /> Assign</button>
                <button v-if="asset.status === 'assigned'" @click="showReturn = true" class="btn-secondary btn-sm"><ArrowUturnLeftIcon class="h-4 w-4" /> Return</button>
                <button v-if="!['disposed','retired'].includes(asset.status)" @click="showTransfer = true" class="btn-secondary btn-sm"><TruckIcon class="h-4 w-4" /> Transfer</button>
                <button v-if="!['assigned','disposed','retired'].includes(asset.status)" @click="showMaintenance = true" class="btn-secondary btn-sm"><WrenchScrewdriverIcon class="h-4 w-4" /> Schedule Maintenance</button>
                <button @click="showInspection = true" class="btn-secondary btn-sm"><ClipboardDocumentCheckIcon class="h-4 w-4" /> Record Inspection</button>
                <button v-if="asset.status !== 'disposed'" @click="dispose" class="btn-danger btn-sm"><TrashIcon class="h-4 w-4" /> Dispose</button>
            </div>
        </template>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="card text-sm text-gray-600 space-y-1">
                <div class="flex items-center gap-2 mb-2">
                    <span :class="['badge', statusColor[asset.status]]">{{ asset.status.replace('_', ' ') }}</span>
                    <span class="text-xs text-gray-400 capitalize">Condition: {{ asset.condition }}</span>
                </div>
                <p><span class="text-gray-400">Category:</span> {{ asset.category?.name ?? '—' }}</p>
                <p><span class="text-gray-400">Manufacturer / Model:</span> {{ asset.manufacturer ?? '—' }} {{ asset.model ?? '' }}</p>
                <p><span class="text-gray-400">Serial Number:</span> {{ asset.serial_number ?? '—' }}</p>
                <p><span class="text-gray-400">Custodian:</span> {{ asset.custodian?.name ?? '—' }}</p>
                <p><span class="text-gray-400">Department:</span> {{ asset.department?.name ?? '—' }}</p>
                <p><span class="text-gray-400">Location:</span> {{ asset.location?.name ?? '—' }}</p>
            </div>
            <div class="card text-sm text-gray-600 space-y-1">
                <p><span class="text-gray-400">Purchase Date:</span> {{ asset.purchase_date ? new Date(asset.purchase_date).toLocaleDateString() : '—' }}</p>
                <p><span class="text-gray-400">Purchase Cost:</span> {{ asset.purchase_cost ?? '—' }}</p>
                <p><span class="text-gray-400">Supplier:</span> {{ asset.supplier_name ?? '—' }}</p>
                <p><span class="text-gray-400">Warranty Expiry:</span> {{ asset.warranty_expiry ? new Date(asset.warranty_expiry).toLocaleDateString() : '—' }}</p>
                <p v-if="asset.description" class="pt-2">{{ asset.description }}</p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-4">
            <div class="card col-span-2">
                <h3 class="font-semibold text-gray-900 mb-2 text-sm">Assignment History</h3>
                <ul class="text-sm divide-y divide-gray-50">
                    <li v-for="a in assignments" :key="a.id" class="py-2">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ a.assignee?.name }}</span>
                            <span :class="['badge', a.status === 'active' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600']">{{ a.status }}</span>
                        </div>
                        <p class="text-xs text-gray-400">
                            {{ new Date(a.assigned_at).toLocaleDateString() }} by {{ a.assigned_by?.name }}
                            <span v-if="a.department"> — {{ a.department.name }}</span>
                            <span v-if="a.returned_at"> · returned {{ new Date(a.returned_at).toLocaleDateString() }} ({{ a.return_condition }})</span>
                        </p>
                    </li>
                    <li v-if="!assignments.length" class="text-gray-400 text-center py-4">Never assigned.</li>
                </ul>
            </div>
            <div class="card flex flex-col items-center justify-center">
                <img v-if="qrDataUrl" :src="qrDataUrl" alt="Asset QR code" class="w-32 h-32" />
                <p class="text-xs text-gray-400 mt-2 text-center">Scan to open this asset's page</p>
            </div>
        </div>

        <div class="card mb-4">
            <h3 class="font-semibold text-gray-900 mb-2 text-sm">Maintenance</h3>
            <ul class="text-sm divide-y divide-gray-50">
                <li v-for="m in maintenanceRecords" :key="m.id" class="py-2">
                    <div class="flex items-center justify-between">
                        <span class="font-medium capitalize">{{ m.maintenance_type.replace('_', ' ') }}</span>
                        <div class="flex items-center gap-2">
                            <span :class="['badge', m.status === 'scheduled' ? 'bg-amber-100 text-amber-800' : m.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500']">{{ m.status }}</span>
                            <template v-if="isManager && m.status === 'scheduled'">
                                <button @click="completeMaintenance(m)" class="text-xs text-green-600 hover:underline">Complete</button>
                                <button @click="cancelMaintenance(m)" class="text-xs text-red-600 hover:underline">Cancel</button>
                            </template>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400">
                        {{ new Date(m.service_date).toLocaleDateString() }}
                        <span v-if="m.service_provider"> — {{ m.service_provider }}</span>
                        <span v-if="m.cost"> — cost {{ m.cost }}</span>
                    </p>
                    <p v-if="m.description" class="text-xs text-gray-500">{{ m.description }}</p>
                </li>
                <li v-if="!maintenanceRecords.length" class="text-gray-400 text-center py-4">No maintenance recorded.</li>
            </ul>
        </div>

        <div class="card mb-4">
            <h3 class="font-semibold text-gray-900 mb-2 text-sm">Inspections</h3>
            <ul class="text-sm divide-y divide-gray-50">
                <li v-for="i in inspections" :key="i.id" class="py-2">
                    <div class="flex items-center justify-between">
                        <span class="font-medium">{{ i.inspector?.name }}</span>
                        <span class="badge bg-gray-100 text-gray-700 capitalize">{{ i.condition }} · {{ i.working_status.replace('_', ' ') }}</span>
                    </div>
                    <p class="text-xs text-gray-400">{{ new Date(i.inspected_at).toLocaleDateString() }}</p>
                    <p v-if="i.damage_notes" class="text-xs text-red-600">Damage: {{ i.damage_notes }}</p>
                    <p v-if="i.comments" class="text-xs text-gray-500">{{ i.comments }}</p>
                </li>
                <li v-if="!inspections.length" class="text-gray-400 text-center py-4">No inspections recorded.</li>
            </ul>
        </div>

        <details v-if="isManager && activityLogs.length" class="card">
            <summary class="font-semibold text-gray-900 cursor-pointer text-sm">History</summary>
            <ul class="mt-3 space-y-2 text-sm text-gray-600">
                <li v-for="log in activityLogs" :key="log.id">
                    <span class="font-medium text-gray-900">{{ log.user?.name }}</span> {{ log.action }}
                    <span v-if="log.old_status && log.new_status">({{ log.old_status }} → {{ log.new_status }})</span>
                    <span class="text-xs text-gray-400">— {{ new Date(log.created_at).toLocaleString() }}</span>
                    <p v-if="log.reason" class="text-xs text-gray-500 italic">Reason: {{ log.reason }}</p>
                </li>
            </ul>
        </details>

        <!-- Assign -->
        <div v-if="showAssign" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Assign Asset</h3>
                <form @submit.prevent="submitAssign" class="space-y-3">
                    <div>
                        <label class="label">Assign To</label>
                        <select v-model="assignForm.assigned_to" class="input" required>
                            <option value="" disabled>Select…</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Department</label>
                        <select v-model="assignForm.department_id" class="input">
                            <option value="">None</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Location</label>
                        <select v-model="assignForm.location_id" class="input">
                            <option value="">None</option>
                            <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Notes</label>
                        <textarea v-model="assignForm.notes" class="input" rows="2"></textarea>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showAssign = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="assignForm.processing">Assign</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Return -->
        <div v-if="showReturn" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Return Asset</h3>
                <form @submit.prevent="submitReturn" class="space-y-3">
                    <div>
                        <label class="label">Condition</label>
                        <select v-model="returnForm.condition" class="input" required>
                            <option v-for="c in CONDITIONS" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Notes</label>
                        <textarea v-model="returnForm.notes" class="input" rows="2" placeholder="Damage description, accessories returned…"></textarea>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showReturn = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="returnForm.processing">Return</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Transfer -->
        <div v-if="showTransfer" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Transfer Asset</h3>
                <form @submit.prevent="submitTransfer" class="space-y-3">
                    <div>
                        <label class="label">New Department</label>
                        <select v-model="transferForm.department_id" class="input">
                            <option value="">No change</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">New Location</label>
                        <select v-model="transferForm.location_id" class="input">
                            <option value="">No change</option>
                            <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Notes</label>
                        <textarea v-model="transferForm.notes" class="input" rows="2"></textarea>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showTransfer = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="transferForm.processing">Transfer</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Schedule Maintenance -->
        <div v-if="showMaintenance" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Schedule Maintenance</h3>
                <form @submit.prevent="submitMaintenance" class="space-y-3">
                    <div>
                        <label class="label">Type</label>
                        <select v-model="maintenanceForm.maintenance_type" class="input" required>
                            <option v-for="t in MAINTENANCE_TYPES" :key="t" :value="t">{{ t.replace('_', ' ') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Description</label>
                        <textarea v-model="maintenanceForm.description" class="input" rows="2"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Service Date</label>
                            <input v-model="maintenanceForm.service_date" type="date" class="input" required />
                        </div>
                        <div>
                            <label class="label">Cost</label>
                            <input v-model.number="maintenanceForm.cost" type="number" min="0" step="0.01" class="input" />
                        </div>
                    </div>
                    <div>
                        <label class="label">Service Provider</label>
                        <input v-model="maintenanceForm.service_provider" class="input" />
                    </div>
                    <div>
                        <label class="label">Next Service Date</label>
                        <input v-model="maintenanceForm.next_service_date" type="date" class="input" />
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showMaintenance = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="maintenanceForm.processing">Schedule</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Record Inspection -->
        <div v-if="showInspection" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Record Inspection</h3>
                <form @submit.prevent="submitInspection" class="space-y-3">
                    <div>
                        <label class="label">Inspection Date</label>
                        <input v-model="inspectionForm.inspected_at" type="date" class="input" required />
                    </div>
                    <div>
                        <label class="label">Condition</label>
                        <select v-model="inspectionForm.condition" class="input" required>
                            <option v-for="c in CONDITIONS" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Working Status</label>
                        <select v-model="inspectionForm.working_status" class="input" required>
                            <option value="working">Working</option>
                            <option value="partially_working">Partially Working</option>
                            <option value="not_working">Not Working</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Damage Notes</label>
                        <textarea v-model="inspectionForm.damage_notes" class="input" rows="2"></textarea>
                    </div>
                    <div>
                        <label class="label">Comments</label>
                        <textarea v-model="inspectionForm.comments" class="input" rows="2"></textarea>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showInspection = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="inspectionForm.processing">Save Inspection</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
