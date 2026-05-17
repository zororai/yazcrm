<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeftIcon, ArrowUpTrayIcon, DocumentArrowDownIcon } from '@heroicons/vue/24/outline';

const form = useForm({ file: null });
const fileName = ref('');

function pickFile(e) {
    const f = e.target.files[0];
    if (!f) return;
    form.file = f;
    fileName.value = f.name;
}

function submit() {
    form.post('/tickets/import', {
        forceFormData: true,
    });
}

const columns = [
    'Created on', 'Call Source', 'Call Destination', 'Name of Caller',
    'Mode of Communication', 'Call Validity', 'Purpose of Call',
    'Immediate Action Required', 'Age', 'Gender', 'Marital Status',
    'Key Pops', 'Province', 'District', 'Location', 'New/Repeat',
    'Project', 'Services Requested', 'Second Service Requested',
    'No. of Services', 'Referred to', 'Confirming Uptake of Services',
    'Status', 'Conselloer\'s Notes', 'Date Resolved',
];
</script>

<template>
    <AppLayout>
        <template #title>Import Old CRM Records</template>

        <div class="max-w-2xl space-y-6">
            <Link href="/tickets" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                <ArrowLeftIcon class="h-4 w-4" /> Back to Tickets
            </Link>

            <!-- Upload card -->
            <div class="card space-y-5">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Upload Excel / CSV File</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Upload the old CRM export (.xlsx, .xls, or .csv). The first row must be the header row.
                        Existing records are not affected — only new rows are added.
                    </p>
                </div>

                <!-- Drop zone -->
                <label
                    class="flex flex-col items-center justify-center gap-3 border-2 border-dashed border-gray-300 rounded-xl p-10 cursor-pointer hover:border-brand-400 hover:bg-brand-50 transition-colors"
                    :class="{ 'border-brand-500 bg-brand-50': fileName }"
                >
                    <DocumentArrowDownIcon class="h-10 w-10 text-gray-400" />
                    <div class="text-center">
                        <p class="text-sm font-medium text-gray-700">
                            {{ fileName || 'Click to choose a file' }}
                        </p>
                        <p class="text-xs text-gray-400 mt-1">Supports .xlsx, .xls, .csv — max 10 MB</p>
                    </div>
                    <input type="file" class="sr-only" accept=".xlsx,.xls,.csv" @change="pickFile" />
                </label>

                <p v-if="form.errors.file" class="text-sm text-red-600">{{ form.errors.file }}</p>

                <button
                    type="button"
                    @click="submit"
                    :disabled="!form.file || form.processing"
                    class="btn-primary w-full justify-center"
                >
                    <ArrowUpTrayIcon class="h-4 w-4" />
                    {{ form.processing ? 'Importing…' : 'Import Records' }}
                </button>
            </div>

            <!-- Column reference -->
            <div class="card">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">Expected Columns</h3>
                <p class="text-xs text-gray-500 mb-3">
                    Columns are matched by header name (case-insensitive). Unrecognised columns are ignored.
                </p>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="col in columns" :key="col"
                        class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs font-mono"
                    >{{ col }}</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
