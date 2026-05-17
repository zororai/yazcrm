<script setup>
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <GuestLayout>
        <h2 class="text-xl font-bold text-gray-900 mb-6">Sign in to your account</h2>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="label">Email address</label>
                <input
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    class="input"
                    :class="{ 'border-red-500': form.errors.email }"
                    placeholder="you@example.com"
                    required
                />
                <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="label">Password</label>
                <input
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    class="input"
                    :class="{ 'border-red-500': form.errors.password }"
                    required
                />
                <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
            </div>

            <div class="flex items-center gap-2">
                <input id="remember" v-model="form.remember" type="checkbox" class="rounded border-gray-300 text-brand-600" />
                <label for="remember" class="text-sm text-gray-600">Remember me</label>
            </div>

            <button
                type="submit"
                class="btn-primary w-full justify-center"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Signing in…' : 'Sign in' }}
            </button>
        </form>
    </GuestLayout>
</template>
