<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { MagnifyingGlassIcon, PhoneIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';
import { debounce } from 'lodash-es';

const props = defineProps({
    subscribers: Array,
    meta:        Object,
    filters:     Object,
    error:       String,
});

const search = ref(props.filters.search ?? '');

const doSearch = debounce(() => {
    router.get('/uchat-contacts', { search: search.value || undefined, page: 1 }, {
        preserveState: true,
        replace: true,
    });
}, 350);

watch(search, doSearch);

const channelLabel = {
    whatsapp_cloud: 'WhatsApp',
    whatsapp:       'WhatsApp',
    facebook:       'Facebook',
    instagram:      'Instagram',
    telegram:       'Telegram',
    tiktok:         'TikTok',
    web:            'WebChat',
    slack:          'Slack',
    wechat:         'WeChat',
};

const channelColor = {
    whatsapp_cloud: 'bg-green-100 text-green-700',
    whatsapp:       'bg-green-100 text-green-700',
    facebook:       'bg-blue-100 text-blue-700',
    instagram:      'bg-pink-100 text-pink-700',
    telegram:       'bg-sky-100 text-sky-700',
    web:            'bg-gray-100 text-gray-600',
};

function fmt(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString([], { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function goPage(p) {
    router.get('/uchat-contacts', { search: search.value || undefined, page: p }, { preserveState: true });
}

function initials(name) {
    if (!name) return '?';
    return name.trim().split(/\s+/).map(w => w[0]?.toUpperCase() ?? '').slice(0, 2).join('');
}

const avatarColors = ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#3b82f6','#ec4899'];
function avatarColor(name) {
    let h = 0;
    for (const c of (name ?? '')) h = (h * 31 + c.charCodeAt(0)) & 0xffff;
    return avatarColors[h % avatarColors.length];
}
</script>

<template>
    <AppLayout>
        <template #title>
            <span class="flex items-center gap-2">
                <ChatBubbleLeftRightIcon class="h-5 w-5 text-brand-400" />
                Bot Contacts
            </span>
        </template>
        <template #subtitle>People interacting with the uChat bot</template>

        <!-- Search + total -->
        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-56">
                <label class="label">Search by name or phone</label>
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                    <input v-model="search" class="input pl-9" placeholder="e.g. Rose or +2637…" />
                </div>
            </div>
            <div class="text-sm text-gray-500 pb-1">
                {{ meta.total.toLocaleString() }} total subscribers
            </div>
        </div>

        <!-- Error -->
        <div v-if="error" class="card mb-4 bg-red-50 border-red-200 text-red-700 text-sm">
            ⚠ {{ error }}
        </div>

        <!-- Table -->
        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Name</th>
                        <th class="table-th">Phone</th>
                        <th class="table-th">Channel</th>
                        <th class="table-th">Subscribed</th>
                        <th class="table-th">Last Active</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!subscribers.length">
                        <td colspan="5" class="py-12 text-center text-sm text-gray-400">No subscribers found.</td>
                    </tr>
                    <tr v-for="s in subscribers" :key="s.user_ns" class="hover:bg-gray-50">
                        <!-- Name + avatar -->
                        <td class="table-td">
                            <div class="flex items-center gap-2">
                                <div class="h-8 w-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                                    :style="{ background: avatarColor(s.name) }">
                                    {{ initials(s.name) }}
                                </div>
                                <span class="font-medium text-gray-900 text-sm">{{ s.name || '—' }}</span>
                            </div>
                        </td>
                        <!-- Phone -->
                        <td class="table-td">
                            <span v-if="s.phone" class="flex items-center gap-1 text-sm text-gray-600">
                                <PhoneIcon class="h-3.5 w-3.5 text-gray-400 flex-shrink-0" />
                                {{ s.phone }}
                            </span>
                            <span v-else class="text-gray-400 text-xs">—</span>
                        </td>
                        <!-- Channel -->
                        <td class="table-td">
                            <span :class="['badge', channelColor[s.channel] ?? 'bg-gray-100 text-gray-600']">
                                {{ channelLabel[s.channel] ?? s.channel }}
                            </span>
                        </td>
                        <!-- Subscribed -->
                        <td class="table-td text-xs text-gray-500 whitespace-nowrap">{{ fmt(s.subscribed) }}</td>
                        <!-- Last active -->
                        <td class="table-td text-xs text-gray-500 whitespace-nowrap">{{ fmt(s.last_interaction) }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="meta.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">
                    Page {{ meta.current_page }} of {{ meta.last_page }}
                    &nbsp;·&nbsp; {{ meta.total.toLocaleString() }} total
                </p>
                <div class="flex gap-1">
                    <button
                        @click="goPage(meta.current_page - 1)"
                        :disabled="meta.current_page <= 1"
                        class="px-3 py-1 rounded text-xs text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:pointer-events-none"
                    >← Prev</button>
                    <button
                        @click="goPage(meta.current_page + 1)"
                        :disabled="meta.current_page >= meta.last_page"
                        class="px-3 py-1 rounded text-xs text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:pointer-events-none"
                    >Next →</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
