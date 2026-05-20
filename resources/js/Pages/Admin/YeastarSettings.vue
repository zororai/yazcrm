<script setup>
import { ref, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { EyeIcon, EyeSlashIcon, SignalIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline';
import { ArrowPathIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ settings: Object });

const form = useForm({
    base_url:   props.settings.base_url   ?? '',
    app_id:     props.settings.app_id     ?? '',
    app_secret: props.settings.app_secret ?? '',
});

const showSecret  = ref(false);
const testResult  = reactive({ status: null, message: '' }); // null | 'ok' | 'fail' | 'loading'

function save() {
    testResult.status = null;
    form.post('/yeastar-settings');
}

async function testConnection() {
    testResult.status  = 'loading';
    testResult.message = '';

    try {
        const res = await fetch('/yeastar-settings/test', {
            method:  'POST',
            headers: {
                'Content-Type':     'application/json',
                'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                base_url:   form.base_url,
                app_id:     form.app_id,
                app_secret: form.app_secret,
            }),
        });

        const data = await res.json();
        testResult.status  = data.ok ? 'ok' : 'fail';
        testResult.message = data.message;
    } catch (e) {
        testResult.status  = 'fail';
        testResult.message = 'Request failed: ' + e.message;
    }
}
</script>

<template>
    <AppLayout>
        <template #title>Yeastar PBX Settings</template>

        <div class="max-w-2xl mx-auto">
            <div class="card">
                <div class="flex items-center gap-3 mb-6">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-600">
                        <SignalIcon class="h-6 w-6 text-white" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">API Connection</h2>
                        <p class="text-xs text-gray-500">Credentials are stored in the database and override .env values.</p>
                    </div>
                </div>

                <form @submit.prevent="save" class="space-y-5">
                    <!-- Base URL -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
                        <input
                            v-model="form.base_url"
                            type="url"
                            placeholder="http://192.168.10.150:8088/openapi/v1.0"
                            class="input"
                            :class="{ 'border-red-400': form.errors.base_url }"
                        />
                        <p v-if="form.errors.base_url" class="mt-1 text-xs text-red-600">{{ form.errors.base_url }}</p>
                    </div>

                    <!-- App ID -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            App ID <span class="text-gray-400 font-normal">(API username)</span>
                        </label>
                        <input
                            v-model="form.app_id"
                            type="text"
                            placeholder="eIoWKKFS4WETS3FjsYohxJBrzoKIWuOZ"
                            class="input font-mono text-sm"
                            :class="{ 'border-red-400': form.errors.app_id }"
                        />
                        <p v-if="form.errors.app_id" class="mt-1 text-xs text-red-600">{{ form.errors.app_id }}</p>
                    </div>

                    <!-- App Secret -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            App Secret <span class="text-gray-400 font-normal">(API password)</span>
                        </label>
                        <div class="relative">
                            <input
                                v-model="form.app_secret"
                                :type="showSecret ? 'text' : 'password'"
                                placeholder="••••••••••••••••"
                                class="input font-mono text-sm pr-10"
                                :class="{ 'border-red-400': form.errors.app_secret }"
                            />
                            <button
                                type="button"
                                @click="showSecret = !showSecret"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                            >
                                <EyeSlashIcon v-if="showSecret" class="h-4 w-4" />
                                <EyeIcon      v-else           class="h-4 w-4" />
                            </button>
                        </div>
                        <p v-if="form.errors.app_secret" class="mt-1 text-xs text-red-600">{{ form.errors.app_secret }}</p>
                    </div>

                    <!-- Test result banner -->
                    <div
                        v-if="testResult.status && testResult.status !== 'loading'"
                        :class="[
                            'flex items-start gap-2 p-3 rounded-lg text-sm border',
                            testResult.status === 'ok'
                                ? 'bg-green-50 border-green-200 text-green-800'
                                : 'bg-red-50 border-red-200 text-red-800',
                        ]"
                    >
                        <CheckCircleIcon v-if="testResult.status === 'ok'" class="h-4 w-4 mt-0.5 flex-shrink-0" />
                        <XCircleIcon     v-else                            class="h-4 w-4 mt-0.5 flex-shrink-0" />
                        {{ testResult.message }}
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-3 pt-2">
                        <button
                            type="submit"
                            class="btn-primary"
                            :disabled="form.processing"
                        >
                            Save Settings
                        </button>

                        <button
                            type="button"
                            @click="testConnection"
                            class="btn-secondary"
                            :disabled="testResult.status === 'loading'"
                        >
                            <ArrowPathIcon
                                class="h-4 w-4"
                                :class="{ 'animate-spin': testResult.status === 'loading' }"
                            />
                            {{ testResult.status === 'loading' ? 'Testing…' : 'Test Connection' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
