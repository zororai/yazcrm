<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    HomeIcon, PhoneIcon, TicketIcon, ChartBarIcon,
    QueueListIcon, SignalIcon, UserGroupIcon, ArrowRightOnRectangleIcon,
    Bars3Icon, XMarkIcon, BellIcon, FlagIcon, TagIcon, Cog6ToothIcon, ChevronDownIcon, FolderOpenIcon,
    ExclamationTriangleIcon, ChatBubbleLeftRightIcon, ShieldCheckIcon, TableCellsIcon,
} from '@heroicons/vue/24/outline';
import CallTicketModal from '@/Components/CallTicketModal.vue';
import IncomingCallPopup from '@/Components/IncomingCallPopup.vue';

const page  = usePage();
const user  = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);
const isAdmin = computed(() => user.value?.role === 'admin');

// nav_permissions: null on admins (full access), array of keys on agents/supervisors
const can = (key) => isAdmin.value || (user.value?.nav_permissions ?? []).includes(key);

const sidebarOpen     = ref(false);
const pendingCall     = ref(null);
const urgentCount     = ref(0);
const urgentAlert     = ref(false); // banner shown when count increases
const urgentToast     = ref(null);  // { subject, contact_number, id } for popup toast
let   prevUrgentCount = -1;         // -1 = first poll not done yet — no alert on initial load
let   prevLatestId    = null;
let   toastTimer      = null;

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

function requestNotifyPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

function fireDesktopNotification(title, body) {
    if ('Notification' in window && Notification.permission === 'granted') {
        const n = new Notification(title, {
            body,
            icon: '/favicon.ico',
            tag: 'urgent-case',
            requireInteraction: true,
        });
        n.onclick = () => { window.focus(); router.visit('/urgent-cases'); n.close(); };
    }
}

async function pollUrgentCount() {
    try {
        const { data } = await axios.get('/api/urgent-cases/open-count');
        const n      = data.count  ?? 0;
        const latest = data.latest ?? null;

        const isFirstPoll = prevUrgentCount === -1;
        const isNew = !isFirstPoll && latest && latest.id !== prevLatestId && prevLatestId !== null;

        if (!isFirstPoll && (isNew || n > prevUrgentCount)) {
            urgentAlert.value = true;

            // Show toast popup with case details
            if (latest) {
                urgentToast.value = latest;
                clearTimeout(toastTimer);
                toastTimer = setTimeout(() => { urgentToast.value = null; }, 10000);

                // Browser desktop notification
                fireDesktopNotification(
                    '🚨 New Urgent Case',
                    latest.subject + (latest.contact_number ? ' · ' + latest.contact_number : '')
                );
            }
        }

        prevUrgentCount = n;
        if (latest) prevLatestId = latest.id;
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

    // Request browser notification permission
    requestNotifyPermission();

    // Poll urgent cases count every 10 seconds
    pollUrgentCount();
    setInterval(pollUrgentCount, 10000);
});

onUnmounted(() => {
    if (user.value) window.Echo?.leave(`agent.${user.value.id}`);
    clearInterval(pollTimer);
});

const navigation = computed(() => [
    { name: 'Dashboard',  href: '/dashboard',   icon: HomeIcon },
    { name: 'Calls',      href: '/calls',        icon: PhoneIcon },
    { name: 'Callbacks',  href: '/callbacks',    icon: QueueListIcon },
    { name: 'Tickets',    href: '/tickets',      icon: TicketIcon },
    { name: 'Urgent',     href: '/urgent-cases', icon: ExclamationTriangleIcon, badge: urgentCount },
    ...(can('extensions')   ? [{ name: 'Extensions',  href: '/extensions',                       icon: SignalIcon }] : []),
    ...(can('analytics')    ? [{ name: 'Analytics',   href: '/analytics',                        icon: ChartBarIcon }] : []),
    ...(can('targets')      ? [{ name: 'Targets',     href: '/call-targets',                     icon: FlagIcon }] : []),
    ...(can('by_project')   ? [{ name: 'By Project',  href: '/distress-domains/section/project', icon: FolderOpenIcon }] : []),
    ...(can('domains')      ? [{ name: 'Domains',     href: '/distress-domains',                 icon: TagIcon }] : []),
    ...(can('bot_contacts') ? [{ name: 'Bot Contacts',href: '/uchat-contacts',                   icon: ChatBubbleLeftRightIcon }] : []),
    ...(isAdmin.value       ? [{ name: 'SBC Signups', href: '/sbc',                              icon: TableCellsIcon }] : []),
    ...(can('yalep') ? [{ name: 'YALeP Students', href: '/sbc?sheet=Certificates%20To%20Process', icon: TableCellsIcon }] : []),
    ...(isAdmin.value       ? [{ name: 'Roles',       href: '/roles',                            icon: ShieldCheckIcon }] : []),
    ...(can('users')        ? [{ name: 'Users',       href: '/users',                            icon: UserGroupIcon }] : []),
    ...(can('yeastar')      ? [{ name: 'Yeastar',     href: '/yeastar-settings',                 icon: Cog6ToothIcon }] : []),
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
                <Link href="/urgent-cases"
                    :class="['relative p-2 rounded-lg transition-colors flex-shrink-0',
                             urgentCount > 0 ? 'text-red-400 hover:bg-red-900/30 animate-flicker' : 'text-gray-400 hover:bg-gray-800']"
                    :title="urgentCount > 0 ? `${urgentCount} open urgent case${urgentCount > 1 ? 's' : ''}` : 'No urgent cases'">
                    <BellIcon class="h-6 w-6" />
                    <!-- Red count badge -->
                    <span v-if="urgentCount > 0"
                        class="absolute -top-1 -right-1 min-w-[1.35rem] h-[1.35rem] px-1 rounded-full
                               bg-red-600 text-white text-[11px] font-extrabold
                               flex items-center justify-center
                               ring-2 ring-[#0f1117] shadow-lg">
                        {{ urgentCount > 99 ? '99+' : urgentCount }}
                    </span>
                    <span v-else
                        class="absolute top-2 right-2 h-2 w-2 rounded-full bg-gray-600 ring-2 ring-[#0f1117]">
                    </span>
                </Link>
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

    <!-- ── Urgent case toast popup ── -->
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-x-full opacity-0"
        enter-to-class="translate-x-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-x-0 opacity-100"
        leave-to-class="translate-x-full opacity-0"
    >
        <div v-if="urgentToast"
            class="fixed top-5 right-5 z-[999] w-80 bg-red-600 text-white rounded-xl shadow-2xl overflow-hidden">
            <div class="flex items-start gap-3 p-4">
                <ExclamationTriangleIcon class="h-6 w-6 flex-shrink-0 mt-0.5 animate-pulse" />
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wider opacity-80 mb-0.5">🚨 New Urgent Case</p>
                    <p class="font-semibold text-sm leading-snug truncate">{{ urgentToast.subject }}</p>
                    <p v-if="urgentToast.contact_number" class="text-xs opacity-75 mt-0.5">
                        📞 {{ urgentToast.contact_number }}
                    </p>
                </div>
                <button @click="urgentToast = null; clearTimeout(toastTimer)"
                    class="flex-shrink-0 opacity-70 hover:opacity-100 transition-opacity mt-0.5">
                    <XMarkIcon class="h-4 w-4" />
                </button>
            </div>
            <Link href="/urgent-cases" @click="urgentToast = null"
                class="block bg-red-700 hover:bg-red-800 text-center text-xs font-semibold py-2 transition-colors">
                View Urgent Cases →
            </Link>
            <!-- Progress bar auto-dismiss (10 s) -->
            <div class="h-1 bg-red-800">
                <div class="h-1 bg-white/50 animate-[shrink_10s_linear_forwards]"></div>
            </div>
        </div>
    </Transition>

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
