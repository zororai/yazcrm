<script setup>
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon, TicketIcon, MicrophoneIcon, TrashIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ story: Object, isManager: Boolean, statuses: Array });

const statusLabels = { pending: 'Pending Review', approved: 'Approved', needs_revision: 'Needs Revision' };
const statusColor = {
    pending: 'bg-gray-100 text-gray-600',
    approved: 'bg-green-100 text-green-800',
    needs_revision: 'bg-amber-100 text-amber-800',
};

const reviewForm = useForm({ status: props.story.status, review_notes: props.story.review_notes ?? '' });
function submitReview() {
    reviewForm.post(`/success-stories/${props.story.id}/status`, { preserveScroll: true });
}

const lightboxPhoto = ref(null);

function destroy() {
    if (!confirm('Delete this success story? This cannot be undone.')) return;
    router.delete(`/success-stories/${props.story.id}`);
}
</script>

<template>
    <AppLayout>
        <template #title>{{ story.title }}</template>
        <template #header-actions>
            <a :href="`/success-stories/${story.id}/export-pdf`" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                <ArrowDownTrayIcon class="h-4 w-4" /> Download PDF
            </a>
            <Link href="/success-stories" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                <ArrowLeftIcon class="h-4 w-4" /> Back
            </Link>
        </template>

        <div class="card space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ story.title }}</h2>
                    <div class="flex items-center gap-3 text-xs text-gray-400 mt-1">
                        <span>{{ story.user?.name }}</span>
                        <Link :href="`/tickets/${story.ticket?.id}`" class="flex items-center gap-1 text-brand-600 hover:underline">
                            <TicketIcon class="h-3.5 w-3.5" /> Ticket #{{ story.ticket?.id }}
                        </Link>
                    </div>
                </div>
                <span :class="['badge shrink-0', statusColor[story.status] ?? 'bg-gray-100 text-gray-600']">
                    {{ statusLabels[story.status] ?? story.status }}
                </span>
            </div>

            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ story.story }}</p>

            <div v-if="story.photos.length">
                <p class="label mb-2">Photos</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <button v-for="p in story.photos" :key="p.id" type="button" @click="lightboxPhoto = p.path"
                        class="rounded-xl overflow-hidden h-32 bg-gray-100">
                        <img :src="`/storage/${p.path}`" class="h-full w-full object-cover hover:scale-105 transition-transform" />
                    </button>
                </div>
            </div>

            <div v-if="story.recording_id">
                <p class="label mb-1 flex items-center gap-1.5"><MicrophoneIcon class="h-4 w-4 text-brand-500" /> Call Recording</p>
                <audio controls preload="none" class="h-9 w-full" :src="`/api/recordings/${story.recording_id}/download`" />
            </div>

            <div v-if="story.reviewer" class="text-xs text-gray-400">
                Reviewed by {{ story.reviewer.name }} on {{ new Date(story.reviewed_at).toLocaleString() }}
            </div>

            <div v-if="!isManager" class="pt-2 border-t border-gray-100">
                <button type="button" @click="destroy" class="text-xs text-red-500 hover:underline inline-flex items-center gap-1">
                    <TrashIcon class="h-3.5 w-3.5" /> Delete this story
                </button>
            </div>
        </div>

        <!-- Review — manager only -->
        <div v-if="isManager" class="card mt-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Review</h3>
            <form @submit.prevent="submitReview" class="space-y-3">
                <div class="flex flex-wrap gap-2">
                    <button v-for="s in statuses" :key="s" type="button" @click="reviewForm.status = s"
                        :class="['px-3 py-1.5 rounded-full text-xs font-medium transition-colors',
                            reviewForm.status === s ? statusColor[s] + ' ring-2 ring-offset-1 ring-current' : 'bg-gray-100 text-gray-500 hover:bg-gray-200']">
                        {{ statusLabels[s] ?? s }}
                    </button>
                </div>
                <div>
                    <label class="label">Notes (optional)</label>
                    <textarea v-model="reviewForm.review_notes" rows="3" class="input resize-none" placeholder="Feedback for the agent…" />
                </div>
                <button type="submit" :disabled="reviewForm.processing" class="btn-primary btn-sm">
                    {{ reviewForm.processing ? 'Saving…' : 'Save Review' }}
                </button>
            </form>
        </div>

        <!-- Lightbox -->
        <div v-if="lightboxPhoto" class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-6" @click="lightboxPhoto = null">
            <img :src="`/storage/${lightboxPhoto}`" class="max-h-full max-w-full rounded-lg" />
        </div>
    </AppLayout>
</template>
