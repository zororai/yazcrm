<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import LsjpPersonForm from '@/Components/LsjpPersonForm.vue';
import {
    ArrowLeftIcon, PencilSquareIcon, TrashIcon, CheckCircleIcon, ExclamationTriangleIcon,
    ClockIcon, MinusCircleIcon, XMarkIcon, PhotoIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({ person: Object, schedule: Array, options: Object, canDelete: Boolean });

const page = usePage();
const referredTo = computed(() => page.props.referredTo ?? []);
const MAX_PHOTOS = 6;
const day = v => (v ? String(v).slice(0, 10) : '');
const today = new Date().toISOString().slice(0, 10);

const checkupFor = month => props.person.checkups.find(c => c.month === month) ?? null;
const sexLabel = { male: 'Male', female: 'Female', other: 'Other' };

const stateStyle = {
    done:     { cls: 'bg-green-100 text-green-800', icon: CheckCircleIcon,         text: 'Done' },
    overdue:  { cls: 'bg-red-100 text-red-800',     icon: ExclamationTriangleIcon, text: 'Overdue' },
    due_soon: { cls: 'bg-amber-100 text-amber-800', icon: ClockIcon,               text: 'Due soon' },
    upcoming: { cls: 'bg-gray-100 text-gray-500',   icon: MinusCircleIcon,         text: 'Upcoming' },
};
const progressStyle = {
    thriving: 'bg-green-100 text-green-800',
    progressing: 'bg-blue-100 text-blue-800',
    struggling: 'bg-amber-100 text-amber-800',
    not_started: 'bg-gray-100 text-gray-600',
    stopped: 'bg-red-100 text-red-800',
};

// ── Edit details ─────────────────────────────────────────────────────────────
const showEdit = ref(false);
// Every field must be declared up front — useForm only submits keys it was created with.
const editForm = useForm({
    full_name: '', id_number: '', age: '', sex: '', phone: '', province: '', district: '', location: '',
    key_population: '', current_activity: '', skill_trained: '', project: '', training_completed_on: '', notes: '',
});
function openEdit() {
    const p = props.person;
    Object.assign(editForm, {
        full_name: p.full_name ?? '', id_number: p.id_number ?? '', age: p.age ?? '', sex: p.sex ?? '',
        phone: p.phone ?? '', province: p.province ?? '', district: p.district ?? '', location: p.location ?? '',
        key_population: p.key_population ?? '', current_activity: p.current_activity ?? '',
        skill_trained: p.skill_trained ?? '', project: p.project ?? '',
        training_completed_on: day(p.training_completed_on), notes: p.notes ?? '',
    });
    editForm.clearErrors();
    showEdit.value = true;
}
function saveEdit() {
    editForm.put(`/lsjp/${props.person.id}`, { preserveScroll: true, onSuccess: () => { showEdit.value = false; } });
}

// ── Delete ───────────────────────────────────────────────────────────────────
const showDelete = ref(false);
const deleteForm = useForm({ reason: '' });
function confirmDelete() {
    deleteForm.delete(`/lsjp/${props.person.id}`);
}

// ── Record / edit a check-up ─────────────────────────────────────────────────
const checkupMonth = ref(null);
const checkupForm = useForm({
    conducted_on: '', progress: '', activity_status: '', challenges: '', comment: '',
    referred_to: '', referral_notes: '', photos: [],
});
const existingPhotos = computed(() => (checkupMonth.value ? checkupFor(checkupMonth.value)?.photos ?? [] : []));
const photoRoom = computed(() => MAX_PHOTOS - existingPhotos.value.length);
function openCheckup(month) {
    const c = checkupFor(month);
    checkupMonth.value = month;
    checkupForm.clearErrors();
    Object.assign(checkupForm, {
        conducted_on: day(c?.conducted_on) || today,
        progress: c?.progress ?? '',
        activity_status: c?.activity_status ?? props.person.current_activity ?? '',
        challenges: c?.challenges ?? '',
        comment: c?.comment ?? '',
        referred_to: c?.referred_to ?? '',
        referral_notes: c?.referral_notes ?? '',
        photos: [],
    });
}
function onPhotos(e) {
    checkupForm.photos = Array.from(e.target.files).slice(0, photoRoom.value);
}
function saveCheckup() {
    checkupForm.post(`/lsjp/${props.person.id}/checkups/${checkupMonth.value}`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { checkupMonth.value = null; },
    });
}
function removePhoto(photo) {
    if (!confirm('Remove this photo?')) return;
    router.delete(`/lsjp/photos/${photo.id}`, { preserveScroll: true });
}
const photoErrors = computed(() => Object.entries(checkupForm.errors).filter(([k]) => k.startsWith('photos')).map(([, v]) => v));

const lightbox = ref(null);
</script>

<template>
    <AppLayout>
        <template #title>{{ person.full_name }}</template>
        <template #subtitle>LSJP Register · trained {{ day(person.training_completed_on) }}<span v-if="person.skill_trained"> in {{ person.skill_trained }}</span></template>
        <template #header-actions>
            <div class="flex gap-2">
                <Link href="/lsjp" class="btn-secondary btn-sm inline-flex items-center gap-1"><ArrowLeftIcon class="h-4 w-4" /> Register</Link>
                <button @click="openEdit" class="btn-secondary btn-sm inline-flex items-center gap-1"><PencilSquareIcon class="h-4 w-4" /> Edit Details</button>
                <button v-if="canDelete" @click="showDelete = true" class="btn-secondary btn-sm inline-flex items-center gap-1 text-red-600"><TrashIcon class="h-4 w-4" /> Delete</button>
            </div>
        </template>

        <!-- Details -->
        <div class="card mb-4">
            <dl class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3 text-sm">
                <div><dt class="text-gray-500">ID number</dt><dd class="font-medium">{{ person.id_number || '—' }}</dd></div>
                <div><dt class="text-gray-500">Age / Sex</dt><dd class="font-medium">{{ person.age ?? '—' }}<span v-if="person.sex"> · {{ sexLabel[person.sex] }}</span></dd></div>
                <div><dt class="text-gray-500">Phone</dt><dd class="font-medium">{{ person.phone || '—' }}</dd></div>
                <div><dt class="text-gray-500">Key population</dt><dd class="font-medium">{{ person.key_population || '—' }}</dd></div>
                <div><dt class="text-gray-500">Location</dt><dd class="font-medium">{{ [person.location, person.district, person.province].filter(Boolean).join(', ') || '—' }}</dd></div>
                <div><dt class="text-gray-500">Current business / activity</dt><dd class="font-medium">{{ person.current_activity || '—' }}</dd></div>
                <div><dt class="text-gray-500">Skill taught</dt><dd class="font-medium">{{ person.skill_trained || '—' }}</dd></div>
                <div><dt class="text-gray-500">Project</dt><dd class="font-medium">{{ person.project || '—' }}</dd></div>
            </dl>
            <p v-if="person.notes" class="mt-3 text-sm text-gray-600 whitespace-pre-line">{{ person.notes }}</p>
            <p class="mt-3 text-xs text-gray-400">Added by {{ person.creator?.name ?? '—' }}</p>
        </div>

        <!-- Check-ups -->
        <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-400 mb-2">Progress check-ups</h2>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div v-for="s in schedule" :key="s.month" class="card flex flex-col">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ s.label }} check-up</h3>
                        <p class="text-xs text-gray-400">Due {{ s.due_date }}</p>
                    </div>
                    <span :class="['badge inline-flex items-center gap-1', stateStyle[s.state].cls]">
                        <component :is="stateStyle[s.state].icon" class="h-3.5 w-3.5" /> {{ stateStyle[s.state].text }}
                    </span>
                </div>

                <template v-if="checkupFor(s.month)">
                    <div class="mt-3 space-y-2 text-sm flex-1">
                        <p class="text-xs text-gray-400">Done {{ day(checkupFor(s.month).conducted_on) }} by {{ checkupFor(s.month).conducted_by?.name ?? '—' }}</p>
                        <span :class="['badge', progressStyle[checkupFor(s.month).progress]]">{{ options.progress[checkupFor(s.month).progress] }}</span>
                        <div v-if="checkupFor(s.month).activity_status">
                            <div class="text-xs text-gray-500">Now doing</div>
                            <div>{{ checkupFor(s.month).activity_status }}</div>
                        </div>
                        <div v-if="checkupFor(s.month).challenges">
                            <div class="text-xs text-gray-500">Challenges faced</div>
                            <div class="whitespace-pre-line">{{ checkupFor(s.month).challenges }}</div>
                        </div>
                        <div v-if="checkupFor(s.month).comment">
                            <div class="text-xs text-gray-500">Comment</div>
                            <div class="whitespace-pre-line">{{ checkupFor(s.month).comment }}</div>
                        </div>
                        <div v-if="checkupFor(s.month).referred_to || checkupFor(s.month).referral_notes">
                            <div class="text-xs text-gray-500">Referral</div>
                            <div>{{ checkupFor(s.month).referred_to }}</div>
                            <div v-if="checkupFor(s.month).referral_notes" class="text-gray-600 whitespace-pre-line">{{ checkupFor(s.month).referral_notes }}</div>
                        </div>
                        <div v-if="checkupFor(s.month).photos.length" class="grid grid-cols-3 gap-2 pt-1">
                            <div v-for="ph in checkupFor(s.month).photos" :key="ph.id" class="relative group aspect-square">
                                <img :src="`/storage/${ph.path}`" alt="Check-up photo" class="h-full w-full object-cover rounded cursor-pointer" @click="lightbox = ph.path" />
                                <button type="button" @click="removePhoto(ph)" class="absolute top-1 right-1 hidden group-hover:block bg-black/60 text-white rounded p-0.5" aria-label="Remove photo">
                                    <XMarkIcon class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <button @click="openCheckup(s.month)" class="btn-secondary btn-sm mt-4 self-start">Edit check-up</button>
                </template>
                <template v-else>
                    <p class="mt-3 text-sm text-gray-400 flex-1">Not recorded yet.</p>
                    <button @click="openCheckup(s.month)" class="btn-primary btn-sm mt-4 self-start">Record check-up</button>
                </template>
            </div>
        </div>

        <!-- Check-up form -->
        <div v-if="checkupMonth" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col">
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">{{ options.checkupMonths[checkupMonth] }} check-up — {{ person.full_name }}</h3>
                </div>
                <form @submit.prevent="saveCheckup" class="overflow-y-auto flex-1 px-6 py-4 space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Date of check-up *</label>
                            <input v-model="checkupForm.conducted_on" type="date" :min="day(person.training_completed_on)" :max="today" class="input" />
                            <p v-if="checkupForm.errors.conducted_on" class="mt-1 text-xs text-red-600">{{ checkupForm.errors.conducted_on }}</p>
                        </div>
                        <div>
                            <label class="label">Progress *</label>
                            <select v-model="checkupForm.progress" class="input">
                                <option value="">Choose…</option>
                                <option v-for="(label, key) in options.progress" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <p v-if="checkupForm.errors.progress" class="mt-1 text-xs text-red-600">{{ checkupForm.errors.progress }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="label">What they are doing now (business / activity)</label>
                        <input v-model="checkupForm.activity_status" class="input" />
                    </div>
                    <div>
                        <label class="label">Challenges faced</label>
                        <textarea v-model="checkupForm.challenges" class="input" rows="3" placeholder="e.g. No capital to buy materials, no market"></textarea>
                    </div>
                    <div>
                        <label class="label">Comment</label>
                        <textarea v-model="checkupForm.comment" class="input" rows="2"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label">Referred to</label>
                            <input v-model="checkupForm.referred_to" list="lsjp-referrals" class="input" placeholder="Pick or type" />
                            <datalist id="lsjp-referrals">
                                <option v-for="r in referredTo" :key="r" :value="r" />
                            </datalist>
                        </div>
                        <div>
                            <label class="label">Referral notes</label>
                            <input v-model="checkupForm.referral_notes" class="input" placeholder="Why / reference" />
                        </div>
                    </div>
                    <div>
                        <label class="label"><PhotoIcon class="h-4 w-4 inline" /> Add photos ({{ photoRoom }} more allowed, up to 5 MB each)</label>
                        <input v-if="photoRoom > 0" type="file" accept="image/*" multiple class="input" @change="onPhotos" />
                        <p v-else class="text-xs text-gray-400">This check-up already has {{ MAX_PHOTOS }} photos — remove one to add another.</p>
                        <p v-for="(e, i) in photoErrors" :key="i" class="mt-1 text-xs text-red-600">{{ e }}</p>
                        <p v-if="existingPhotos.length" class="mt-1 text-xs text-gray-400">{{ existingPhotos.length }} photo(s) already saved.</p>
                    </div>
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="checkupMonth = null" class="btn-secondary">Cancel</button>
                    <button type="button" @click="saveCheckup" class="btn-primary" :disabled="checkupForm.processing">
                        {{ checkupForm.processing ? 'Saving…' : 'Save Check-up' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit details -->
        <div v-if="showEdit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col">
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">Edit {{ person.full_name }}</h3>
                </div>
                <form @submit.prevent="saveEdit" class="overflow-y-auto flex-1 px-6 py-4">
                    <LsjpPersonForm :form="editForm" :province-districts="options.provinceDistricts" />
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="showEdit = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="saveEdit" class="btn-primary" :disabled="editForm.processing">Save Changes</button>
                </div>
            </div>
        </div>

        <!-- Delete -->
        <div v-if="showDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="font-semibold text-gray-900">Remove {{ person.full_name }} from the register?</h3>
                <p class="text-xs text-gray-500 mt-2">Their check-ups and photos are kept and the removal is recorded in the Audit Trail.</p>
                <label class="label mt-4">Reason *</label>
                <textarea v-model="deleteForm.reason" class="input" rows="3" placeholder="e.g. Registered twice"></textarea>
                <p v-if="deleteForm.errors.reason" class="mt-1 text-xs text-red-600">{{ deleteForm.errors.reason }}</p>
                <div class="flex gap-2 justify-end mt-4">
                    <button type="button" @click="showDelete = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="confirmDelete" class="btn-danger" :disabled="deleteForm.processing || !deleteForm.reason.trim()">Remove</button>
                </div>
            </div>
        </div>

        <!-- Photo lightbox -->
        <div v-if="lightbox" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @click="lightbox = null">
            <img :src="`/storage/${lightbox}`" alt="Check-up photo" class="max-h-full max-w-full rounded-lg" />
        </div>
    </AppLayout>
</template>
