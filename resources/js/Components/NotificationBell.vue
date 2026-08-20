<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { InboxIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const unreadCount = computed(() => page.props.unreadNotificationsCount ?? 0);

const open = ref(false);
const notifications = ref([]);
const loading = ref(false);

async function toggle() {
    open.value = !open.value;
    if (open.value) {
        loading.value = true;
        try {
            const { data } = await axios.get('/notifications');
            notifications.value = data.notifications;
        } finally {
            loading.value = false;
        }
    }
}

async function openNotification(n) {
    if (!n.read_at) {
        await axios.post(`/notifications/${n.id}/read`);
    }
    open.value = false;
    router.get(n.data.url ?? '/dashboard');
}

async function markAllRead() {
    await axios.post('/notifications/read-all');
    notifications.value = notifications.value.map(n => ({ ...n, read_at: n.read_at ?? new Date().toISOString() }));
    router.reload({ only: ['unreadNotificationsCount'] });
}
</script>

<template>
    <div class="relative">
        <button
            @click="toggle"
            :class="['relative p-2 rounded-lg transition-colors flex-shrink-0',
                     unreadCount > 0 ? 'text-blue-400 hover:bg-blue-900/30' : 'text-gray-400 hover:bg-gray-800']"
            title="Notifications"
        >
            <InboxIcon class="h-6 w-6" />
            <span v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 min-w-[1.35rem] h-[1.35rem] px-1 rounded-full
                       bg-blue-600 text-white text-[11px] font-extrabold
                       flex items-center justify-center ring-2 ring-[#0f1117] shadow-lg">
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <div v-if="open" class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50 text-gray-900">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold">Notifications</h3>
                <button @click="markAllRead" class="text-xs text-blue-600 hover:underline">Mark all read</button>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <p v-if="loading" class="text-sm text-gray-400 text-center py-6">Loading…</p>
                <template v-else>
                    <button
                        v-for="n in notifications"
                        :key="n.id"
                        @click="openNotification(n)"
                        :class="['w-full text-left px-4 py-3 text-sm border-b border-gray-50 hover:bg-gray-50', !n.read_at ? 'bg-blue-50/50' : '']"
                    >
                        <p class="text-gray-800">{{ n.data.message }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ new Date(n.created_at).toLocaleString() }}</p>
                    </button>
                    <p v-if="!notifications.length" class="text-sm text-gray-400 text-center py-6">No notifications yet.</p>
                </template>
            </div>
        </div>
    </div>
</template>
