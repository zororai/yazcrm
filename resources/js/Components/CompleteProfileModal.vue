<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { CameraIcon, SparklesIcon, PhoneIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ user: Object });
const emit = defineEmits(['dismiss']);

const avatarPreview = ref(props.user.avatar ? `/storage/${props.user.avatar}` : null);
const form = useForm({
    phone:  props.user.phone ?? '',
    bio:    props.user.bio   ?? '',
    avatar: null,
});

const fields = [
    { key: 'avatar', done: () => !!avatarPreview.value },
    { key: 'phone', done: () => !!form.phone },
    { key: 'bio', done: () => !!form.bio },
];

function onAvatarChange(e) {
    const file = e.target.files[0];
    form.avatar = file;
    if (file) avatarPreview.value = URL.createObjectURL(file);
}

function submit() {
    form.post('/profile', {
        preserveScroll: true,
        onSuccess: () => emit('dismiss'),
    });
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
    >
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
            <Transition
                appear
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 scale-95 translate-y-2"
                enter-to-class="opacity-100 scale-100 translate-y-0"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                    <!-- Gradient header -->
                    <div class="relative px-6 pt-7 pb-8 bg-gradient-to-br from-fuchsia-500 via-brand-600 to-indigo-600 overflow-hidden">
                        <div class="absolute -top-8 -right-8 h-32 w-32 rounded-full bg-white/10"></div>
                        <div class="absolute -bottom-10 -left-6 h-28 w-28 rounded-full bg-white/10"></div>
                        <div class="relative flex items-center gap-2 text-white/90 text-xs font-semibold uppercase tracking-wider mb-2">
                            <SparklesIcon class="h-4 w-4" /> Almost there
                        </div>
                        <h3 class="relative font-bold text-white text-xl leading-snug">Complete Your Profile</h3>
                        <p class="relative text-sm text-white/80 mt-1">Add a photo, phone number, and a short bio so your team can recognize you.</p>

                        <!-- progress dots -->
                        <div class="relative flex gap-1.5 mt-4">
                            <span v-for="(f, i) in fields" :key="i"
                                :class="['h-1.5 flex-1 rounded-full transition-colors', f.done() ? 'bg-white' : 'bg-white/30']" />
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="px-6 pt-5 pb-6 space-y-4 -mt-1">
                        <!-- Avatar -->
                        <div class="flex items-center gap-5">
                            <div class="relative group flex-shrink-0">
                                <img :src="avatarPreview ?? '/images/default-avatar.png'"
                                    class="h-28 w-28 rounded-full object-cover ring-4 ring-brand-50 shadow-sm" />
                                <label class="absolute -bottom-1 -right-1 flex items-center justify-center h-10 w-10 rounded-full bg-brand-600 text-white shadow-md cursor-pointer hover:bg-brand-700 transition-colors ring-2 ring-white">
                                    <CameraIcon class="h-5 w-5" />
                                    <input type="file" accept="image/*" class="hidden" @change="onAvatarChange" />
                                </label>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800">{{ user.name }}</p>
                                <p class="text-xs text-gray-400">Click the camera icon to add a photo</p>
                                <p v-if="form.errors.avatar" class="mt-1 text-xs text-red-600">{{ form.errors.avatar }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="label flex items-center gap-1.5"><PhoneIcon class="h-3.5 w-3.5 text-gray-400" /> Phone</label>
                            <input v-model="form.phone" class="input" placeholder="e.g. +263 77 123 4567" />
                            <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="label flex items-center gap-1.5"><PencilSquareIcon class="h-3.5 w-3.5 text-gray-400" /> Bio</label>
                            <textarea v-model="form.bio" class="input h-20 resize-none" placeholder="A short line about yourself…"></textarea>
                            <p v-if="form.errors.bio" class="mt-1 text-xs text-red-600">{{ form.errors.bio }}</p>
                        </div>

                        <div class="flex gap-2 justify-end pt-1">
                            <button type="button" @click="emit('dismiss')" class="btn-secondary">Remind me later</button>
                            <button type="submit"
                                class="btn bg-gradient-to-r from-brand-600 to-indigo-600 text-white hover:from-brand-700 hover:to-indigo-700 focus:ring-brand-500 shadow-sm"
                                :disabled="form.processing">
                                {{ form.processing ? 'Saving…' : 'Save Profile' }}
                            </button>
                        </div>
                    </form>
                </div>
            </Transition>
        </div>
    </Transition>
</template>
