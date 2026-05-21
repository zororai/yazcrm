<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    HomeIcon, PhoneIcon, TicketIcon, ChartBarIcon,
    QueueListIcon, SignalIcon, UserGroupIcon, ArrowRightOnRectangleIcon,
    Bars3Icon, XMarkIcon, BellIcon, FlagIcon, TagIcon, Cog6ToothIcon,
} from '@heroicons/vue/24/outline';
import CallTicketModal from '@/Components/CallTicketModal.vue';
import IncomingCallPopup from '@/Components/IncomingCallPopup.vue';

const page  = usePage();
const user  = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);
const isAdmin = computed(() => user.value?.role === 'admin');

const sidebarOpen = ref(false);
const pendingCall = ref(null);

// ── Incoming call popup ──────────────────────────────────────────────────────
const activeCalls   = ref([]);
const dismissedIds  = ref(new Set());
let   pollTimer     = null;

const visibleCalls = computed(() =>
    activeCalls.value.filter(c => {
        const key = c.id ?? c.caller;
        return !dismissedIds.value.has(key);
    })
);

function dismissCall(index) {
    const call = visibleCalls.value[index];
    if (call) dismissedIds.value.add(call.id ?? call.caller);
}

async function pollActiveCalls() {
    try {
        const { data } = await axios.get('/api/calls/active');
        const incoming = (data.calls ?? []);
        // Auto-clear dismissed set when a call disappears
        const currentKeys = new Set(incoming.map(c => c.id ?? c.caller));
        dismissedIds.value.forEach(k => {
            if (!currentKeys.has(k)) dismissedIds.value.delete(k);
        });
        activeCalls.value = incoming;
    } catch {
        // silently ignore network errors during polling
    }
}

onMounted(() => {
    // Echo-based ticket modal (existing)
    if (window.Echo && user.value) {
        window.Echo.private(`agent.${user.value.id}`)
            .listen('.call-ended', (data) => {
                pendingCall.value = data;
            });
    }

    // Poll for active inbound calls every 8 seconds
    pollActiveCalls();
    pollTimer = setInterval(pollActiveCalls, 8000);
});

onUnmounted(() => {
    if (user.value) window.Echo?.leave(`agent.${user.value.id}`);
    clearInterval(pollTimer);
});

const navigation = computed(() => [
    { name: 'Dashboard',  href: '/dashboard', icon: HomeIcon },
    { name: 'Calls',      href: '/calls',      icon: PhoneIcon },
    { name: 'Callbacks',  href: '/callbacks',  icon: QueueListIcon },
    { name: 'Tickets',    href: '/tickets',    icon: TicketIcon },
    ...(isAdmin.value ? [
        { name: 'Extensions', href: '/extensions',   icon: SignalIcon },
        { name: 'Analytics',  href: '/analytics',    icon: ChartBarIcon },
        { name: 'Targets',    href: '/call-targets',    icon: FlagIcon },
        { name: 'Domains',    href: '/distress-domains',  icon: TagIcon },
        { name: 'Users',      href: '/users',             icon: UserGroupIcon },
        { name: 'Yeastar',    href: '/yeastar-settings',  icon: Cog6ToothIcon },
    ] : []),
]);

function isActive(href) {
    return page.url.startsWith(href);
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen flex bg-gray-100">
        <!-- Sidebar backdrop (mobile) -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-20 bg-black/50 lg:hidden"
            @click="sidebarOpen = false"
        />

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-30 w-64 flex flex-col bg-gray-900 transition-transform duration-200',
                'lg:static lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <!-- Logo -->
            <div class="flex h-16 items-center gap-3 px-6 border-b border-gray-700">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600">
                    <PhoneIcon class="h-5 w-5 text-white" />
                </div>
                <span class="text-white font-semibold text-lg">CRM PBX</span>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                <Link
                    v-for="item in navigation"
                    :key="item.name"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                        isActive(item.href)
                            ? 'bg-brand-600 text-white'
                            : 'text-gray-300 hover:bg-gray-800 hover:text-white',
                    ]"
                    @click="sidebarOpen = false"
                >
                    <component :is="item.icon" class="h-5 w-5 flex-shrink-0" />
                    {{ item.name }}
                </Link>
            </nav>

            <!-- User -->
            <div class="border-t border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-white text-sm font-semibold flex-shrink-0">
                        {{ user?.name?.charAt(0)?.toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ user?.name }}</p>
                        <p class="text-xs text-gray-400 truncate capitalize">{{ user?.role }}</p>
                    </div>
                    <button
                        @click="logout"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition-colors"
                        title="Logout"
                    >
                        <ArrowRightOnRectangleIcon class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top bar -->
            <header class="h-16 bg-white border-b border-gray-200 flex items-center gap-4 px-4 lg:px-6">
                <button
                    class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 lg:hidden"
                    @click="sidebarOpen = true"
                >
                    <Bars3Icon class="h-5 w-5" />
                </button>
                <h1 class="text-lg font-semibold text-gray-900 flex-1">
                    <slot name="title" />
                </h1>
                <slot name="header-actions" />
            </header>

            <!-- Flash messages -->
            <div v-if="flash.success || flash.error" class="px-4 lg:px-6 pt-4">
                <div
                    v-if="flash.success"
                    class="flex items-center gap-2 p-3 rounded-lg bg-green-50 text-green-800 text-sm border border-green-200"
                >
                    {{ flash.success }}
                </div>
                <div
                    v-if="flash.error"
                    class="flex items-center gap-2 p-3 rounded-lg bg-red-50 text-red-800 text-sm border border-red-200"
                >
                    {{ flash.error }}
                </div>
            </div>

            <!-- Page content -->
            <main class="flex-1 p-4 lg:p-6 overflow-auto">
                <slot />
            </main>
        </div>
    </div>

    <!-- Auto ticket modal: fires when an answered call ≥ 30 s ends -->
    <CallTicketModal
        v-if="pendingCall"
        :call="pendingCall"
        @close="pendingCall = null"
    />

    <!-- Incoming call popup (polled every 8 s) -->
    <IncomingCallPopup
        :calls="visibleCalls"
        @dismiss="dismissCall"
    />
</template>
