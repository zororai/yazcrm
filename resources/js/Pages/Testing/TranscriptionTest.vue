<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { MicrophoneIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ recentTestCalls: Array });

const form = useForm({ audio: null, label: '' });

function submit() {
    form.post('/transcription-test');
}
</script>

<template>
    <AppLayout>
        <template #title>Transcription Test Tool</template>

        <div class="max-w-2xl space-y-6">
            <div class="card">
                <h3 class="font-semibold text-gray-800 flex items-center gap-2 mb-1">
                    <MicrophoneIcon class="h-4 w-4 text-gray-400" /> Upload Test Audio
                </h3>
                <p class="text-sm text-gray-500 mb-4">
                    Upload any recording (wav, mp3, ogg, m4a) to instantly create a test call with that recording
                    attached, so you can run it through Transcribe Call and Generate AI Summary on the call's page —
                    no real PBX call required.
                </p>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="label">Audio File</label>
                        <input type="file" accept=".wav,.mp3,.ogg,.oga,.m4a" class="input"
                            @change="form.audio = $event.target.files[0]" required />
                        <p v-if="form.errors.audio" class="mt-1 text-xs text-red-600">{{ form.errors.audio }}</p>
                    </div>
                    <div>
                        <label class="label">Label (optional)</label>
                        <input v-model="form.label" class="input" placeholder="e.g. Ndebele sample 1" />
                    </div>
                    <button type="submit" class="btn-primary" :disabled="form.processing || !form.audio">
                        <ArrowUpTrayIcon class="h-4 w-4" /> {{ form.processing ? 'Uploading…' : 'Create Test Call' }}
                    </button>
                </form>
            </div>

            <div v-if="recentTestCalls.length" class="card">
                <h3 class="font-semibold text-gray-800 mb-3">Recent Test Calls</h3>
                <ul class="divide-y divide-gray-50">
                    <li v-for="c in recentTestCalls" :key="c.id" class="py-2">
                        <Link :href="`/calls/${c.id}`" class="text-brand-600 hover:underline text-sm font-medium">
                            {{ c.caller }}
                        </Link>
                        <span class="text-xs text-gray-400 ml-2">{{ new Date(c.created_at).toLocaleString() }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
