<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon, PrinterIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ student: Object, year: Number, attendance: Object, results: Array, absences: Array });

const counts = computed(() => ({
    present: props.attendance.present ?? 0,
    late: props.attendance.late ?? 0,
    absent: props.attendance.absent ?? 0,
    excused: props.attendance.excused ?? 0,
}));
const daysMarked = computed(() => Object.values(counts.value).reduce((a, b) => a + b, 0));
const attendanceRate = computed(() => daysMarked.value
    ? Math.round((counts.value.present + counts.value.late) / daysMarked.value * 100) : null);

// subject → term → [results]
const bySubject = computed(() => {
    const out = {};
    for (const r of props.results) {
        const name = r.subject?.name ?? '—';
        out[name] ??= { 1: [], 2: [], 3: [] };
        out[name][r.term].push(r);
    }
    return out;
});

const avg = list => list.length ? Math.round(list.reduce((s, r) => s + r.mark, 0) / list.length) : null;

const termAverages = computed(() => [1, 2, 3].map(t => avg(props.results.filter(r => r.term === t))));

function grade(mark) {
    if (mark === null) return '';
    if (mark >= 75) return 'A';
    if (mark >= 65) return 'B';
    if (mark >= 50) return 'C';
    if (mark >= 40) return 'D';
    return 'E';
}

const markClass = mark => mark === null ? 'text-gray-300' : mark < 50 ? 'text-red-600 font-semibold' : 'text-gray-900';

function changeYear(e) {
    router.get(`/school/students/${props.student.id}`, { year: e.target.value }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <template #title>{{ student.full_name }}</template>
        <template #header-actions>
            <button onclick="window.print()" class="btn-secondary btn-sm"><PrinterIcon class="h-4 w-4" /> Print</button>
        </template>

        <Link href="/school/students" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4 print:hidden">
            <ArrowLeftIcon class="h-4 w-4" /> Students
        </Link>

        <div class="card mb-6 flex flex-wrap gap-x-8 gap-y-2 items-center text-sm">
            <div><span class="text-gray-400">Adm. No.</span> <span class="font-medium">{{ student.admission_number }}</span></div>
            <div><span class="text-gray-400">Class</span> <span class="font-medium">{{ student.school_class?.name ?? '—' }}</span></div>
            <div><span class="text-gray-400">Sex</span> <span class="font-medium">{{ student.gender ?? '—' }}</span></div>
            <div v-if="student.guardian_name"><span class="text-gray-400">Guardian</span> <span class="font-medium">{{ student.guardian_name }} {{ student.guardian_phone }}</span></div>
            <div class="ml-auto">
                <select :value="year" @change="changeYear" class="input w-auto print:hidden">
                    <option v-for="y in [year + 1, year, year - 1, year - 2]" :key="y" :value="y">{{ y }}</option>
                </select>
                <span class="hidden print:inline font-medium">{{ year }}</span>
            </div>
        </div>

        <!-- Attendance -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
            <div class="card text-center">
                <div class="text-2xl font-bold" :class="attendanceRate !== null && attendanceRate < 80 ? 'text-red-600' : 'text-gray-900'">
                    {{ attendanceRate === null ? '—' : attendanceRate + '%' }}
                </div>
                <div class="text-xs text-gray-500">Attendance ({{ daysMarked }} days)</div>
            </div>
            <div v-for="(n, k) in counts" :key="k" class="card text-center">
                <div class="text-2xl font-bold text-gray-900">{{ n }}</div>
                <div class="text-xs text-gray-500 capitalize">{{ k }}</div>
            </div>
        </div>

        <!-- Results -->
        <div class="card p-0 overflow-x-auto mb-6">
            <div class="px-4 py-3 border-b border-gray-100 font-semibold text-gray-900">Results {{ year }}</div>
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Subject</th>
                        <th v-for="t in [1, 2, 3]" :key="t" class="table-th text-center">Term {{ t }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="(terms, subject) in bySubject" :key="subject">
                        <td class="table-td font-medium">{{ subject }}</td>
                        <td v-for="t in [1, 2, 3]" :key="t" class="table-td text-center">
                            <template v-if="terms[t].length">
                                <span :class="markClass(avg(terms[t]))">{{ avg(terms[t]) }}% {{ grade(avg(terms[t])) }}</span>
                                <div class="text-xs text-gray-400">
                                    <span v-for="r in terms[t]" :key="r.id" class="block" :title="r.comment ?? ''">
                                        {{ r.assessment }}: {{ r.mark }}
                                    </span>
                                </div>
                            </template>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                    </tr>
                    <tr v-if="!results.length">
                        <td colspan="4" class="table-td text-center text-gray-400 py-8">No marks recorded for {{ year }}.</td>
                    </tr>
                    <tr v-else class="bg-gray-50 font-semibold">
                        <td class="table-td">Average</td>
                        <td v-for="(a, i) in termAverages" :key="i" class="table-td text-center" :class="markClass(a)">
                            {{ a === null ? '—' : `${a}% ${grade(a)}` }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Absences -->
        <div v-if="absences.length" class="card p-0 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 font-semibold text-gray-900">Recent absences &amp; late arrivals</div>
            <table class="w-full">
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="a in absences" :key="a.date">
                        <td class="table-td w-32">{{ a.date }}</td>
                        <td class="table-td w-24">
                            <span class="badge" :class="a.status === 'absent' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'">{{ a.status }}</span>
                        </td>
                        <td class="table-td text-gray-500">{{ a.note }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
