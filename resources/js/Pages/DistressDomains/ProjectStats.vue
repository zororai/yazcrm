<script setup>
import { ref, computed } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ArrowLeftIcon, PlusIcon, CheckCircleIcon, XCircleIcon,
    PencilSquareIcon, TrashIcon, Cog6ToothIcon, FolderOpenIcon,
    TicketIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    projects: Array,
    items:    Array,
    months:   Array,
});

// ── Active project tab ────────────────────────────────────────────────────────
const activeTab = ref(props.projects[0]?.name ?? null);
const current   = computed(() => props.projects.find(p => p.name === activeTab.value) ?? null);

// ── Monthly chart helpers ─────────────────────────────────────────────────────
const chartMax = computed(() => {
    if (!current.value) return 1;
    return Math.max(...current.value.monthly, 1);
});

function barHeight(count) {
    return Math.max(4, Math.round((count / chartMax.value) * 96));
}

const palette = ['bg-brand-500', 'bg-indigo-500', 'bg-emerald-500', 'bg-orange-400', 'bg-pink-500'];

function projectColor(idx) {
    return palette[idx % palette.length];
}

// ── Manage section (lookup item CRUD) ────────────────────────────────────────
const showManage = ref(false);

const showAdd = ref(false);
const addForm = useForm({ type: 'project', name: '', sort_order: '' });

function storeItem() {
    addForm.post('/lookup-items', {
        onSuccess: () => { showAdd.value = false; addForm.reset(); addForm.type = 'project'; },
    });
}

const editing  = ref(null);
const editForm = useForm({ name: '', sort_order: '', is_active: true });

function openEdit(item) {
    editing.value       = item.id;
    editForm.name       = item.name;
    editForm.sort_order = item.sort_order;
    editForm.is_active  = item.is_active;
}

function saveEdit(item) {
    editForm.put(`/lookup-items/${item.id}`, {
        onSuccess: () => { editing.value = null; editForm.reset(); },
    });
}

function cancelEdit() { editing.value = null; editForm.reset(); }

function removeItem(item) {
    if (!confirm(`Remove project "${item.name}"?`)) return;
    router.delete(`/lookup-items/${item.id}`);
}

// ── Totals across all projects ────────────────────────────────────────────────
const grandTotal     = computed(() => props.projects.reduce((s, p) => s + p.total,      0));
const grandThisMonth = computed(() => props.projects.reduce((s, p) => s + p.this_month, 0));
</script>

<template>
    <AppLayout>
        <template #title>Stats by Project</template>
        <template #header-actions>
            <Link href="/distress-domains" class="btn-secondary btn-sm">
                <ArrowLeftIcon class="h-4 w-4" /> Back
            </Link>
            <button @click="showManage = !showManage"
                class="btn-secondary btn-sm"
                :class="showManage ? 'ring-2 ring-brand-400' : ''">
                <Cog6ToothIcon class="h-4 w-4" />
                Manage Projects
            </button>
        </template>

        <!-- ── Grand summary ──────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="card text-center p-4">
                <p class="text-2xl font-bold text-gray-900">{{ props.projects.length }}</p>
                <p class="text-xs text-gray-500 mt-1">Active Projects</p>
            </div>
            <div class="card text-center p-4">
                <p class="text-2xl font-bold text-gray-900">{{ grandTotal.toLocaleString() }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Tickets (All Time)</p>
            </div>
            <div class="card text-center p-4 bg-blue-50 border border-blue-100">
                <p class="text-2xl font-bold text-blue-700">{{ grandThisMonth.toLocaleString() }}</p>
                <p class="text-xs text-gray-500 mt-1">Tickets This Month</p>
            </div>
            <div class="card text-center p-4">
                <Link href="/tickets" class="group block">
                    <p class="text-2xl font-bold text-brand-600 group-hover:text-brand-700">
                        <TicketIcon class="h-6 w-6 inline mb-1" />
                    </p>
                    <p class="text-xs text-gray-500 mt-1">Log New Ticket</p>
                </Link>
            </div>
        </div>

        <!-- ── Project tabs nav bar ───────────────────────────────────────── -->
        <div class="flex flex-wrap gap-2 mb-5">
            <button
                v-for="(project, i) in projects"
                :key="project.name"
                @click="activeTab = project.name"
                :class="[
                    'flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium border transition-all',
                    activeTab === project.name
                        ? 'bg-brand-600 text-white border-brand-600 shadow'
                        : 'bg-white text-gray-600 border-gray-200 hover:border-brand-300 hover:text-brand-600',
                ]"
            >
                <span :class="['inline-block h-2 w-2 rounded-full', projectColor(i)]"
                    v-if="activeTab !== project.name"></span>
                {{ project.name }}
                <span class="ml-1 text-xs opacity-75">({{ project.total.toLocaleString() }})</span>
            </button>
        </div>

        <!-- ── Active project panel ───────────────────────────────────────── -->
        <template v-if="current">
            <!-- KPI cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
                <div class="card p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ current.total.toLocaleString() }}</p>
                    <p class="text-xs text-gray-500 mt-1">Total Tickets</p>
                </div>
                <div class="card p-4 text-center bg-blue-50 border border-blue-100">
                    <p class="text-2xl font-bold text-blue-700">{{ current.this_month.toLocaleString() }}</p>
                    <p class="text-xs text-gray-500 mt-1">This Month</p>
                </div>
                <div v-for="p in current.purposes.slice(0,2)" :key="p.purpose" class="card p-4 text-center">
                    <p class="text-2xl font-bold text-indigo-700">{{ p.count.toLocaleString() }}</p>
                    <p class="text-xs text-gray-500 mt-1 truncate" :title="p.purpose">{{ p.purpose }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
                <!-- Monthly bar chart -->
                <div class="card lg:col-span-3 p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Monthly Tickets — Last 6 Months</h3>
                    <div class="flex items-end gap-2 h-28">
                        <div
                            v-for="(count, i) in current.monthly"
                            :key="i"
                            class="flex-1 flex flex-col items-center gap-1 min-w-0"
                        >
                            <span class="text-xs text-gray-500 font-medium">{{ count > 0 ? count.toLocaleString() : '' }}</span>
                            <div
                                class="w-full rounded-t transition-all duration-500 bg-brand-500 opacity-80 hover:opacity-100"
                                :style="{ height: barHeight(count) + 'px' }"
                            />
                            <span class="text-xs text-gray-400 truncate w-full text-center">{{ months[i] }}</span>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-400">
                        <span>Peak: {{ Math.max(...current.monthly).toLocaleString() }}</span>
                        <Link :href="`/tickets?project=${encodeURIComponent(current.name)}`"
                            class="text-brand-600 hover:underline font-medium flex items-center gap-1">
                            <TicketIcon class="h-3.5 w-3.5" /> View tickets for {{ current.name }}
                        </Link>
                    </div>
                </div>

                <!-- Breakdowns -->
                <div class="lg:col-span-2 flex flex-col gap-4">
                    <!-- Purpose breakdown -->
                    <div class="card p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Purpose of Call</h3>
                        <div v-if="current.purposes.length" class="space-y-2">
                            <div v-for="p in current.purposes" :key="p.purpose" class="space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-600 truncate" :title="p.purpose">{{ p.purpose }}</span>
                                    <span class="font-semibold text-gray-800 ml-2 flex-shrink-0">{{ p.count.toLocaleString() }}</span>
                                </div>
                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div
                                        class="h-full bg-brand-400 rounded-full transition-all"
                                        :style="{ width: ((p.count / current.total) * 100).toFixed(1) + '%' }"
                                    />
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-xs text-gray-400">No data.</p>
                    </div>

                    <!-- Services breakdown -->
                    <div class="card p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Top Services Requested</h3>
                        <div v-if="current.services.length" class="space-y-2">
                            <div v-for="s in current.services" :key="s.service" class="space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-600 truncate" :title="s.service">{{ s.service }}</span>
                                    <span class="font-semibold text-gray-800 ml-2 flex-shrink-0">{{ s.count.toLocaleString() }}</span>
                                </div>
                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div
                                        class="h-full bg-indigo-400 rounded-full transition-all"
                                        :style="{ width: ((s.count / current.total) * 100).toFixed(1) + '%' }"
                                    />
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-xs text-gray-400">No data.</p>
                    </div>
                </div>
            </div>

            <!-- All-projects comparison table -->
            <div class="card p-0 overflow-hidden mt-5">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="table-th">Project</th>
                            <th class="table-th text-right">Total Tickets</th>
                            <th class="table-th text-right">This Month</th>
                            <th class="table-th">Share of Total</th>
                            <th class="table-th text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr
                            v-for="(p, i) in projects"
                            :key="p.name"
                            @click="activeTab = p.name"
                            class="cursor-pointer hover:bg-gray-50 transition-colors"
                            :class="activeTab === p.name ? 'bg-brand-50' : ''"
                        >
                            <td class="table-td font-medium">
                                <span :class="['inline-block h-2.5 w-2.5 rounded-full mr-2', projectColor(i)]"></span>
                                {{ p.name }}
                                <span v-if="!p.is_active" class="ml-1 text-xs text-gray-400">(inactive)</span>
                            </td>
                            <td class="table-td text-right font-semibold">{{ p.total.toLocaleString() }}</td>
                            <td class="table-td text-right text-blue-700 font-semibold">{{ p.this_month.toLocaleString() }}</td>
                            <td class="table-td min-w-[160px]">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div
                                            :class="['h-full rounded-full transition-all', projectColor(i)]"
                                            :style="{ width: grandTotal ? ((p.total / grandTotal) * 100).toFixed(1) + '%' : '0%' }"
                                        />
                                    </div>
                                    <span class="text-xs text-gray-500 w-10 text-right">
                                        {{ grandTotal ? ((p.total / grandTotal) * 100).toFixed(1) : 0 }}%
                                    </span>
                                </div>
                            </td>
                            <td class="table-td">
                                <Link
                                    :href="`/tickets?project=${encodeURIComponent(p.name)}`"
                                    class="text-xs text-brand-600 hover:underline"
                                    @click.stop
                                >
                                    View tickets
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!projects.length">
                            <td colspan="5" class="py-10 text-center text-sm text-gray-400">No projects found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <div v-else class="card py-12 text-center text-sm text-gray-400">
            No projects configured. Click "Manage Projects" to add one.
        </div>

        <!-- ── Manage Projects (collapsible) ──────────────────────────────── -->
        <div v-if="showManage" class="mt-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <FolderOpenIcon class="h-4 w-4 text-gray-400" /> Manage Project List
                </h3>
                <button @click="showAdd = !showAdd" class="btn-primary btn-sm">
                    <PlusIcon class="h-4 w-4" /> Add Project
                </button>
            </div>

            <!-- Add form -->
            <div v-if="showAdd" class="card mb-3">
                <div class="flex gap-3 items-end flex-wrap">
                    <div class="flex-1 min-w-[180px]">
                        <label class="label">Project Name *</label>
                        <input v-model="addForm.name" class="input"
                            :class="{ 'border-red-500': addForm.errors.name }"
                            placeholder="e.g. YAZ" autofocus />
                        <p v-if="addForm.errors.name" class="mt-1 text-xs text-red-600">{{ addForm.errors.name }}</p>
                    </div>
                    <div class="w-28">
                        <label class="label">Order</label>
                        <input v-model="addForm.sort_order" type="number" min="0" class="input" placeholder="0" />
                    </div>
                    <div class="flex gap-2 pb-0.5">
                        <button @click="storeItem" :disabled="addForm.processing" class="btn-primary">
                            <CheckCircleIcon class="h-4 w-4" /> Save
                        </button>
                        <button @click="showAdd = false; addForm.reset(); addForm.type = 'project';" class="btn-secondary">
                            <XCircleIcon class="h-4 w-4" /> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <div class="card p-0 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="table-th">Name</th>
                            <th class="table-th w-24 text-center">Order</th>
                            <th class="table-th w-24 text-center">Active</th>
                            <th class="table-th w-28">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-if="!items.length">
                            <td colspan="4" class="py-8 text-center text-sm text-gray-400">No projects yet.</td>
                        </tr>
                        <template v-for="item in items" :key="item.id">
                            <tr v-if="editing !== item.id" class="hover:bg-gray-50"
                                :class="{ 'opacity-50': !item.is_active }">
                                <td class="table-td font-medium">{{ item.name }}</td>
                                <td class="table-td text-center text-sm text-gray-500">{{ item.sort_order }}</td>
                                <td class="table-td text-center">
                                    <span :class="item.is_active ? 'badge bg-green-100 text-green-700' : 'badge bg-gray-100 text-gray-500'">
                                        {{ item.is_active ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="table-td">
                                    <div class="flex gap-1">
                                        <button @click="openEdit(item)" class="p-1.5 rounded text-gray-400 hover:text-brand-600 hover:bg-gray-100" title="Edit">
                                            <PencilSquareIcon class="h-4 w-4" />
                                        </button>
                                        <button @click="removeItem(item)" class="p-1.5 rounded text-gray-400 hover:text-red-600 hover:bg-red-50" title="Delete">
                                            <TrashIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-else class="bg-brand-50">
                                <td class="table-td">
                                    <input v-model="editForm.name" class="input py-1 text-sm"
                                        :class="{ 'border-red-500': editForm.errors.name }" autofocus />
                                    <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
                                </td>
                                <td class="table-td">
                                    <input v-model="editForm.sort_order" type="number" min="0" class="input w-20 py-1 text-sm" />
                                </td>
                                <td class="table-td text-center">
                                    <label class="flex items-center justify-center gap-1 cursor-pointer text-sm">
                                        <input type="checkbox" v-model="editForm.is_active" class="rounded border-gray-300 text-brand-600" />
                                        Active
                                    </label>
                                </td>
                                <td class="table-td">
                                    <div class="flex gap-1">
                                        <button @click="saveEdit(item)" :disabled="editForm.processing" class="btn-primary btn-sm py-1">
                                            <CheckCircleIcon class="h-4 w-4" /> Save
                                        </button>
                                        <button @click="cancelEdit" class="btn-secondary btn-sm py-1">
                                            <XCircleIcon class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
