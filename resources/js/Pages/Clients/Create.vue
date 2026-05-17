<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const form = useForm({
    name: '', phone: '', email: '', company: '', notes: '', whatsapp_number: '',
});

function submit() {
    form.post('/clients');
}
</script>

<template>
    <AppLayout>
        <template #title>New Client</template>

        <div class="max-w-2xl space-y-4">
            <Link href="/clients" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeftIcon class="h-4 w-4" /> Back to clients
            </Link>

            <div class="card">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label">Full Name *</label>
                            <input v-model="form.name" class="input" :class="{ 'border-red-500': form.errors.name }" required />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="label">Phone *</label>
                            <input v-model="form.phone" class="input" :class="{ 'border-red-500': form.errors.phone }" required />
                            <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="label">Email</label>
                            <input v-model="form.email" type="email" class="input" :class="{ 'border-red-500': form.errors.email }" />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="label">Company</label>
                            <input v-model="form.company" class="input" />
                        </div>
                        <div>
                            <label class="label">WhatsApp Number</label>
                            <input v-model="form.whatsapp_number" class="input" />
                        </div>
                    </div>
                    <div>
                        <label class="label">Notes</label>
                        <textarea v-model="form.notes" class="input h-24 resize-none" />
                    </div>
                    <div class="flex gap-2 justify-end pt-2">
                        <Link href="/clients" class="btn-secondary">Cancel</Link>
                        <button type="submit" class="btn-primary" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : 'Create Client' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
