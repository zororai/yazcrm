<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ date: String, teachers: Array, isAdmin: Boolean, myToday: Object });

const STATUSES = [
    { key: 'present', label: 'Present' },
    { key: 'late',    label: 'Late' },
    { key: 'absent',  label: 'Absent' },
    { key: 'leave',   label: 'On leave' },
];
const badge = {
    present: 'bg-green-100 text-green-700', late: 'bg-amber-100 text-amber-700',
    absent: 'bg-red-100 text-red-700', leave: 'bg-gray-100 text-gray-700',
};

const date = ref(props.date);
watch(date, d => router.get('/courses/facilitator-attendance', { date: d }, { preserveScroll: true, replace: true }));

const rows = ref([]);
watch(() => props.teachers, t => { rows.value = t.map(r => ({ ...r })); }, { immediate: true });

const form = useForm({ date: props.date, entries: [] });
function save() {
    form.date = props.date;
    form.entries = rows.value.map(r => ({ id: r.id, status: r.status || null, time_in: r.time_in || null, time_out: r.time_out || null, note: r.note }));
    form.post('/courses/facilitator-attendance', { preserveScroll: true });
}

const signForm = useForm({});
const signIn = () => signForm.post('/courses/sign-in', { preserveScroll: true });
const signOut = () => signForm.post('/courses/sign-out', { preserveScroll: true });
</script>

<template>
    <AppLayout>
        <template #title>Facilitator Attendance</template>

        <!-- Own sign-in for today -->
        <div class="card mb-6 flex flex-wrap items-center gap-4">
            <div class="text-sm">
                <div class="font-semibold text-gray-900">My attendance today</div>
                <div class="text-gray-500" v-if="myToday">
                    In {{ myToday.time_in ?? '—' }} · Out {{ myToday.time_out ?? '—' }}
                    <span class="badge ml-1" :class="badge[myToday.status]">{{ myToday.status }}</span>
                </div>
                <div class="text-gray-500" v-else>Not signed in yet.</div>
            </div>
            <div class="ml-auto flex gap-2">
                <button v-if="!myToday?.time_in" @click="signIn" class="btn-primary" :disabled="signForm.processing">Sign In</button>
                <button v-else-if="!myToday?.time_out" @click="signOut" class="btn-secondary" :disabled="signForm.processing">Sign Out</button>
            </div>
        </div>

        <div class="flex items-end gap-2 mb-4">
            <div>
                <label class="label">Date</label>
                <input v-model="date" type="date" class="input w-auto" />
            </div>
        </div>

        <div class="card p-0 overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Facilitator</th>
                        <th class="table-th">Status</th>
                        <th class="table-th">Time in</th>
                        <th class="table-th">Time out</th>
                        <th class="table-th">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="r in rows" :key="r.id">
                        <td class="table-td font-medium">{{ r.name }}</td>
                        <template v-if="isAdmin">
                            <td class="table-td">
                                <select v-model="r.status" class="input py-1 text-sm w-32">
                                    <option :value="null">— Not marked —</option>
                                    <option v-for="s in STATUSES" :key="s.key" :value="s.key">{{ s.label }}</option>
                                </select>
                            </td>
                            <td class="table-td"><input v-model="r.time_in" type="time" class="input py-1 text-sm w-28" /></td>
                            <td class="table-td"><input v-model="r.time_out" type="time" class="input py-1 text-sm w-28" /></td>
                            <td class="table-td"><input v-model="r.note" class="input py-1 text-sm" /></td>
                        </template>
                        <template v-else>
                            <td class="table-td">
                                <span v-if="r.status" class="badge" :class="badge[r.status]">{{ r.status }}</span>
                                <span v-else class="text-gray-300">—</span>
                            </td>
                            <td class="table-td">{{ r.time_in ?? '—' }}</td>
                            <td class="table-td">{{ r.time_out ?? '—' }}</td>
                            <td class="table-td text-gray-500">{{ r.note }}</td>
                        </template>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="5" class="table-td text-center text-gray-400 py-8">
                            No facilitators yet — give staff the "Courses (Facilitator)" permission under Users.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="isAdmin && rows.length" class="flex justify-end mt-4">
            <button @click="save" class="btn-primary" :disabled="form.processing">Save Attendance</button>
        </div>
    </AppLayout>
</template>
