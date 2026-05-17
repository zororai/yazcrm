<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, PencilIcon, TrashIcon, LockClosedIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ users: Array });

const showAdd  = ref(false);
const editUser = ref(null);
const resetUser = ref(null);

const addForm = useForm({ name: '', email: '', password: '', password_confirmation: '', role: 'agent' });
const editForm = useForm({ name: '', email: '', role: '' });
const resetForm = useForm({ password: '', password_confirmation: '' });

function openEdit(user) {
    editUser.value = user;
    editForm.name  = user.name;
    editForm.email = user.email;
    editForm.role  = user.role;
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
                            <option value="agent">Agent</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="admin">Admin</option>
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
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Edit {{ editUser.name }}</h3>
                <form @submit.prevent="update" class="space-y-3">
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
                        <select v-model="editForm.role" class="input">
                            <option value="agent">Agent</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="editUser = null" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="editForm.processing">Save</button>
                    </div>
                </form>
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
