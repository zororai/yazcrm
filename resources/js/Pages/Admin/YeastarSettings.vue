<script setup>
import { ref, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import {
    EyeIcon, EyeSlashIcon, SignalIcon,
    CheckCircleIcon, XCircleIcon, ArrowPathIcon, LinkIcon, CircleStackIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    settings:           Object,
    webhook_registered: Boolean,
    db_connected:       Boolean,
});

const form = useForm({
    base_url:   props.settings.base_url   ?? '',
    app_id:     props.settings.app_id     ?? '',
    app_secret: props.settings.app_secret ?? '',
});

const webhookUrl     = ref(props.settings.webhook_url ?? '');
const showSecret     = ref(false);
const testResult     = reactive({ status: null, message: '' });
const webhookResult  = reactive({ status: null, message: '' });
const webhookOk      = ref(props.webhook_registered);

function save() {
    testResult.status = null;
    form.post('/yeastar-settings');
}

async function apiPost(url, body) {
    const res = await axios.post(url, body);
    return res.data;
}

async function testConnection() {
    testResult.status  = 'loading';
    testResult.message = '';
    try {
        const data = await apiPost('/yeastar-settings/test', {
            base_url: form.base_url, app_id: form.app_id, app_secret: form.app_secret,
        });
        testResult.status  = data.ok ? 'ok' : 'fail';
        testResult.message = data.message;
    } catch (e) {
        testResult.status  = 'fail';
        testResult.message = e.response?.data?.message ?? e.message;
    }
}

async function registerWebhook() {
    webhookResult.status  = 'loading';
    webhookResult.message = '';
    try {
        const data = await apiPost('/yeastar-settings/register-webhook', { webhook_url: webhookUrl.value });
        webhookResult.status  = data.ok ? 'ok' : 'fail';
        webhookResult.message = data.message;
        if (data.ok) webhookOk.value = true;
    } catch (e) {
        webhookResult.status  = 'fail';
        webhookResult.message = e.response?.data?.message ?? e.message;
    }
}
</script>

<template>
    <AppLayout>
        <template #title>Yeastar PBX Settings</template>

        <div class="max-w-2xl mx-auto space-y-6">

            <!-- ── API Credentials ─────────────────────────────────────── -->
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
                        <input v-model="form.base_url" type="url"
                            placeholder="https://192.168.10.150:8088/openapi/v1.0"
                            class="input" :class="{ 'border-red-400': form.errors.base_url }" />
                        <p v-if="form.errors.base_url" class="mt-1 text-xs text-red-600">{{ form.errors.base_url }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            App ID <span class="text-gray-400 font-normal">(API username)</span>
                        </label>
                        <input v-model="form.app_id" type="text"
                            placeholder="eIoWKKFS4WETS3FjsYohxJBrzoKIWuOZ"
                            class="input font-mono text-sm" :class="{ 'border-red-400': form.errors.app_id }" />
                        <p v-if="form.errors.app_id" class="mt-1 text-xs text-red-600">{{ form.errors.app_id }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            App Secret <span class="text-gray-400 font-normal">(API password)</span>
                        </label>
                        <div class="relative">
                            <input v-model="form.app_secret" :type="showSecret ? 'text' : 'password'"
                                placeholder="••••••••••••••••"
                                class="input font-mono text-sm pr-10"
                                :class="{ 'border-red-400': form.errors.app_secret }" />
                            <button type="button" @click="showSecret = !showSecret"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600">
                                <EyeSlashIcon v-if="showSecret" class="h-4 w-4" />
                                <EyeIcon      v-else            class="h-4 w-4" />
                            </button>
                        </div>
                        <p v-if="form.errors.app_secret" class="mt-1 text-xs text-red-600">{{ form.errors.app_secret }}</p>
                    </div>

                    <!-- Test result -->
                    <div v-if="testResult.status && testResult.status !== 'loading'"
                        :class="['flex items-start gap-2 p-3 rounded-lg text-sm border',
                            testResult.status === 'ok'
                                ? 'bg-green-50 border-green-200 text-green-800'
                                : 'bg-red-50 border-red-200 text-red-800']">
                        <CheckCircleIcon v-if="testResult.status === 'ok'" class="h-4 w-4 mt-0.5 flex-shrink-0" />
                        <XCircleIcon     v-else                            class="h-4 w-4 mt-0.5 flex-shrink-0" />
                        {{ testResult.message }}
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="btn-primary" :disabled="form.processing">
                            Save Settings
                        </button>
                        <button type="button" @click="testConnection" class="btn-secondary"
                            :disabled="testResult.status === 'loading'">
                            <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': testResult.status === 'loading' }" />
                            {{ testResult.status === 'loading' ? 'Testing…' : 'Test Connection' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- ── Real-time Webhook ────────────────────────────────────── -->
            <div class="card">
                <div class="flex items-center gap-3 mb-6">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-600">
                        <LinkIcon class="h-6 w-6 text-white" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Real-time Webhook</h2>
                        <p class="text-xs text-gray-500">
                            Registers a webhook with Yeastar so calls appear on the dashboard instantly — no manual sync needed.
                        </p>
                    </div>
                </div>

                <!-- Current status badge -->
                <div class="flex items-center gap-2 mb-5">
                    <span class="text-sm font-medium text-gray-700">Status:</span>
                    <span v-if="webhookOk"
                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <span class="h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse" />
                        Registered
                    </span>
                    <span v-else
                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400" />
                        Not registered
                    </span>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Webhook URL
                            <span class="text-gray-400 font-normal">(must be reachable from the PBX)</span>
                        </label>
                        <input v-model="webhookUrl" type="url"
                            class="input font-mono text-sm"
                            placeholder="http://your-server/api/webhooks/yeastar" />
                        <p class="mt-1 text-xs text-gray-400">
                            Make sure the PBX at 192.168.10.150 can reach this URL over the network.
                        </p>
                    </div>

                    <!-- Webhook result -->
                    <div v-if="webhookResult.status && webhookResult.status !== 'loading'"
                        :class="['flex items-start gap-2 p-3 rounded-lg text-sm border',
                            webhookResult.status === 'ok'
                                ? 'bg-green-50 border-green-200 text-green-800'
                                : 'bg-red-50 border-red-200 text-red-800']">
                        <CheckCircleIcon v-if="webhookResult.status === 'ok'" class="h-4 w-4 mt-0.5 flex-shrink-0" />
                        <XCircleIcon     v-else                               class="h-4 w-4 mt-0.5 flex-shrink-0" />
                        {{ webhookResult.message }}
                    </div>

                    <button type="button" @click="registerWebhook" class="btn-primary"
                        :disabled="webhookResult.status === 'loading'">
                        <ArrowPathIcon class="h-4 w-4" :class="{ 'animate-spin': webhookResult.status === 'loading' }" />
                        {{ webhookResult.status === 'loading' ? 'Registering…' : (webhookOk ? 'Re-register Webhook' : 'Register Webhook') }}
                    </button>
                </div>
            </div>

            <!-- ── Database Grant ──────────────────────────────────────── -->
            <div class="card">
                <div class="flex items-center gap-3 mb-6">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-600">
                        <CircleStackIcon class="h-6 w-6 text-white" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Database Grant (CDR)</h2>
                        <p class="text-xs text-gray-500">
                            Direct MySQL access — enables full CDR sync without API permission issues.
                            Enable in Yeastar portal under <strong>Integrations → Database Grant</strong>.
                        </p>
                    </div>
                </div>

                <!-- Connection status -->
                <div class="flex items-center gap-2 mb-5">
                    <span class="text-sm font-medium text-gray-700">Status:</span>
                    <span v-if="db_connected"
                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
                        Connected
                    </span>
                    <span v-else
                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400" />
                        Not configured
                    </span>
                </div>

                <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 mb-4 text-xs text-amber-800">
                    Add these to your <code class="font-mono bg-amber-100 px-1 rounded">.env</code> file,
                    then add <strong>192.168.10.10</strong> to the Permitted IP list in the PBX portal.
                </div>

                <div class="space-y-1 font-mono text-xs bg-gray-900 text-green-300 rounded-lg p-4 select-all leading-relaxed">
                    <p>DB_YEASTAR_HOST=<span class="text-yellow-300">{{ settings.db_host || '&lt;Database Address from PBX portal&gt;' }}</span></p>
                    <p>DB_YEASTAR_PORT=<span class="text-yellow-300">15023</span></p>
                    <p>DB_YEASTAR_DATABASE=<span class="text-yellow-300">asterisk</span></p>
                    <p>DB_YEASTAR_USERNAME=<span class="text-yellow-300">{{ settings.db_username || '&lt;username from PBX portal&gt;' }}</span></p>
                    <p>DB_YEASTAR_PASSWORD=<span class="text-yellow-300">&lt;password from PBX portal&gt;</span></p>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
