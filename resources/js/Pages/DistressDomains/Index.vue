<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ChevronRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ sections: Array });

const palette = [
    { bg: 'bg-indigo-50',  text: 'text-indigo-700',  border: 'hover:border-indigo-200'  },
    { bg: 'bg-green-50',   text: 'text-green-700',   border: 'hover:border-green-200'   },
    { bg: 'bg-purple-50',  text: 'text-purple-700',  border: 'hover:border-purple-200'  },
    { bg: 'bg-pink-50',    text: 'text-pink-700',    border: 'hover:border-pink-200'    },
    { bg: 'bg-orange-50',  text: 'text-orange-700',  border: 'hover:border-orange-200'  },
    { bg: 'bg-yellow-50',  text: 'text-yellow-700',  border: 'hover:border-yellow-200'  },
    { bg: 'bg-teal-50',    text: 'text-teal-700',    border: 'hover:border-teal-200'    },
    { bg: 'bg-rose-50',    text: 'text-rose-700',    border: 'hover:border-rose-200'    },
];
</script>

<template>
    <AppLayout>
        <template #title>Lookup Settings</template>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link
                v-for="(section, i) in sections"
                :key="section.type"
                :href="`/distress-domains/section/${section.type}`"
                class="card p-5 border border-transparent transition-all duration-150 cursor-pointer group"
                :class="palette[i % palette.length].border"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold mb-2',
                            palette[i % palette.length].bg,
                            palette[i % palette.length].text]">
                            {{ section.count }} {{ section.count === 1 ? 'item' : 'items' }}
                        </span>
                        <h3 class="text-sm font-semibold text-gray-900 group-hover:text-brand-600 transition-colors leading-tight">
                            {{ section.label }}
                        </h3>
                    </div>
                    <ChevronRightIcon class="h-5 w-5 text-gray-300 group-hover:text-brand-500 transition-colors flex-shrink-0 mt-1" />
                </div>
            </Link>
        </div>
    </AppLayout>
</template>
