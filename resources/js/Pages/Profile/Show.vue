<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ profileUser: Object });

const showPassword = ref(false);
const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

function submitPassword() {
    passwordForm.put('/profile/password', {
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <AppLayout>
        <template #title>My Profile</template>

        <div class="grid grid-cols-2 gap-4 max-w-3xl">
            <div class="card">
                <h3 class="font-semibold text-gray-900 mb-3">Account Details</h3>
                <div class="space-y-2 text-sm text-gray-600">
                    <p><span class="text-gray-400">Name:</span> {{ profileUser.name }}</p>
                    <p><span class="text-gray-400">Email:</span> {{ profileUser.email }}</p>
                    <p><span class="text-gray-400">Role:</span> <span class="capitalize">{{ profileUser.role }}</span></p>
                    <p><span class="text-gray-400">Supervisor:</span> {{ profileUser.supervisor?.name ?? '—' }}</p>
                    <p v-if="profileUser.extension"><span class="text-gray-400">Extension:</span> {{ profileUser.extension.number }}</p>
                    <p><span class="text-gray-400">Last Login:</span> {{ profileUser.last_login_at ? new Date(profileUser.last_login_at).toLocaleString() : '—' }}</p>
                    <p><span class="text-gray-400">Status:</span> {{ profileUser.is_active ? 'Active' : 'Inactive' }}</p>
                </div>
            </div>

            <div class="card">
                <h3 class="font-semibold text-gray-900 mb-3">Change Password</h3>
                <form @submit.prevent="submitPassword" class="space-y-3">
                    <div>
                        <label class="label">Current Password</label>
                        <input v-model="passwordForm.current_password" :type="showPassword ? 'text' : 'password'" class="input" required autocomplete="current-password" />
                        <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-red-600">{{ passwordForm.errors.current_password }}</p>
                    </div>
                    <div>
                        <label class="label">New Password</label>
                        <input v-model="passwordForm.password" :type="showPassword ? 'text' : 'password'" class="input" required autocomplete="new-password" />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-red-600">{{ passwordForm.errors.password }}</p>
                    </div>
                    <div>
                        <label class="label">Confirm New Password</label>
                        <input v-model="passwordForm.password_confirmation" :type="showPassword ? 'text' : 'password'" class="input" required autocomplete="new-password" />
                    </div>
                    <label class="flex items-center gap-2 text-xs text-gray-500">
                        <input type="checkbox" v-model="showPassword" /> Show passwords
                    </label>
                    <div class="flex justify-end pt-1">
                        <button type="submit" class="btn-primary" :disabled="passwordForm.processing">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
