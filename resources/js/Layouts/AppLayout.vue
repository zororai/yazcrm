<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    HomeIcon, PhoneIcon, TicketIcon, ChartBarIcon,
    QueueListIcon, SignalIcon, UserGroupIcon, ArrowRightOnRectangleIcon,
    Bars3Icon, XMarkIcon, BellIcon, FlagIcon, TagIcon, Cog6ToothIcon, ChevronDownIcon, FolderOpenIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';
import CallTicketModal from '@/Components/CallTicketModal.vue';
import IncomingCallPopup from '@/Components/IncomingCallPopup.vue';

const page  = usePage();
const user  = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);
const isAdmin = computed(() => user.value?.role === 'admin');

const sidebarOpen     = ref(false);
const pendingCall     = ref(null);
const urgentCount     = ref(0);
const urgentAlert     = ref(false); // banner shown when count increases
let   prevUrgentCount = 0;

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

async function pollUrgentCount() {
    try {
        const { data } = await axios.get('/api/urgent-cases/open-count');
        const n = data.count ?? 0;
        if (n > prevUrgentCount && prevUrgentCount !== null) urgentAlert.value = true;
        prevUrgentCount = n;
        urgentCount.value = n;
    } catch { /* ignore */ }
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

    // Poll urgent cases count every 30 seconds
    pollUrgentCount();
    setInterval(pollUrgentCount, 30000);
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
    { name: 'Urgent',     href: '/urgent-cases', icon: ExclamationTriangleIcon, badge: urgentCount },
    ...(isAdmin.value ? [
        { name: 'Extensions', href: '/extensions',   icon: SignalIcon },
        { name: 'Analytics',  href: '/analytics',    icon: ChartBarIcon },
        { name: 'Targets',    href: '/call-targets',                         icon: FlagIcon },
        { name: 'By Project', href: '/distress-domains/section/project',    icon: FolderOpenIcon },
        { name: 'Domains',    href: '/distress-domains',                     icon: TagIcon },
        { name: 'Users',      href: '/users',             icon: UserGroupIcon },
        { name: 'Yeastar',    href: '/yeastar-settings',  icon: Cog6ToothIcon },
    ] : []),
]);

function isActive(href) {
    if (!page.url.startsWith(href)) return false;
    // If a more-specific nav item also matches the current URL, don't highlight this one
    const moreSpecific = navigation.value.some(
        item => item.href !== href && item.href.startsWith(href) && page.url.startsWith(item.href)
    );
    return !moreSpecific;
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen flex bg-[#0d1117]">
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
            <div class="flex h-16 items-center gap-3 px-5 border-b border-gray-700">
                <!-- Youth Advocates mark -->
                <svg width="38" height="34" viewBox="0 0 130 108" xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0">
                    <!-- Left arm: orange -->
                    <rect x="12" y="0" width="28" height="82" rx="14"
                          fill="#e8512a"
                          transform="rotate(34 26 66)"/>
                    <!-- Right arm: purple -->
                    <rect x="90" y="0" width="28" height="82" rx="14"
                          fill="#6835a2"
                          transform="rotate(-34 104 66)"/>
                    <!-- Teardrop head (white so it shows on dark sidebar) -->
                    <ellipse cx="65" cy="13" rx="11" ry="14" fill="#ffffff"/>
                </svg>
                <div class="leading-tight min-w-0">
                    <div class="text-white font-bold text-sm leading-none">youth</div>
                    <div class="text-gray-400 text-[11px] leading-none mt-0.5 tracking-wide">advocates</div>
                </div>
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
                            : item.badge?.value > 0
                                ? 'text-red-300 hover:bg-gray-800 hover:text-red-200'
                                : 'text-gray-300 hover:bg-gray-800 hover:text-white',
                    ]"
                    @click="sidebarOpen = false"
                >
                    <component :is="item.icon" class="h-5 w-5 flex-shrink-0" />
                    <span class="flex-1">{{ item.name }}</span>
                    <span v-if="item.badge?.value > 0"
                        class="inline-flex items-center justify-center h-5 min-w-[1.25rem] px-1 rounded-full text-xs font-bold bg-red-500 text-white">
                        {{ item.badge.value }}
                    </span>
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
            <header class="h-16 bg-[#0f1117] border-b border-gray-800 flex items-center gap-4 px-4 lg:px-6 flex-shrink-0">
                <button
                    class="p-2 rounded-lg text-gray-400 hover:bg-gray-800 lg:hidden"
                    @click="sidebarOpen = true"
                >
                    <Bars3Icon class="h-5 w-5" />
                </button>
                <div class="flex-1 min-w-0">
                    <h1 class="text-base font-semibold text-white leading-tight truncate">
                        <slot name="title" />
                    </h1>
                    <p class="text-xs text-gray-400 leading-tight truncate hidden sm:block">
                        <slot name="subtitle" />
                    </p>
                </div>
                <slot name="header-actions" />
                <!-- Notification bell -->
                <button class="relative p-2 rounded-lg text-gray-400 hover:bg-gray-800 transition-colors flex-shrink-0">
                    <BellIcon class="h-5 w-5" />
                    <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-red-500 ring-2 ring-[#0f1117]"></span>
                </button>
                <!-- User profile -->
                <div class="flex items-center gap-3 pl-3 border-l border-gray-700 flex-shrink-0">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-white text-sm font-semibold flex-shrink-0">
                        {{ user?.name?.charAt(0)?.toUpperCase() }}
                    </div>
                    <div class="hidden md:block">
                        <p class="text-sm font-medium text-white leading-tight">{{ user?.name }}</p>
                        <p class="text-xs text-gray-400 capitalize leading-tight">{{ user?.role }}</p>
                    </div>
                    <button
                        @click="logout"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-gray-700 transition-colors"
                        title="Logout"
                    >
                        <ArrowRightOnRectangleIcon class="h-4 w-4" />
                    </button>
                </div>
            </header>

            <!-- Urgent cases alert banner -->
            <div v-if="urgentAlert" class="px-4 lg:px-6 pt-3">
                <div class="flex items-center justify-between gap-3 p-3 rounded-lg bg-red-500/15 border border-red-500/30 text-red-300 text-sm">
                    <span class="flex items-center gap-2">
                        <ExclamationTriangleIcon class="h-4 w-4 text-red-400 flex-shrink-0" />
                        <strong class="text-red-200">New urgent case logged!</strong>
                        There {{ urgentCount > 1 ? `are ${urgentCount} open urgent cases` : 'is 1 open urgent case' }} requiring attention.
                    </span>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <Link href="/urgent-cases" class="text-xs font-semibold text-red-200 hover:text-white underline"
                            @click="urgentAlert = false">
                            View Cases
                        </Link>
                        <button @click="urgentAlert = false" class="text-red-400 hover:text-red-200">
                            <XMarkIcon class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Flash messages -->
            <div v-if="flash.success || flash.error" class="px-4 lg:px-6 pt-4">
                <div
                    v-if="flash.success"
                    class="flex items-center gap-2 p-3 rounded-lg bg-green-500/10 text-green-400 text-sm border border-green-500/20"
                >
                    {{ flash.success }}
                </div>
                <div
                    v-if="flash.error"
                    class="flex items-center gap-2 p-3 rounded-lg bg-red-500/10 text-red-400 text-sm border border-red-500/20"
                >
                    {{ flash.error }}
                </div>
            </div>

            <!-- Page content -->
            <main class="flex-1 p-4 lg:p-6 overflow-auto bg-[#0d1117]">
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
