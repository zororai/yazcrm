<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, XMarkIcon, PhotoIcon, MicrophoneIcon, TicketIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ stories: Object, myTickets: Array, isManager: Boolean, filters: Object });

const statusFilter = ref(props.filters.status ?? '');
function runFilter() {
    router.get('/success-stories', { status: statusFilter.value || undefined }, { preserveState: true, replace: true });
}

const statusLabels = { pending: 'Pending Review', approved: 'Approved', needs_revision: 'Needs Revision' };
const statusColor = {
    pending: 'bg-gray-100 text-gray-600',
    approved: 'bg-green-100 text-green-800',
    needs_revision: 'bg-amber-100 text-amber-800',
};

// ── Create modal ──────────────────────────────────────────────────────────────
const showCreate = ref(false);
const photoInput = ref(null);
const photoPreviews = ref([]);
const ticketRecording = ref(null); // { recording_id, duration } | null

const form = useForm({
    ticket_id: '',
    title: '',
    story: '',
    recording_id: '',
    photos: [],
});

function openCreate(preselectedTicketId = '') {
    form.reset();
    photoPreviews.value = [];
    ticketRecording.value = null;
    showCreate.value = true;
    if (preselectedTicketId) form.ticket_id = preselectedTicketId;
}

// Arriving from /tickets with ?ticket_id=123 ("Mark Success") opens the
// create form straight to that ticket instead of an empty picker.
const initialTicketId = new URLSearchParams(window.location.search).get('ticket_id');
if (initialTicketId) openCreate(initialTicketId);

watch(() => form.ticket_id, async (ticketId) => {
    form.recording_id = '';
    ticketRecording.value = null;
    if (!ticketId) return;
    const res = await fetch(`/tickets/${ticketId}/recording`);
    const data = await res.json();
    if (data.recording_id) {
        ticketRecording.value = data;
        form.recording_id = data.recording_id;
    }
}, { immediate: true }); // picks up the ?ticket_id= preselection set above, before this watcher existed

function onPhotosSelected(e) {
    const files = Array.from(e.target.files ?? []).slice(0, 6);
    form.photos = files;
    photoPreviews.value = files.map(f => URL.createObjectURL(f));
}

function removePhoto(i) {
    form.photos = form.photos.filter((_, idx) => idx !== i);
    photoPreviews.value = photoPreviews.value.filter((_, idx) => idx !== i);
    if (photoInput.value) photoInput.value.value = '';
}

function submit() {
    form.post('/success-stories', {
        forceFormData: true,
        onSuccess: () => { showCreate.value = false; form.reset(); photoPreviews.value = []; },
    });
}

const selectedTicket = computed(() => props.myTickets.find(t => t.id == form.ticket_id));
</script>

<template>
    <AppLayout>
        <template #title>Success Stories</template>
        <template #header-actions>
            <button @click="openCreate()" class="btn-primary btn-sm inline-flex items-center gap-1.5">
                <PlusIcon class="h-4 w-4" /> New Success Story
            </button>
        </template>

        <div class="flex flex-wrap gap-2 mb-5">
            <button v-for="s in [{key:'',label:'All'},{key:'pending',label:'Pending'},{key:'approved',label:'Approved'},{key:'needs_revision',label:'Needs Revision'}]"
                :key="s.key" type="button" @click="statusFilter = s.key; runFilter()"
                :class="['px-3 py-1.5 rounded-full text-xs font-medium transition',
                    statusFilter === s.key ? 'bg-brand-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50']">
                {{ s.label }}
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link v-for="s in stories.data" :key="s.id" :href="`/success-stories/${s.id}`"
                class="block rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden hover:shadow-md hover:border-brand-100 transition">
                <div v-if="s.photos.length" class="h-36 bg-gray-100">
                    <img :src="`/storage/${s.photos[0].path}`" class="h-full w-full object-cover" />
                </div>
                <div v-else class="h-36 bg-gradient-to-br from-brand-50 to-indigo-50 flex items-center justify-center">
                    <PhotoIcon class="h-8 w-8 text-brand-200" />
                </div>
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2 mb-1.5">
                        <p class="font-semibold text-gray-900 text-sm truncate">{{ s.title }}</p>
                        <span :class="['badge shrink-0', statusColor[s.status] ?? 'bg-gray-100 text-gray-600']">{{ statusLabels[s.status] ?? s.status }}</span>
                    </div>
                    <p class="text-xs text-gray-500 line-clamp-2 mb-2">{{ s.story }}</p>
                    <div class="flex items-center gap-3 text-[11px] text-gray-400">
                        <span v-if="isManager">{{ s.user?.name }}</span>
                        <span class="flex items-center gap-1"><TicketIcon class="h-3 w-3" /> #{{ s.ticket?.id }}</span>
                        <span v-if="s.recording_id" class="flex items-center gap-1"><MicrophoneIcon class="h-3 w-3" /></span>
                        <span v-if="s.photos.length > 1">+{{ s.photos.length - 1 }} photo{{ s.photos.length > 2 ? 's' : '' }}</span>
                    </div>
                </div>
            </Link>

            <div v-if="!stories.data.length" class="col-span-full rounded-2xl bg-white border border-gray-100 py-16 text-center text-gray-400">
                No success stories yet.
            </div>
        </div>

        <div v-if="stories.last_page > 1" class="flex justify-center gap-1 mt-6">
            <Link v-for="link in stories.links" :key="link.label" :href="link.url ?? '#'"
                :class="['px-3 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                v-html="link.label" />
        </div>

        <!-- Create modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">New Success Story</h3>
                    <button @click="showCreate = false" class="text-gray-400 hover:text-gray-600"><XMarkIcon class="h-5 w-5" /></button>
                </div>

                <form @submit.prevent="submit" class="overflow-y-auto flex-1 px-6 py-4 space-y-4">
                    <div>
                        <label class="label">Ticket *</label>
                        <select v-model="form.ticket_id" class="input" :class="{ 'border-red-500': form.errors.ticket_id }" required>
                            <option value="">— select a ticket —</option>
                            <option v-for="t in myTickets" :key="t.id" :value="t.id">#{{ t.id }} — {{ t.subject }}</option>
                        </select>
                        <p v-if="form.errors.ticket_id" class="mt-1 text-xs text-red-600">{{ form.errors.ticket_id }}</p>
                    </div>

                    <div v-if="ticketRecording" class="rounded-xl bg-brand-50 border border-brand-100 px-3 py-2.5">
                        <label class="flex items-center gap-2 text-sm text-brand-700 cursor-pointer">
                            <input type="checkbox" :checked="!!form.recording_id"
                                @change="form.recording_id = $event.target.checked ? ticketRecording.recording_id : ''"
                                class="rounded border-gray-300 text-brand-600" />
                            Attach this ticket's call recording
                        </label>
                        <audio v-if="form.recording_id" controls preload="none" class="mt-2 h-8 w-full" :src="`/api/recordings/${ticketRecording.recording_id}/download`" />
                    </div>

                    <div>
                        <label class="label">Title *</label>
                        <input v-model="form.title" class="input" :class="{ 'border-red-500': form.errors.title }" required />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="label">Story *</label>
                        <textarea v-model="form.story" rows="6" class="input resize-none" :class="{ 'border-red-500': form.errors.story }"
                            placeholder="What happened, what changed, what was the outcome…" required />
                        <p v-if="form.errors.story" class="mt-1 text-xs text-red-600">{{ form.errors.story }}</p>
                    </div>

                    <div>
                        <label class="label">Photos (up to 6)</label>
                        <input ref="photoInput" type="file" accept="image/*" multiple @change="onPhotosSelected" class="input" />
                        <p v-if="form.errors.photos" class="mt-1 text-xs text-red-600">{{ form.errors.photos }}</p>
                        <div v-if="photoPreviews.length" class="grid grid-cols-3 sm:grid-cols-6 gap-2 mt-2">
                            <div v-for="(p, i) in photoPreviews" :key="i" class="relative">
                                <img :src="p" class="h-16 w-full object-cover rounded-lg" />
                                <button type="button" @click="removePhoto(i)"
                                    class="absolute -top-1.5 -right-1.5 bg-white rounded-full shadow p-0.5 text-gray-400 hover:text-red-500">
                                    <XMarkIcon class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="showCreate = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="submit" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'Submitting…' : 'Submit Story' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
