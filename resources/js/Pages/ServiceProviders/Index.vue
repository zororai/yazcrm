<script setup>
import { ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { MagnifyingGlassIcon, PlusIcon, PencilIcon, TrashIcon, XMarkIcon, PhoneIcon, EnvelopeIcon, MapPinIcon } from '@heroicons/vue/24/outline';
import { debounce } from 'lodash-es';

const props = defineProps({ providers: Array, chapters: Array, filters: Object });
const isAdmin = usePage().props.auth.user?.role === 'admin';

const search  = ref(props.filters.search  ?? '');
const chapter = ref(props.filters.chapter ?? '');

function apply() {
    router.get('/service-directory', {
        search:  search.value  || undefined,
        chapter: chapter.value || undefined,
    }, { preserveState: true, replace: true });
}

const debouncedApply = debounce(apply, 350);

// ── Add / Edit modal (admin only) ─────────────────────────────────────────────
const showForm = ref(false);
const editing  = ref(null);
const form = useForm({ name: '', organisation: '', phone: '', email: '', physical_address: '', services_offered: '', notes: '', chapter: '' });

function openAdd() {
    editing.value = null;
    form.reset();
    showForm.value = true;
}

function openEdit(p) {
    editing.value = p;
    form.name = p.name;
    form.organisation = p.organisation;
    form.phone = p.phone ?? '';
    form.email = p.email ?? '';
    form.physical_address = p.physical_address ?? '';
    form.services_offered = p.services_offered ?? '';
    form.notes = p.notes ?? '';
    form.chapter = p.chapter ?? '';
    showForm.value = true;
}

function save() {
    const options = { onSuccess: () => { showForm.value = false; } };
    if (editing.value) {
        form.put(`/service-directory/${editing.value.id}`, options);
    } else {
        form.post('/service-directory', options);
    }
}

function destroy(p) {
    if (!confirm(`Remove "${p.organisation}" from the directory?`)) return;
    router.delete(`/service-directory/${p.id}`);
}
</script>

<template>
    <AppLayout>
        <template #title>Service Directory</template>
        <template #header-actions>
            <button v-if="isAdmin" @click="openAdd" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> Add Provider
            </button>
        </template>

        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[220px]">
                <label class="label">Search</label>
                <div class="relative">
                    <MagnifyingGlassIcon class="h-4 w-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input v-model="search" @input="debouncedApply" class="input pl-9" placeholder="Name, organisation, phone, email, service…" />
                </div>
            </div>
            <div>
                <label class="label">Chapter</label>
                <select v-model="chapter" @change="apply" class="input w-48">
                    <option value="">All Chapters</option>
                    <option v-for="c in chapters" :key="c" :value="c">{{ c }}</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="p in providers" :key="p.id" class="card flex flex-col gap-2">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-gray-900">{{ p.organisation }}</p>
                        <p v-if="p.name && p.name !== p.organisation" class="text-xs text-gray-500">{{ p.name }}</p>
                    </div>
                    <span v-if="p.chapter" class="badge bg-brand-100 text-brand-700 shrink-0">{{ p.chapter }}</span>
                </div>

                <div class="text-sm text-gray-600 space-y-1 mt-1">
                    <a v-if="p.phone" :href="`tel:${p.phone}`" class="flex items-center gap-1.5 hover:text-brand-600">
                        <PhoneIcon class="h-3.5 w-3.5 shrink-0" /> {{ p.phone }}
                    </a>
                    <a v-if="p.email" :href="`mailto:${p.email}`" class="flex items-center gap-1.5 hover:text-brand-600 truncate">
                        <EnvelopeIcon class="h-3.5 w-3.5 shrink-0" /> <span class="truncate">{{ p.email }}</span>
                    </a>
                    <p v-if="p.physical_address" class="flex items-start gap-1.5 text-gray-500">
                        <MapPinIcon class="h-3.5 w-3.5 shrink-0 mt-0.5" /> {{ p.physical_address }}
                    </p>
                </div>

                <p v-if="p.services_offered" class="text-xs text-gray-600 bg-gray-50 rounded-lg px-2.5 py-2 mt-1">
                    {{ p.services_offered }}
                </p>
                <p v-if="p.notes" class="text-xs text-gray-400 italic">{{ p.notes }}</p>

                <div v-if="isAdmin" class="flex gap-2 justify-end mt-auto pt-2 border-t border-gray-100">
                    <button @click="openEdit(p)" class="text-gray-400 hover:text-blue-600" title="Edit">
                        <PencilIcon class="h-4 w-4" />
                    </button>
                    <button @click="destroy(p)" class="text-gray-400 hover:text-red-600" title="Delete">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div v-if="!providers.length" class="col-span-full text-center py-16 text-gray-400 text-sm">
                No service providers found.
            </div>
        </div>

        <!-- Add/Edit modal -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">{{ editing ? 'Edit Provider' : 'Add Provider' }}</h3>
                    <button @click="showForm = false" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                <form @submit.prevent="save" class="overflow-y-auto flex-1 px-6 py-4 space-y-3">
                    <div>
                        <label class="label">Organisation *</label>
                        <input v-model="form.organisation" class="input" required />
                        <p v-if="form.errors.organisation" class="mt-1 text-xs text-red-600">{{ form.errors.organisation }}</p>
                    </div>
                    <div>
                        <label class="label">Contact Name *</label>
                        <input v-model="form.name" class="input" required />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">Phone</label>
                            <input v-model="form.phone" class="input" />
                        </div>
                        <div>
                            <label class="label">Email</label>
                            <input v-model="form.email" type="email" class="input" />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="label">Physical Address</label>
                        <input v-model="form.physical_address" class="input" />
                    </div>
                    <div>
                        <label class="label">Services Offered</label>
                        <textarea v-model="form.services_offered" class="input h-20 resize-none" />
                    </div>
                    <div>
                        <label class="label">Notes / Comments</label>
                        <input v-model="form.notes" class="input" placeholder="e.g. 24 hour service, prices, target population…" />
                    </div>
                    <div>
                        <label class="label">Chapter / Region</label>
                        <input v-model="form.chapter" class="input" placeholder="e.g. Harare" list="chapter-options" />
                        <datalist id="chapter-options">
                            <option v-for="c in chapters" :key="c" :value="c" />
                        </datalist>
                    </div>
                </form>
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100">
                    <button type="button" @click="showForm = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="save" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : (editing ? 'Update' : 'Add Provider') }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
