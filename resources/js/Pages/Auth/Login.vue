<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

const form = useForm({ email: '', password: '', remember: false });
const showPassword = ref(false);

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Sign in" />

    <div class="min-h-screen flex flex-col items-center justify-center bg-[#f7f5fb] px-4 py-10">
        <div class="w-full max-w-sm">
            <img src="/logo.png" alt="Youth Advocates" class="mx-auto h-32 w-auto object-contain mix-blend-multiply" />

            <div class="card mt-2">
                <h1 class="text-xl font-semibold text-gray-900">Sign in</h1>
                <p class="mt-1 text-sm text-gray-500">Use your CRM email and password.</p>

                <form @submit.prevent="submit" class="mt-6 space-y-4" novalidate>
                    <div>
                        <label for="email" class="label">Email address</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                            autofocus
                            :class="['input', form.errors.email && 'border-red-400 focus:border-red-500 focus:ring-red-500/25']"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label for="password" class="label">Password</label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                :class="['input pr-10', form.errors.password && 'border-red-400 focus:border-red-500 focus:ring-red-500/25']"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            >
                                <EyeSlashIcon v-if="showPassword" class="h-4 w-4" />
                                <EyeIcon v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input v-model="form.remember" type="checkbox" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                        Remember me
                    </label>

                    <button type="submit" class="btn-primary w-full justify-center py-2.5" :disabled="form.processing">
                        {{ form.processing ? 'Signing in…' : 'Sign in' }}
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-xs text-gray-500">
                Forgot your password? Ask a CRM administrator to reset it.
            </p>
        </div>
    </div>
</template>
