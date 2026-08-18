<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, PencilIcon, TrashIcon, LockClosedIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ users: Array, roles: Array });

const showAdd  = ref(false);
const editUser = ref(null);
const resetUser = ref(null);

const addForm  = useForm({ name: '', email: '', password: '', password_confirmation: '', role: 'agent' });
const editForm = useForm({ name: '', email: '', role: '', supervisor_id: '', nav_permissions: [] });
const resetForm = useForm({ password: '', password_confirmation: '' });

const NAV_ITEMS = [
    { key: 'dashboard',   label: 'Dashboard' },
    { key: 'dialer',      label: 'Dialer' },
    { key: 'calls',       label: 'Calls' },
    { key: 'recordings',  label: 'Recordings' },
    { key: 'callbacks',   label: 'Callbacks' },
    { key: 'tickets',     label: 'Tickets' },
    { key: 'urgent',      label: 'Urgent Cases' },
    { key: 'directory',   label: 'Service Directory' },
    { key: 'appraisals',  label: 'Appraisals' },
    { key: 'appraisal_reviews', label: 'Appraisal Reviews' },
    { key: 'extensions',  label: 'Extensions' },
    { key: 'analytics',   label: 'Analytics' },
    { key: 'targets',     label: 'Call Targets' },
    { key: 'by_project',  label: 'By Project (Stats)' },
    { key: 'domains',     label: 'Distress Domains' },
    { key: 'bot_contacts',label: 'Bot Contacts' },
    { key: 'registry',    label: 'Asset Register' },
    { key: 'risk',        label: 'Risk Register' },
    { key: 'sbc',         label: 'SBC Signups' },
    { key: 'yalep',       label: 'YALeP Students' },
    { key: 'roles',       label: 'Roles & Permissions' },
    { key: 'users',       label: 'Users Management' },
    { key: 'yeastar',     label: 'Yeastar Settings' },
];

const editIsAdmin = computed(() => editForm.role === 'admin');

function openEdit(user) {
    editUser.value           = user;
    editForm.name            = user.name;
    editForm.email           = user.email;
    editForm.role            = user.role;
    editForm.supervisor_id   = user.supervisor_id ?? '';
    editForm.nav_permissions = user.nav_permissions ?? [];
}

function togglePerm(key) {
    const perms = [...editForm.nav_permissions];
    const idx = perms.indexOf(key);
    if (idx === -1) perms.push(key);
    else perms.splice(idx, 1);
    editForm.nav_permissions = perms;
}

// When role changes in edit form, auto-load that role's default permissions
function onEditRoleChange(roleName) {
    editForm.role = roleName;
    if (roleName === 'admin') { editForm.nav_permissions = []; return; }
    const role = props.roles.find(r => r.name === roleName);
    editForm.nav_permissions = role?.nav_permissions ? [...role.nav_permissions] : [];
}

function store() {
    addForm.post('/users', { onSuccess: () => { showAdd.value = false; addForm.reset(); } });
}

function update() {
    editForm.put(`/users/${editUser.value.id}`, { onSuccess: () => { editUser.value = null; } });
}

function doReset() {
    resetForm.post(`/users/${resetUser.value.id}/reset-password`, { onSuccess: () => { resetUser.value = null; resetForm.reset(); } });
}

function toggle(user) {
    router.post(`/users/${user.id}/toggle-active`);
}

function destroy(user) {
    if (!confirm(`Delete ${user.name}?`)) return;
    router.delete(`/users/${user.id}`);
}

const roleColor = {
    admin:      'bg-purple-100 text-purple-800',
    supervisor: 'bg-blue-100 text-blue-800',
    agent:      'bg-gray-100 text-gray-700',
};
</script>

<template>
    <AppLayout>
        <template #title>Users</template>
        <template #header-actions>
            <button @click="showAdd = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New User
            </button>
        </template>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Name</th>
                        <th class="table-th">Email</th>
                        <th class="table-th">Role</th>
                        <th class="table-th">Extension</th>
                        <th class="table-th">Last Login</th>
                        <th class="table-th">Status</th>
                        <th class="table-th w-32" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium">{{ u.name }}</td>
                        <td class="table-td text-xs">{{ u.email }}</td>
                        <td class="table-td">
                            <span :class="['badge', roleColor[u.role]]">{{ u.role }}</span>
                        </td>
                        <td class="table-td font-mono text-xs">{{ u.extension?.number ?? '—' }}</td>
                        <td class="table-td text-xs">{{ u.last_login_at ? new Date(u.last_login_at).toLocaleDateString() : '—' }}</td>
                        <td class="table-td">
                            <button
                                @click="toggle(u)"
                                :class="['badge cursor-pointer', u.is_active ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-red-100 text-red-700 hover:bg-red-200']"
                            >
                                {{ u.is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </td>
                        <td class="table-td">
                            <div class="flex gap-1">
                                <button @click="openEdit(u)" class="btn-secondary btn-sm" title="Edit">
                                    <PencilIcon class="h-3.5 w-3.5" />
                                </button>
                                <button @click="resetUser = u" class="btn-secondary btn-sm" title="Reset password">
                                    <LockClosedIcon class="h-3.5 w-3.5" />
                                </button>
                                <button @click="destroy(u)" class="btn-danger btn-sm">
                                    <TrashIcon class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Add user modal -->
        <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">New User</h3>
                <form @submit.prevent="store" class="space-y-3">
                    <div>
                        <label class="label">Name</label>
                        <input v-model="addForm.name" class="input" required />
                        <p v-if="addForm.errors.name" class="mt-1 text-xs text-red-600">{{ addForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="label">Email</label>
                        <input v-model="addForm.email" type="email" class="input" required />
                        <p v-if="addForm.errors.email" class="mt-1 text-xs text-red-600">{{ addForm.errors.email }}</p>
                    </div>
                    <div>
                        <label class="label">Role</label>
                        <select v-model="addForm.role" class="input">
                            <option v-for="r in roles" :key="r.name" :value="r.name">
                                {{ r.display_name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Password</label>
                        <input v-model="addForm.password" type="password" class="input" required />
                    </div>
                    <div>
                        <label class="label">Confirm Password</label>
                        <input v-model="addForm.password_confirmation" type="password" class="input" required />
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="showAdd = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="addForm.processing">Create</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit user modal -->
        <div v-if="editUser" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[90vh] flex flex-col">
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">Edit {{ editUser.name }}</h3>
                </div>
                <form @submit.prevent="update" class="overflow-y-auto flex-1 px-6 py-4 space-y-4">
                    <div>
                        <label class="label">Name</label>
                        <input v-model="editForm.name" class="input" required />
                    </div>
                    <div>
                        <label class="label">Email</label>
                        <input v-model="editForm.email" type="email" class="input" required />
                    </div>
                    <div>
                        <label class="label">Role</label>
                        <select :value="editForm.role" @change="onEditRoleChange($event.target.value)" class="input">
                            <option v-for="r in roles" :key="r.name" :value="r.name">
                                {{ r.display_name }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Changing the role automatically updates permissions below.</p>
                    </div>
                    <div>
                        <label class="label">Supervisor</label>
                        <select v-model="editForm.supervisor_id" class="input">
                            <option value="">— None —</option>
                            <option v-for="u in users.filter(u => u.id !== editUser.id)" :key="u.id" :value="u.id">
                                {{ u.name }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Used to route this staff member's performance appraisals for review.</p>
                    </div>

                    <!-- Nav permissions (hidden for admins — they get everything) -->
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <ShieldCheckIcon class="h-4 w-4 text-brand-600" />
                            <span class="text-sm font-semibold text-gray-700">Nav Permissions</span>
                        </div>
                        <p v-if="editIsAdmin" class="text-xs text-gray-400 italic">
                            Admins have access to everything — no restrictions apply.
                        </p>
                        <template v-else>
                            <p class="text-xs text-gray-500 mb-3">
                                Choose exactly which sections this user can see in the sidebar.
                            </p>
                            <div class="grid grid-cols-2 gap-2 max-h-72 overflow-y-auto">
                                <label
                                    v-for="item in NAV_ITEMS"
                                    :key="item.key"
                                    class="flex items-center gap-2 cursor-pointer rounded-md px-2 py-1.5 text-sm text-gray-700 hover:bg-white transition-colors"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="editForm.nav_permissions.includes(item.key)"
                                        @change="togglePerm(item.key)"
                                        class="rounded border-gray-300 text-brand-600"
                                    />
                                    {{ item.label }}
                                </label>
                            </div>
                        </template>
                    </div>
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="editUser = null" class="btn-secondary">Cancel</button>
                    <button type="button" @click="update" class="btn-primary" :disabled="editForm.processing">Save</button>
                </div>
            </div>
        </div>

        <!-- Reset password modal -->
        <div v-if="resetUser" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Reset Password — {{ resetUser.name }}</h3>
                <form @submit.prevent="doReset" class="space-y-3">
                    <div>
                        <label class="label">New Password</label>
                        <input v-model="resetForm.password" type="password" class="input" required />
                    </div>
                    <div>
                        <label class="label">Confirm Password</label>
                        <input v-model="resetForm.password_confirmation" type="password" class="input" required />
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="resetUser = null" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="resetForm.processing">Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
