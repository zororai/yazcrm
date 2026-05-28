<script setup>
import { ref, watch, computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';

const distressDomains = computed(() => usePage().props.distressDomains ?? []);
import AppLayout from '@/Layouts/AppLayout.vue';
import { PlusIcon, MagnifyingGlassIcon, XMarkIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline';
import { debounce } from 'lodash-es';

const props    = defineProps({ tickets: Object, clients: Array, agents: Array, filters: Object, keyPops: Array, modesOfCommunication: Array, projects: Array, servicesRequested: Array, secondServicesRequested: Array, servicesRequestedBefore: Array, referredTo: Array });
const isAdmin  = computed(() => usePage().props.auth.user?.role === 'admin');
const search   = ref(props.filters.search ?? '');
const status   = ref(props.filters.status ?? '');
const priority = ref(props.filters.priority ?? '');
const showAdd  = ref(false);

const showContactDrop = ref(false);
const contactResults  = ref([]);

const debouncedContactSearch = debounce(async (q) => {
    if (!q || q.length < 2) { contactResults.value = []; return; }
    const res = await fetch(`/calls/number-search?q=${encodeURIComponent(q)}`);
    contactResults.value = await res.json();
}, 300);

function onContactInput() { debouncedContactSearch(addForm.contact_number); }
function selectContact(num) { addForm.contact_number = num; showContactDrop.value = false; contactResults.value = []; }

const addForm = useForm({
    subject: '', contact_number: '', description: '', priority: 'medium', follow_up_date: '',
    // CRM fields
    mode_of_communication:    'phone',
    call_validity:            'valid',
    purpose_of_call:          '',
    immediate_action_required: false,
    caller_age:               '',
    caller_gender:            '',
    caller_marital_status:    '',
    key_pops:                 '',
    province:                 '',
    district:                 '',
    location:                 '',
    is_repeat_caller:         false,
    project:                   '',
    services_requested_before: '',
    services_requested:        '',
    second_service_requested: '',
    number_of_services:       '',
    referred_to:              '',
    uptake_confirmed:         false,
});

function apply() {
    router.get('/tickets', {
        search:   search.value || undefined,
        status:   status.value || undefined,
        priority: priority.value || undefined,
    }, { preserveState: true, replace: true });
}

const debouncedApply = debounce(apply, 350);
watch(search, debouncedApply);
watch([status, priority], apply);

function store() {
    addForm.post('/tickets', { onSuccess: () => { showAdd.value = false; addForm.reset(); } });
}

const provinces = [
    'Bulawayo', 'Harare', 'Manicaland', 'Mashonaland Central',
    'Mashonaland East', 'Mashonaland West', 'Masvingo',
    'Matabeleland North', 'Matabeleland South', 'Midlands',
];

const priorityColor = {
    low:    'bg-gray-100 text-gray-600',
    medium: 'bg-blue-100 text-blue-800',
    high:   'bg-orange-100 text-orange-800',
    urgent: 'bg-red-100 text-red-800',
};
const statusColor = {
    open:        'bg-yellow-100 text-yellow-800',
    in_progress: 'bg-blue-100 text-blue-800',
    resolved:    'bg-green-100 text-green-800',
    closed:      'bg-gray-100 text-gray-600',
};
</script>

<template>
    <AppLayout>
        <template #title>Tickets</template>
        <template #header-actions>
            <Link v-if="isAdmin" href="/tickets/import" class="btn-secondary btn-sm">
                <ArrowUpTrayIcon class="h-4 w-4" /> Import
            </Link>
            <button @click="showAdd = true" class="btn-primary btn-sm">
                <PlusIcon class="h-4 w-4" /> New Ticket
            </button>
        </template>

        <div class="card mb-4 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48">
                <label class="label">Search</label>
                <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                    <input v-model="search" class="input pl-9" placeholder="Subject or description…" />
                </div>
            </div>
            <div>
                <label class="label">Status</label>
                <select v-model="status" class="input w-36">
                    <option value="">All</option>
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                </select>
            </div>
            <div>
                <label class="label">Priority</label>
                <select v-model="priority" class="input w-32">
                    <option value="">All</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Name</th>
                        <th class="table-th">Purpose</th>
                        <th class="table-th">Priority</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Agent</th>
                        <th class="table-th">Created</th>
                        <th class="table-th w-16" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="!tickets.data.length">
                        <td colspan="7" class="py-12 text-center text-sm text-gray-400">No tickets found.</td>
                    </tr>
                    <tr v-for="t in tickets.data" :key="t.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium max-w-xs truncate">
                            <Link :href="`/tickets/${t.id}`" class="text-brand-600 hover:underline">{{ t.subject }}</Link>
                        </td>
                        <td class="table-td text-xs max-w-xs truncate">{{ t.purpose_of_call ?? '—' }}</td>
                        <td class="table-td">
                            <span :class="['badge', priorityColor[t.priority]]">{{ t.priority }}</span>
                        </td>
                        <td class="table-td">
                            <span :class="['badge', statusColor[t.status]]">{{ t.status.replace('_', ' ') }}</span>
                        </td>
                        <td class="table-td">{{ t.agent?.name ?? '—' }}</td>
                        <td class="table-td text-xs">{{ new Date(t.created_at).toLocaleDateString() }}</td>
                        <td class="table-td">
                            <Link :href="`/tickets/${t.id}`" class="btn-secondary btn-sm">View</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="tickets.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">Showing {{ tickets.from }}–{{ tickets.to }} of {{ tickets.total }}</p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in tickets.links" :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-xs', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url && 'opacity-40 pointer-events-none']"
                        preserve-state v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- New ticket modal -->
        <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col">
                <!-- Modal header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                    <h3 class="font-semibold text-gray-900">New Ticket</h3>
                    <button @click="showAdd = false" class="text-gray-400 hover:text-gray-600">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <!-- Scrollable body -->
                <form @submit.prevent="store" class="overflow-y-auto flex-1 px-6 py-4 space-y-5">

                    <!-- ── Basic Info ── -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Basic Info</h4>
                        <div class="space-y-3">
                            <div>
                                <label class="label">Name *</label>
                                <input v-model="addForm.subject" class="input" :class="{ 'border-red-500': addForm.errors.subject }" required />
                                <p v-if="addForm.errors.subject" class="mt-1 text-xs text-red-600">{{ addForm.errors.subject }}</p>
                            </div>
                            <div class="relative">
                                <label class="label">Contact / Sisters Number</label>
                                <input
                                    v-model="addForm.contact_number"
                                    @input="onContactInput"
                                    @focus="showContactDrop = true"
                                    @blur="() => setTimeout(() => showContactDrop = false, 150)"
                                    class="input"
                                    placeholder="Type or search number…"
                                    autocomplete="off"
                                />
                                <ul v-if="showContactDrop && contactResults.length"
                                    class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                    <li v-for="num in contactResults" :key="num">
                                        <button type="button"
                                            @mousedown.prevent="selectContact(num)"
                                            class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                            {{ num }}
                                        </button>
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <label class="label">Priority</label>
                                <select v-model="addForm.priority" class="input w-40">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Follow-up Date</label>
                                <input v-model="addForm.follow_up_date" type="date" class="input w-48" />
                            </div>
                        </div>
                    </div>

                    <!-- ── Call Details ── -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Call Details</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">Mode of Communication</label>
                                <select v-model="addForm.mode_of_communication" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="m in props.modesOfCommunication" :key="m" :value="m">{{ m }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Call Validity</label>
                                <select v-model="addForm.call_validity" class="input">
                                    <option value="">— select —</option>
                                    <option value="valid">Valid</option>
                                    <option value="invalid">Invalid</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="label">Purpose of Call</label>
                                <select v-model="addForm.purpose_of_call" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="d in distressDomains" :key="d" :value="d">{{ d }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Project</label>
                                <select v-model="addForm.project" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="p in props.projects" :key="p" :value="p">{{ p }}</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-4 pt-5">
                                <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                                    <input type="checkbox" v-model="addForm.immediate_action_required" class="rounded border-gray-300 text-brand-600" />
                                    Immediate Action Required
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- ── Caller Info ── -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Caller Information</h4>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="label">Age</label>
                                <input v-model="addForm.caller_age" type="number" min="1" max="120" class="input" />
                            </div>
                            <div>
                                <label class="label">Gender</label>
                                <select v-model="addForm.caller_gender" class="input">
                                    <option value="">— select —</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                    <option value="prefer_not_to_say">Prefer not to say</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Marital Status</label>
                                <select v-model="addForm.caller_marital_status" class="input">
                                    <option value="">— select —</option>
                                    <option value="single">Single</option>
                                    <option value="married">Married</option>
                                    <option value="divorced">Divorced</option>
                                    <option value="widowed">Widowed</option>
                                    <option value="cohabiting">Cohabiting</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">Key Pops</label>
                                <select v-model="addForm.key_pops" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="kp in props.keyPops" :key="kp" :value="kp">{{ kp }}</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-2 pt-5">
                                <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                                    <input type="checkbox" v-model="addForm.is_repeat_caller" class="rounded border-gray-300 text-brand-600" />
                                    Repeat Caller
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- ── Location ── -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Location</h4>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="label">Province</label>
                                <select v-model="addForm.province" class="input">
                                    <option value="">— select —</option>
                                    <option v-for="p in provinces" :key="p" :value="p">{{ p }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label">District</label>
                                <input v-model="addForm.district" class="input" />
                            </div>
                            <div>
                                <label class="label">Location</label>
                                <input v-model="addForm.location" class="input" />
                            </div>
                        </div>
                    </div>

                    <!-- ── Services ── -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Services</h4>
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="label">Services Requested Before</label>
                                    <select v-model="addForm.services_requested_before" class="input">
                                        <option value="">— select —</option>
                                        <option v-for="s in props.servicesRequestedBefore" :key="s" :value="s">{{ s }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Services Requested</label>
                                    <select v-model="addForm.services_requested" class="input">
                                        <option value="">— select —</option>
                                        <option v-for="s in props.servicesRequested" :key="s" :value="s">{{ s }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Second Service Requested</label>
                                    <select v-model="addForm.second_service_requested" class="input">
                                        <option value="">— select —</option>
                                        <option v-for="s in props.secondServicesRequested" :key="s" :value="s">{{ s }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">No. of Services</label>
                                    <input v-model="addForm.number_of_services" type="number" min="0" class="input" />
                                </div>
                                <div>
                                    <label class="label">Referred To</label>
                                    <select v-model="addForm.referred_to" class="input">
                                        <option value="">— select —</option>
                                        <option v-for="r in props.referredTo" :key="r" :value="r">{{ r }}</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                                    <input type="checkbox" v-model="addForm.uptake_confirmed" class="rounded border-gray-300 text-brand-600" />
                                    Confirming Uptake of Services
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- ── Notes ── -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Notes</h4>
                        <div>
                            <label class="label">Counsellor's Notes</label>
                            <textarea v-model="addForm.description" class="input h-24 resize-none" placeholder="Notes from the session…" />
                        </div>
                    </div>
                </form>

                <!-- Modal footer -->
                <div class="flex gap-2 justify-end px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button type="button" @click="showAdd = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="store" class="btn-primary" :disabled="addForm.processing">
                        {{ addForm.processing ? 'Creating…' : 'Create Ticket' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
