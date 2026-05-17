<script setup>
import { ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowPathIcon, UserPlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ extensions: Array, unassignedUsers: Array });
const page  = usePage();
const isAdmin = page.props.auth.user?.role === 'admin';

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
                <div class="flex items-center justify-between mt-auto">
                    <div class="text-xs text-gray-500">
                        <span v-if="ext.user">
                            <span class="font-medium text-gray-700">{{ ext.user.name }}</span>
                        </span>
                        <span v-else class="italic">Unassigned</span>
                    </div>
                    <button v-if="isAdmin" @click="openAssign(ext)" class="btn-secondary btn-sm">
                        <UserPlusIcon class="h-3.5 w-3.5" /> Assign
                    </button>
                </div>
            </div>

            <div v-if="!extensions.length" class="col-span-full text-center py-16 text-gray-400 text-sm">
                No extensions found. Sync from PBX first.
            </div>
        </div>

        <!-- Assign modal -->
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
    </AppLayout>
</template>
