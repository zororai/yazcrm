<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, PencilIcon, TrashIcon, ShieldCheckIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    roles:      Array,
    validPerms: Array,
});

const PERM_LABELS = {
    dashboard:   'Dashboard',
    dialer:      'Dialer',
    calls:       'Calls',
    recordings:  'Recordings',
    callbacks:   'Callbacks',
    tickets:     'Tickets',
    urgent:      'Urgent Cases',
    directory:   'Service Directory',
    appraisals:  'Appraisals',
    appraisal_reviews: 'Appraisal Reviews',
    activity_reports: 'Activity Reports',
    work_management: 'Work Management',
    stores: 'Stores & Assets',
    extensions:  'Extensions',
    analytics:   'Analytics',
    targets:     'Call Targets',
    by_project:  'By Project (Stats)',
    domains:     'Distress Domains',
    bot_contacts:'Bot Contacts',
    users:       'Users Management',
    yeastar:     'Yeastar Settings',
    yalep:       'YALeP Students',
    registry:    'Asset Register',
    risk:        'Risk Register',
    sbc:         'SBC Signups',
    roles:       'Roles & Permissions',
};

// ── Create ────────────────────────────────────────────────────────────────────
const showCreate = ref(false);
const createForm = useForm({ display_name: '', nav_permissions: [] });

function toggleCreate(key) {
    const p = [...createForm.nav_permissions];
    const i = p.indexOf(key);
    i === -1 ? p.push(key) : p.splice(i, 1);
    createForm.nav_permissions = p;
}

function store() {
    createForm.post('/roles', {
        onSuccess: () => { showCreate.value = false; createForm.reset(); },
    });
}

// ── Edit ──────────────────────────────────────────────────────────────────────
const editRole = ref(null);
const editForm = useForm({ display_name: '', nav_permissions: [] });

function openEdit(role) {
    editRole.value             = role;
    editForm.display_name      = role.display_name;
    editForm.nav_permissions   = role.nav_permissions ? [...role.nav_permissions] : [];
}

function toggleEdit(key) {
    const p = [...editForm.nav_permissions];
    const i = p.indexOf(key);
    i === -1 ? p.push(key) : p.splice(i, 1);
    editForm.nav_permissions = p;
}

function update() {
    editForm.put(`/roles/${editRole.value.id}`, {
        onSuccess: () => { editRole.value = null; editForm.reset(); },
    });
}

function destroy(role) {
    if (!confirm(`Delete role "${role.display_name}"? Users with this role will keep their current permissions.`)) return;
    router.delete(`/roles/${role.id}`);
}
</script>

<template>
    <AppLayout>
        <template #title>
            <span class="flex items-center gap-2">
                <ShieldCheckIcon class="h-5 w-5 text-brand-400" />
                Roles & Permissions
            </span>
        </template>
        <template #header-actions>
            <button @click="showCreate = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Role
            </button>
        </template>

        <!-- Role cards grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="role in roles"
                :key="role.id"
                class="card flex flex-col gap-3"
            >
                <!-- Header -->
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-900">{{ role.display_name }}</span>
                            <span v-if="role.is_system"
                                class="badge bg-purple-100 text-purple-700 text-[10px]">Built-in</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5 font-mono">{{ role.name }}</p>
                    </div>
                    <div class="flex gap-1 flex-shrink-0">
                        <button @click="openEdit(role)" class="btn-secondary btn-sm" title="Edit">
                            <PencilIcon class="h-3.5 w-3.5" />
                        </button>
                        <button v-if="!role.is_system" @click="destroy(role)"
                            class="btn-danger btn-sm" title="Delete">
                            <TrashIcon class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Permission chips -->
                <div>
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">
                        Nav Access
                    </p>
                    <template v-if="role.name === 'admin'">
                        <span class="badge bg-green-100 text-green-700">All sections</span>
                    </template>
                    <template v-else-if="role.nav_permissions && role.nav_permissions.length">
                        <div class="flex flex-wrap gap-1">
                            <span v-for="p in role.nav_permissions" :key="p"
                                class="badge bg-brand-100 text-brand-700 text-[10px]">
                                {{ PERM_LABELS[p] ?? p }}
                            </span>
                        </div>
                    </template>
                    <template v-else>
                        <span class="text-xs text-gray-400 italic">No sidebar sections selected</span>
                    </template>
                </div>
            </div>
        </div>

        <!-- ── Create Role Modal ── -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">New Role</h3>
                    <button @click="showCreate = false" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <div class="overflow-y-auto flex-1 px-6 py-4 space-y-4">
                    <div>
                        <label class="label">Role Name *</label>
                        <input v-model="createForm.display_name" class="input"
                            :class="{ 'border-red-500': createForm.errors.display_name }"
                            placeholder="e.g. Data Analyst, Team Lead…" autofocus />
                        <p v-if="createForm.errors.display_name" class="mt-1 text-xs text-red-600">
                            {{ createForm.errors.display_name }}
                        </p>
                    </div>
                    <div>
                        <label class="label">Nav Permissions</label>
                        <p class="text-xs text-gray-400 mb-2">
                            Choose exactly which sidebar sections this role can see.
                        </p>
                        <div class="grid grid-cols-2 gap-2 rounded-lg border border-gray-200 p-3 bg-gray-50 max-h-80 overflow-y-auto">
                            <label v-for="key in validPerms" :key="key"
                                class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 py-1">
                                <input type="checkbox"
                                    :checked="createForm.nav_permissions.includes(key)"
                                    @change="toggleCreate(key)"
                                    class="rounded border-gray-300 text-brand-600" />
                                {{ PERM_LABELS[key] ?? key }}
                            </label>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="showCreate = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="store" class="btn-primary" :disabled="createForm.processing">
                        Create Role
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Edit Role Modal ── -->
        <div v-if="editRole" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">Edit Role — {{ editRole.display_name }}</h3>
                    <button @click="editRole = null" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <div class="overflow-y-auto flex-1 px-6 py-4 space-y-4">
                    <div>
                        <label class="label">Role Name</label>
                        <input v-model="editForm.display_name" class="input" />
                    </div>
                    <div v-if="editRole.name !== 'admin'">
                        <label class="label">Nav Permissions</label>
                        <p class="text-xs text-gray-400 mb-2">
                            Choose exactly which sidebar sections this role can see.
                        </p>
                        <div class="grid grid-cols-2 gap-2 rounded-lg border border-gray-200 p-3 bg-gray-50 max-h-80 overflow-y-auto">
                            <label v-for="key in validPerms" :key="key"
                                class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 py-1">
                                <input type="checkbox"
                                    :checked="editForm.nav_permissions.includes(key)"
                                    @change="toggleEdit(key)"
                                    class="rounded border-gray-300 text-brand-600" />
                                {{ PERM_LABELS[key] ?? key }}
                            </label>
                        </div>
                    </div>
                    <p v-else class="text-xs text-gray-400 italic">
                        Admin always has full access to all sections.
                    </p>
                </div>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="editRole = null" class="btn-secondary">Cancel</button>
                    <button type="button" @click="update" class="btn-primary" :disabled="editForm.processing">
                        Save Role
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
