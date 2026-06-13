<script setup>
import { ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowPathIcon, UserPlusIcon, KeyIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ extensions: Array, unassignedUsers: Array });
const page  = usePage();
const isAdmin = page.props.auth.user?.role === 'admin';

// ── Assign user modal ─────────────────────────────────────────────────────────
const assignModal = ref(null);
const assignForm  = useForm({ user_id: '' });

function openAssign(ext) {
    assignModal.value = ext;
    assignForm.user_id = ext.user?.id ?? '';
}

function saveAssign() {
    assignForm.post(`/extensions/${assignModal.value.id}/assign-user`, {
        onSuccess: () => { assignModal.value = null; },
    });
}

// ── SIP config modal ──────────────────────────────────────────────────────────
const sipModal = ref(null);
const sipForm  = useForm({ sip_password: '', sip_domain: '192.168.10.150' });

function openSip(ext) {
    sipModal.value = ext;
    sipForm.sip_password = ext.sip_password ?? '';
    sipForm.sip_domain   = ext.sip_domain   ?? '192.168.10.200';
}

function saveSip() {
    sipForm.put(`/extensions/${sipModal.value.id}`, {
        onSuccess: () => { sipModal.value = null; },
    });
}

// ── Other ─────────────────────────────────────────────────────────────────────
function sync() {
    router.post('/extensions/sync');
}

function statusColor(s) {
    return s === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500';
}
</script>

<template>
    <AppLayout>
        <template #title>Extensions</template>
        <template #header-actions>
            <button v-if="isAdmin" @click="sync" class="btn-secondary btn-sm">
                <ArrowPathIcon class="h-4 w-4" /> Sync from PBX
            </button>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div
                v-for="ext in extensions"
                :key="ext.id"
                class="card flex flex-col gap-3"
            >
                <div class="flex items-center justify-between">
                    <span class="text-2xl font-bold text-gray-900 font-mono">{{ ext.extension_number }}</span>
                    <span :class="['badge', statusColor(ext.status)]">{{ ext.status }}</span>
                </div>

                <p class="text-sm text-gray-600 font-medium">{{ ext.name ?? 'Unnamed' }}</p>

                <!-- SIP configured indicator -->
                <div class="flex items-center gap-1.5 text-xs"
                    :class="ext.sip_password ? 'text-green-600' : 'text-gray-400'">
                    <CheckCircleIcon v-if="ext.sip_password" class="h-3.5 w-3.5" />
                    <KeyIcon v-else class="h-3.5 w-3.5" />
                    {{ ext.sip_password ? 'Dialer configured' : 'Dialer not configured' }}
                </div>

                <div class="flex items-center justify-between mt-auto gap-2 flex-wrap">
                    <div class="text-xs text-gray-500">
                        <span v-if="ext.user" class="font-medium text-gray-700">{{ ext.user.name }}</span>
                        <span v-else class="italic">Unassigned</span>
                    </div>
                    <div class="flex gap-1.5">
                        <button v-if="isAdmin" @click="openSip(ext)"
                            class="btn-secondary btn-sm"
                            :title="ext.sip_password ? 'Edit SIP config' : 'Set up dialer'"
                        >
                            <KeyIcon class="h-3.5 w-3.5" />
                            {{ ext.sip_password ? 'SIP' : 'Set SIP' }}
                        </button>
                        <button v-if="isAdmin" @click="openAssign(ext)" class="btn-secondary btn-sm">
                            <UserPlusIcon class="h-3.5 w-3.5" /> Assign
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="!extensions.length" class="col-span-full text-center py-16 text-gray-400 text-sm">
                No extensions found. Sync from PBX first.
            </div>
        </div>

        <!-- Assign user modal -->
        <div v-if="assignModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-1">Assign Extension {{ assignModal.extension_number }}</h3>
                <p class="text-xs text-gray-500 mb-4">Select a user or leave blank to unassign.</p>
                <form @submit.prevent="saveAssign">
                    <select v-model="assignForm.user_id" class="input mb-4">
                        <option value="">— Unassign —</option>
                        <option v-for="u in unassignedUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                        <option v-if="assignModal.user" :value="assignModal.user.id">{{ assignModal.user.name }} (current)</option>
                    </select>
                    <div class="flex gap-2 justify-end">
                        <button type="button" @click="assignModal = null" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="assignForm.processing">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- SIP config modal -->
        <div v-if="sipModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <div class="flex items-center gap-2 mb-1">
                    <KeyIcon class="h-5 w-5 text-brand-600" />
                    <h3 class="font-semibold text-gray-900">
                        SIP / Dialer Config — Ext {{ sipModal.extension_number }}
                    </h3>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                    Find the SIP password in Yeastar:
                    <span class="font-medium text-gray-700">Extension → Edit → Security tab</span>
                </p>

                <form @submit.prevent="saveSip" class="space-y-3">
                    <div>
                        <label class="label">SIP Password <span class="text-red-500">*</span></label>
                        <input
                            v-model="sipForm.sip_password"
                            type="password"
                            class="input"
                            placeholder="Paste from Yeastar Security tab"
                            autocomplete="new-password"
                            required
                        />
                        <p v-if="sipForm.errors.sip_password" class="mt-1 text-xs text-red-600">
                            {{ sipForm.errors.sip_password }}
                        </p>
                    </div>

                    <div>
                        <label class="label">PBX IP / Domain</label>
                        <input
                            v-model="sipForm.sip_domain"
                            class="input"
                            placeholder="192.168.10.200"
                        />
                        <p class="mt-1 text-xs text-gray-400">The IP address of your Yeastar PBX.</p>
                    </div>

                    <div class="bg-blue-50 rounded-lg px-3 py-2 text-xs text-blue-700">
                        The agent assigned to this extension will be able to make and receive
                        calls directly in the browser using the in-built dialer.
                    </div>

                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="sipModal = null" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="sipForm.processing">
                            {{ sipForm.processing ? 'Saving…' : 'Save SIP Config' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
