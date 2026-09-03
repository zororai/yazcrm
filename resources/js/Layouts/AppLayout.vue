<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    HomeIcon, PhoneIcon, TicketIcon, ChartBarIcon,
    QueueListIcon, SignalIcon, UserGroupIcon, ArrowRightOnRectangleIcon,
    Bars3Icon, XMarkIcon, BellIcon, FlagIcon, TagIcon, Cog6ToothIcon, ChevronDownIcon, FolderOpenIcon,
    ExclamationTriangleIcon, ChatBubbleLeftRightIcon, ShieldCheckIcon, TableCellsIcon,
    ServerStackIcon, ShieldExclamationIcon, PhoneArrowUpRightIcon, MicrophoneIcon, BookOpenIcon,
    ClipboardDocumentCheckIcon, ClipboardDocumentListIcon, DocumentTextIcon, TruckIcon,
    SunIcon, MoonIcon, CalendarDaysIcon,
} from '@heroicons/vue/24/outline';
import CallTicketModal from '@/Components/CallTicketModal.vue';
import IncomingCallPopup from '@/Components/IncomingCallPopup.vue';
import Dialer from '@/Components/Dialer.vue';
import ChatAssistant from '@/Components/ChatAssistant.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import CompleteProfileModal from '@/Components/CompleteProfileModal.vue';
import PendingTicketQueue from '@/Components/PendingTicketQueue.vue';

const page  = usePage();
const user  = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);
const isAdmin = computed(() => user.value?.role === 'admin');

// Prompt once per session to complete phone/bio/avatar — dismissible via
// "Remind me later", reappears next login if still incomplete.
const showCompleteProfile = ref(user.value && user.value.profile_complete === false);

// nav_permissions: null on admins (full access), array of keys on agents/supervisors
const can = (key) => isAdmin.value || (user.value?.nav_permissions ?? []).includes(key);

// ── Light / Dark mode ────────────────────────────────────────────────────────
const theme = ref(localStorage.getItem('theme') ?? 'dark');
function toggleTheme() {
    theme.value = theme.value === 'dark' ? 'light' : 'dark';
    localStorage.setItem('theme', theme.value);
}
const isLight = computed(() => theme.value === 'light');

const sidebarOpen     = ref(false);
const dialerOpen      = ref(false);
const pendingCall     = ref(null);
const pendingTicketQueue = ref([]); // calls awaiting a ticket, shown as floating queue buttons
const ticketPromptedKeys = ref(new Set()); // calls already queued for a ticket, so polling doesn't re-add them

function openTicketFromQueue(call) {
    pendingTicketQueue.value = pendingTicketQueue.value.filter(c => c.call_id !== call.call_id);
    pendingCall.value = call;
}

// Closing the modal without submitting a ticket doesn't discard the call —
// it goes back onto the floating queue so it can't be lost without a ticket.
function minimizePendingCall() {
    if (pendingCall.value && ! pendingTicketQueue.value.some(c => c.call_id === pendingCall.value.call_id)) {
        pendingTicketQueue.value.push(pendingCall.value);
    }
    pendingCall.value = null;
}

// Load calls that ended >=15s ago and still have no ticket, from the server
// — not just from live polling — so the "log a ticket" queue survives a
// page refresh instead of being wiped along with in-memory state.
async function loadNeedingTicket() {
    try {
        const { data } = await axios.get('/api/calls/needing-ticket');
        for (const call of (data.calls ?? [])) {
            ticketPromptedKeys.value.add(call.call_id);
            if (! pendingTicketQueue.value.some(c => c.call_id === call.call_id)) {
                pendingTicketQueue.value.push({
                    call_id:   call.call_id, // Yeastar's call_id string — only used as a dedup/queue key
                    db_call_id: call.id ?? null, // the real calls.id row — what actually links the ticket
                    caller:    call.caller,
                    callee:    call.callee,
                    duration:  call.duration,
                    direction: call.direction,
                    client:    call.client ?? null,
                    recording_id: call.recording_id ?? null,
                });
            }
        }
    } catch { /* ignore */ }
}
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
let   needingTicketTimer = null;

// A stable per-call key. Falling back to just the caller's number (as this
// used to) meant dismissing one call silently suppressed every future call
// from that same number too — once dismissed, it never popped up again.
// started_at makes distinct calls from the same number resolve to distinct keys.
function callKey(c) {
    return c.id ?? c.call_id ?? `${c.caller ?? c.src ?? 'unknown'}-${c.started_at ?? ''}`;
}

const visibleCalls = computed(() =>
    activeCalls.value.filter(c => !dismissedIds.value.has(callKey(c)))
);

function dismissCall(index) {
    const call = visibleCalls.value[index];
    if (call) dismissedIds.value.add(callKey(call));
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
        const currentKeys = new Set(incoming.map(callKey));
        dismissedIds.value.forEach(k => {
            if (!currentKeys.has(k)) dismissedIds.value.delete(k);
        });
        activeCalls.value = incoming;

        // Drive the 15s ticket-queue prompt off this same reliable polling
        // data, not just the call-ended webhook (which isn't always firing —
        // see handleCallEnd). Any call still tracked 15s after it started
        // gets queued for a ticket, once.
        const now = Date.now();
        for (const call of incoming) {
            const key = callKey(call);
            if (ticketPromptedKeys.value.has(key)) continue;
            const startedMs = call.started_at ? new Date(call.started_at).getTime() : null;
            if (!startedMs || (now - startedMs) < 15000) continue;

            ticketPromptedKeys.value.add(key);
            if (! pendingTicketQueue.value.some(c => c.call_id === key)) {
                pendingTicketQueue.value.push({
                    call_id:   key,
                    db_call_id: call.id ?? null, // only present on the DB-fallback path, not live Yeastar API items
                    caller:    call.caller ?? call.src ?? 'Unknown',
                    callee:    call.callee ?? call.dst ?? call.extension_number ?? '',
                    duration:  Math.round((now - startedMs) / 1000),
                    direction: call.direction ?? 'inbound',
                    client:    call.client ?? null,
                    recording_id: call.recording_id ?? null,
                });
            }
        }
    } catch {
        // silently ignore network errors during polling
    }
}

onMounted(() => {
    // Echo-based ticket modal (existing)
    if (window.Echo && user.value) {
        window.Echo.private(`agent.${user.value.id}`)
            .listen('.call-ended', (data) => {
                // CallEndedEvent's `call_id` is actually the real calls.id
                // primary key (not the Yeastar string) — carry it through
                // as db_call_id too, which is what the ticket link uses.
                if (! pendingTicketQueue.value.some(c => c.call_id === data.call_id)) {
                    pendingTicketQueue.value.push({ ...data, db_call_id: data.call_id });
                }
            });
    }

    // Poll for active inbound calls every 8 seconds
    pollActiveCalls();
    pollTimer = setInterval(pollActiveCalls, 8000);

    // Re-seed the ticket queue from the server (survives refresh) and keep
    // catching any call that ended without the client noticing.
    loadNeedingTicket();
    needingTicketTimer = setInterval(loadNeedingTicket, 30000);

    // Request browser notification permission
    requestNotifyPermission();

    // Poll urgent cases count every 10 seconds
    pollUrgentCount();
    setInterval(pollUrgentCount, 10000);
});

onUnmounted(() => {
    if (user.value) window.Echo?.leave(`agent.${user.value.id}`);
    clearInterval(pollTimer);
    clearInterval(needingTicketTimer);
});

const navigation = computed(() => [
    ...(can('dashboard')    ? [{ name: 'Dashboard',  href: '/dashboard',   icon: HomeIcon }] : []),
    ...(can('helpline_dashboard') ? [{ name: 'Call Activity', href: '/screen?section=calls', icon: ChartBarIcon }] : []),
    { name: 'My Work', href: '/my-work', icon: ClipboardDocumentCheckIcon },
    ...(can('dialer')       ? [{ name: 'Dialer',     href: '/dialer',      icon: PhoneArrowUpRightIcon }] : []),
    ...(can('calls')        ? [{ name: 'Calls',      href: '/calls',       icon: PhoneIcon }] : []),
    ...(can('recordings')   ? [{ name: 'Recordings', href: '/recordings',  icon: MicrophoneIcon }] : []),
    ...(can('callbacks')    ? [{ name: 'Callbacks',  href: '/callbacks',   icon: QueueListIcon }] : []),
    ...(can('tickets')      ? [{ name: 'Tickets',    href: '/tickets',     icon: TicketIcon }] : []),
    ...(can('urgent')       ? [{ name: 'Urgent',     href: '/urgent-cases', icon: ExclamationTriangleIcon, badge: urgentCount }] : []),
    ...(can('directory')    ? [{ name: 'Directory',  href: '/service-directory', icon: BookOpenIcon }] : []),
    ...(can('appraisals')   ? [{ name: 'Appraisals', href: '/appraisals',  icon: ClipboardDocumentCheckIcon }] : []),
    ...(can('appraisal_reviews') ? [{ name: 'Appraisal Reviews', href: '/appraisal-reviews', icon: ClipboardDocumentCheckIcon }] : []),
    ...(can('appraisal_archive') ? [{ name: 'Appraisal Archive', href: '/appraisal-archive', icon: DocumentTextIcon }] : []),
    ...(isAdmin.value ? [{ name: 'Audit Trail', href: '/audit-log', icon: ShieldExclamationIcon }] : []),
    ...(can('activity_reports') ? [{ name: 'Activity Reports', href: '/activity-reports', icon: DocumentTextIcon }] : []),
    ...(can('work_management') ? [{ name: 'Work Management', href: '/workspaces', icon: TableCellsIcon }] : []),
    ...(can('work_management') && (isAdmin.value || user.value?.role === 'director' || (user.value?.subordinates_count ?? 0) > 0)
        ? [{ name: "My Team's Tasks", href: '/team/tasks', icon: UserGroupIcon }] : []),
    ...(can('data_collection') ? [{ name: 'Data Collection', href: '/data-collection', icon: ClipboardDocumentCheckIcon }] : []),
    ...(can('data_collection') ? [{ name: 'My Collection', href: '/my-collection', icon: QueueListIcon }] : []),
    ...(can('data_collection') && (isAdmin.value || user.value?.role === 'director')
        ? [{ name: 'Review Queue', href: '/data-collection/review-queue', icon: ClipboardDocumentCheckIcon }] : []),
    ...(can('fixed_assets') ? [{ name: 'Fixed Assets', href: '/fixed-assets', icon: ServerStackIcon }] : []),
    ...(can('fixed_assets') && (isAdmin.value || user.value?.role === 'director')
        ? [{ name: 'Asset Categories', href: '/asset-categories', icon: FolderOpenIcon }] : []),
    ...(can('stores') ? [{ name: 'Stores', href: '/stores', icon: ServerStackIcon }] : []),
    ...(can('stores') ? [{ name: 'Items', href: '/items', icon: TableCellsIcon }] : []),
    ...(can('stock_transfers') ? [{ name: 'Stock Transfers', href: '/stock-transfers', icon: TruckIcon }] : []),
    ...(can('stocktakes') ? [{ name: 'Stocktakes', href: '/stocktakes', icon: QueueListIcon }] : []),
    ...(can('procurement') ? [{ name: 'Suppliers', href: '/suppliers', icon: TruckIcon }] : []),
    ...(can('procurement') ? [{ name: 'Purchase Orders', href: '/purchase-orders', icon: ClipboardDocumentListIcon }] : []),
    ...(can('stores') && (isAdmin.value || user.value?.role === 'director')
        ? [{ name: 'Departments', href: '/departments', icon: FolderOpenIcon }] : []),
    ...(can('stores') && (isAdmin.value || user.value?.role === 'director')
        ? [{ name: 'Locations', href: '/locations', icon: TagIcon }] : []),
    ...(can('item_categories') && (isAdmin.value || user.value?.role === 'director')
        ? [{ name: 'Item Categories', href: '/item-categories', icon: FolderOpenIcon }] : []),
    ...(can('extensions')   ? [{ name: 'Extensions',  href: '/extensions',                       icon: SignalIcon }] : []),
    ...(can('analytics')    ? [{ name: 'Analytics',   href: '/analytics',                        icon: ChartBarIcon }] : []),
    ...(can('targets')      ? [{ name: 'Targets',     href: '/call-targets',                     icon: FlagIcon }] : []),
    ...(can('by_project')   ? [{ name: 'By Project',  href: '/distress-domains/section/project', icon: FolderOpenIcon }] : []),
    ...(can('domains')      ? [{ name: 'Domains',     href: '/distress-domains',                 icon: TagIcon }] : []),
    ...(can('bot_contacts') ? [{ name: 'Bot Contacts',href: '/uchat-contacts',                   icon: ChatBubbleLeftRightIcon }] : []),
    ...(isAdmin.value || can('registry') ? [{ name: 'Asset Register', href: '/registry', icon: ServerStackIcon }] : []),
    ...(isAdmin.value ? [{ name: 'IT Asset Categories', href: '/registry/categories', icon: FolderOpenIcon }] : []),
    ...(isAdmin.value ? [{ name: 'Transcription Test Tool', href: '/transcription-test', icon: MicrophoneIcon }] : []),
    ...(isAdmin.value || can('risk')     ? [{ name: 'Risk Register',  href: '/risk',     icon: ShieldExclamationIcon }] : []),
    ...(can('sbc')          ? [{ name: 'SBC Signups', href: '/sbc',                              icon: TableCellsIcon }] : []),
    ...(can('yalep') ? [{ name: 'YALeP Students', href: '/sbc?sheet=Certificates%20To%20Process', icon: TableCellsIcon }] : []),
    ...(can('roles')        ? [{ name: 'Roles',       href: '/roles',                            icon: ShieldCheckIcon }] : []),
    ...(can('users')        ? [{ name: 'Users',       href: '/users',                            icon: UserGroupIcon }] : []),
    ...(isAdmin.value || ['director', 'helpline_manager'].includes(user.value?.role) ? [{ name: 'Counsellor Profiles', href: '/counsellor-profiles', icon: UserGroupIcon }] : []),
    { name: 'Timetable', href: '/timetable', icon: CalendarDaysIcon },
    { name: 'Progress Report', href: '/progress-reports', icon: ClipboardDocumentListIcon },
    ...(isAdmin.value || ['director', 'helpline_manager'].includes(user.value?.role)
        ? [{ name: 'Team Reports', href: '/progress-reports/team', icon: ClipboardDocumentCheckIcon }] : []),
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
    <div :class="['min-h-screen flex', isLight ? 'bg-gray-50' : 'bg-[#0d1117]']">
        <!-- Sidebar backdrop (mobile) -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-20 bg-black/50 lg:hidden"
            @click="sidebarOpen = false"
        />

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-30 w-64 flex flex-col transition-transform duration-200',
                isLight ? 'bg-white border-r border-gray-200' : 'bg-gray-900',
                'lg:static lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <!-- Logo -->
            <div :class="['flex h-16 items-center gap-3 px-5 border-b', isLight ? 'border-gray-200' : 'border-gray-700']">
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
                    <!-- Teardrop head -->
                    <ellipse cx="65" cy="13" rx="11" ry="14" :fill="isLight ? '#1f2937' : '#ffffff'"/>
                </svg>
                <div class="leading-tight min-w-0">
                    <div :class="['font-bold text-sm leading-none', isLight ? 'text-gray-900' : 'text-white']">youth</div>
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
                                ? (isLight ? 'text-red-600 hover:bg-red-50' : 'text-red-300 hover:bg-gray-800 hover:text-red-200')
                                : (isLight ? 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' : 'text-gray-300 hover:bg-gray-800 hover:text-white'),
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
            <div :class="['border-t p-4', isLight ? 'border-gray-200' : 'border-gray-700']">
                <div class="flex items-center gap-3">
                    <Link href="/profile" class="flex items-center gap-3 flex-1 min-w-0 hover:opacity-80 transition-opacity">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-white text-sm font-semibold flex-shrink-0">
                            {{ user?.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p :class="['text-sm font-medium truncate', isLight ? 'text-gray-900' : 'text-white']">{{ user?.name }}</p>
                            <p class="text-xs text-gray-400 truncate capitalize">{{ user?.role }}</p>
                        </div>
                    </Link>
                    <button
                        @click="logout"
                        :class="['p-1.5 rounded-lg transition-colors', isLight ? 'text-gray-400 hover:text-gray-900 hover:bg-gray-100' : 'text-gray-400 hover:text-white hover:bg-gray-700']"
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
            <header :class="['h-16 border-b flex items-center gap-4 px-4 lg:px-6 flex-shrink-0', isLight ? 'bg-white border-gray-200' : 'bg-[#0f1117] border-gray-800']">
                <button
                    :class="['p-2 rounded-lg lg:hidden', isLight ? 'text-gray-500 hover:bg-gray-100' : 'text-gray-400 hover:bg-gray-800']"
                    @click="sidebarOpen = true"
                >
                    <Bars3Icon class="h-5 w-5" />
                </button>
                <div class="flex-1 min-w-0">
                    <h1 :class="['text-base font-semibold leading-tight truncate', isLight ? 'text-gray-900' : 'text-white']">
                        <slot name="title" />
                    </h1>
                    <p class="text-xs text-gray-400 leading-tight truncate hidden sm:block">
                        <slot name="subtitle" />
                    </p>
                </div>
                <slot name="header-actions" />
                <!-- Dialer toggle -->
                <button
                    @click="dialerOpen = !dialerOpen"
                    :class="['p-2 rounded-lg transition-colors flex-shrink-0', isLight ? 'text-gray-500 hover:bg-gray-100' : 'text-gray-400 hover:bg-gray-800']"
                    title="Dialer"
                >
                    <PhoneIcon class="h-5 w-5" />
                </button>
                <NotificationBell />
                <!-- Light / dark toggle -->
                <button
                    @click="toggleTheme"
                    :class="['p-2 rounded-lg transition-colors flex-shrink-0', isLight ? 'text-gray-500 hover:bg-gray-100' : 'text-gray-400 hover:bg-gray-800']"
                    :title="isLight ? 'Switch to dark mode' : 'Switch to light mode'"
                >
                    <SunIcon v-if="isLight" class="h-5 w-5" />
                    <MoonIcon v-else class="h-5 w-5" />
                </button>
                <!-- Urgent cases bell -->
                <Link href="/urgent-cases"
                    :class="['relative p-2 rounded-lg transition-colors flex-shrink-0',
                             urgentCount > 0
                                ? (isLight ? 'text-red-600 hover:bg-red-50 animate-flicker' : 'text-red-400 hover:bg-red-900/30 animate-flicker')
                                : (isLight ? 'text-gray-500 hover:bg-gray-100' : 'text-gray-400 hover:bg-gray-800')]"
                    :title="urgentCount > 0 ? `${urgentCount} open urgent case${urgentCount > 1 ? 's' : ''}` : 'No urgent cases'">
                    <BellIcon class="h-6 w-6" />
                    <!-- Red count badge -->
                    <span v-if="urgentCount > 0"
                        :class="['absolute -top-1 -right-1 min-w-[1.35rem] h-[1.35rem] px-1 rounded-full',
                                 'bg-red-600 text-white text-[11px] font-extrabold',
                                 'flex items-center justify-center shadow-lg',
                                 isLight ? 'ring-2 ring-white' : 'ring-2 ring-[#0f1117]']">
                        {{ urgentCount > 99 ? '99+' : urgentCount }}
                    </span>
                    <span v-else
                        :class="['absolute top-2 right-2 h-2 w-2 rounded-full bg-gray-600', isLight ? 'ring-2 ring-white' : 'ring-2 ring-[#0f1117]']">
                    </span>
                </Link>
                <!-- User profile -->
                <div :class="['flex items-center gap-3 pl-3 border-l flex-shrink-0', isLight ? 'border-gray-200' : 'border-gray-700']">
                    <Link href="/profile" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-white text-sm font-semibold flex-shrink-0">
                            {{ user?.name?.charAt(0)?.toUpperCase() }}
                        </div>
                        <div class="hidden md:block">
                            <p :class="['text-sm font-medium leading-tight', isLight ? 'text-gray-900' : 'text-white']">{{ user?.name }}</p>
                            <p class="text-xs text-gray-400 capitalize leading-tight">{{ user?.role }}</p>
                        </div>
                    </Link>
                    <button
                        @click="logout"
                        :class="['p-1.5 rounded-lg transition-colors', isLight ? 'text-gray-400 hover:text-gray-900 hover:bg-gray-100' : 'text-gray-400 hover:text-white hover:bg-gray-700']"
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
            <main :class="['flex-1 p-4 lg:p-6 overflow-auto', isLight ? 'bg-gray-50' : 'bg-[#0d1117]']">
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

    <!-- Ticket modal: opened by clicking a queued call below -->
    <CallTicketModal
        v-if="pendingCall"
        :call="pendingCall"
        @close="pendingCall = null"
        @minimize="minimizePendingCall"
    />

    <!-- Floating queue: calls ≥ 15s awaiting a ticket, stacked on the right.
         No dismiss — a ticket must be logged to clear an entry, and the
         queue is re-seeded from the server on load so it survives a refresh. -->
    <PendingTicketQueue
        :calls="pendingTicketQueue"
        @open="openTicketFromQueue"
    />

    <!-- Incoming call popup (polled every 8 s) -->
    <IncomingCallPopup
        :calls="visibleCalls"
        @dismiss="dismissCall"
    />

    <!-- WebRTC Dialer — opened via the header phone icon, not a floating button -->
    <Dialer v-model:open="dialerOpen" />

    <!-- Floating chat assistant -->
    <ChatAssistant />

    <!-- Complete-profile prompt -->
    <CompleteProfileModal
        v-if="showCompleteProfile && user"
        :user="user"
        @dismiss="showCompleteProfile = false"
    />
</template>
