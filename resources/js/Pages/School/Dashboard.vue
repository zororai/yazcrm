<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    isAdmin: Boolean, year: Number, term: Number,
    registers: Array, atRisk: Array, coverage: Array,
    teacherAttendance: Array, teachersToday: Object, teacherCount: Number, averages: Array,
});

const change = (key, value) => router.get('/courses', { year: props.year, term: props.term, [key]: value }, { preserveScroll: true });

const registersTaken = computed(() => props.registers.filter(r => r.marked > 0).length);
const pupilsPresent = computed(() => props.registers.reduce((s, r) => s + r.present, 0));
const pupilsMarked = computed(() => props.registers.reduce((s, r) => s + r.marked, 0));
const teachersIn = computed(() => (props.teachersToday?.present ?? 0) + (props.teachersToday?.late ?? 0));
const overallCoverage = computed(() => {
    const planned = props.coverage.reduce((s, c) => s + c.planned, 0);
    return planned ? Math.round(props.coverage.reduce((s, c) => s + c.completed, 0) / planned * 100) : null;
});

const barColour = p => p >= 75 ? 'bg-green-500' : p >= 40 ? 'bg-amber-500' : 'bg-red-500';
const rate = r => r.days ? Math.round((r.present + r.late) / r.days * 100) : 0;
</script>

<template>
    <AppLayout>
        <template #title>Courses Dashboard</template>
        <template #header-actions>
            <select :value="term" @change="change('term', $event.target.value)" class="input w-auto py-1 text-sm">
                <option v-for="t in [1, 2, 3]" :key="t" :value="t">Term {{ t }}</option>
            </select>
            <select :value="year" @change="change('year', $event.target.value)" class="input w-auto py-1 text-sm">
                <option v-for="y in [year + 1, year, year - 1, year - 2]" :key="y" :value="y">{{ y }}</option>
            </select>
        </template>

        <!-- Headline numbers (today + this term) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card">
                <div class="text-xs text-gray-500">Registers taken today</div>
                <div class="text-2xl font-bold text-gray-900">{{ registersTaken }} / {{ registers.length }}</div>
            </div>
            <div class="card">
                <div class="text-xs text-gray-500">Participants present today</div>
                <div class="text-2xl font-bold text-gray-900">
                    {{ pupilsPresent }}<span class="text-sm text-gray-400 font-normal"> / {{ pupilsMarked }} marked</span>
                </div>
            </div>
            <div class="card" v-if="isAdmin">
                <div class="text-xs text-gray-500">Facilitators in today</div>
                <div class="text-2xl font-bold text-gray-900">{{ teachersIn }} / {{ teacherCount }}</div>
                <div class="text-xs text-gray-400" v-if="teachersToday?.late">{{ teachersToday.late }} late</div>
            </div>
            <div class="card">
                <div class="text-xs text-gray-500">Course plan coverage (term {{ term }})</div>
                <div class="text-2xl font-bold text-gray-900">{{ overallCoverage === null ? '—' : overallCoverage + '%' }}</div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Today's registers -->
            <div class="card p-0 overflow-hidden">
                <div class="flex justify-between px-4 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Today's registers</h3>
                    <Link href="/courses/register" class="text-sm text-brand-600 hover:underline">Take register →</Link>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="table-th">Course</th>
                            <th class="table-th">Lead facilitator</th>
                            <th class="table-th text-center">Present</th>
                            <th class="table-th text-center">Absent</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="r in registers" :key="r.id">
                            <td class="table-td font-medium">
                                <Link :href="`/courses/register?class_id=${r.id}`" class="hover:underline">{{ r.name }}</Link>
                            </td>
                            <td class="table-td text-sm text-gray-500">{{ r.class_teacher ?? '—' }}</td>
                            <template v-if="r.marked">
                                <td class="table-td text-center">{{ r.present }} / {{ r.students }}</td>
                                <td class="table-td text-center" :class="{ 'text-red-600 font-semibold': r.absent }">{{ r.absent }}</td>
                            </template>
                            <td v-else colspan="2" class="table-td text-center">
                                <span class="badge bg-amber-100 text-amber-700">Not taken</span>
                            </td>
                        </tr>
                        <tr v-if="!registers.length">
                            <td colspan="4" class="table-td text-center text-gray-400 py-8">No courses.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Facilitator progress -->
            <div class="card p-0 overflow-hidden">
                <div class="flex justify-between px-4 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Facilitator progress — course plan</h3>
                    <Link href="/courses/topics" class="text-sm text-brand-600 hover:underline">Topics →</Link>
                </div>
                <div class="divide-y divide-gray-50">
                    <Link v-for="c in coverage" :key="`${c.teacher}-${c.class_id}-${c.subject_id}`"
                        :href="`/courses/topics?class_id=${c.class_id}&subject_id=${c.subject_id}&year=${year}&term=${term}`"
                        class="block px-4 py-3 hover:bg-gray-50">
                        <div class="flex justify-between text-sm mb-1">
                            <span><span class="font-medium">{{ c.teacher }}</span> · {{ c.class }} · {{ c.subject }}</span>
                            <span class="text-gray-500">
                                {{ c.completed }}/{{ c.planned }}
                                <span v-if="c.overdue" class="text-red-600">· {{ c.overdue }} behind</span>
                            </span>
                        </div>
                        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full" :class="barColour(c.percent)" :style="{ width: c.percent + '%' }" />
                        </div>
                    </Link>
                    <p v-if="!coverage.length" class="px-4 py-8 text-center text-sm text-gray-400">
                        No topics planned for term {{ term }} yet.
                    </p>
                </div>
            </div>

            <!-- Facilitator attendance this term -->
            <div class="card p-0 overflow-hidden">
                <div class="flex justify-between px-4 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Facilitator attendance — term {{ term }}</h3>
                    <Link href="/courses/facilitator-attendance" class="text-sm text-brand-600 hover:underline">Daily sheet →</Link>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="table-th">Facilitator</th>
                            <th class="table-th text-center">Days</th>
                            <th class="table-th text-center">Late</th>
                            <th class="table-th text-center">Absent</th>
                            <th class="table-th text-center">Leave</th>
                            <th class="table-th text-center">Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="t in teacherAttendance" :key="t.teacher">
                            <td class="table-td font-medium">{{ t.teacher }}</td>
                            <td class="table-td text-center">{{ t.days }}</td>
                            <td class="table-td text-center">{{ t.late }}</td>
                            <td class="table-td text-center" :class="{ 'text-red-600 font-semibold': t.absent }">{{ t.absent }}</td>
                            <td class="table-td text-center">{{ t.leave }}</td>
                            <td class="table-td text-center">{{ rate(t) }}%</td>
                        </tr>
                        <tr v-if="!teacherAttendance.length">
                            <td colspan="6" class="table-td text-center text-gray-400 py-8">No facilitator attendance recorded this term.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Participants with low attendance -->
            <div class="card p-0 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Participants below 80% attendance</h3>
                </div>
                <table class="w-full">
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="s in atRisk" :key="s.id">
                            <td class="table-td">
                                <Link :href="`/courses/participants/${s.id}`" class="font-medium hover:underline">{{ s.name }}</Link>
                                <span class="text-xs text-gray-400 block">{{ s.class }}</span>
                            </td>
                            <td class="table-td text-right">
                                <span class="text-red-600 font-semibold">{{ s.rate }}%</span>
                                <span class="text-xs text-gray-400 block">of {{ s.days }} days</span>
                            </td>
                        </tr>
                        <tr v-if="!atRisk.length">
                            <td colspan="2" class="table-td text-center text-gray-400 py-8">No participants below 80% this term.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Course averages -->
            <div class="card p-0 overflow-hidden lg:col-span-2">
                <div class="flex justify-between px-4 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">Course averages — term {{ term }}</h3>
                    <Link href="/courses/results" class="text-sm text-brand-600 hover:underline">Enter marks →</Link>
                </div>
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="table-th">Course</th>
                            <th class="table-th">Module</th>
                            <th class="table-th text-center">Participants marked</th>
                            <th class="table-th">Average</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="a in averages" :key="`${a.class}-${a.subject}`">
                            <td class="table-td font-medium">{{ a.class }}</td>
                            <td class="table-td">{{ a.subject }}</td>
                            <td class="table-td text-center">{{ a.students }}</td>
                            <td class="table-td w-1/3">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full" :class="barColour(a.average)" :style="{ width: a.average + '%' }" />
                                    </div>
                                    <span class="text-sm w-12 text-right" :class="{ 'text-red-600 font-semibold': a.average < 50 }">{{ a.average }}%</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!averages.length">
                            <td colspan="4" class="table-td text-center text-gray-400 py-8">No marks entered for term {{ term }} yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
