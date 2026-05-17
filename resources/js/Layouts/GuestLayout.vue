<script setup>
import { PhoneIcon } from '@heroicons/vue/24/outline';
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-900 p-4">
        <div class="w-full max-w-sm">
            <div class="flex justify-center mb-8">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600">
                        <PhoneIcon class="h-6 w-6 text-white" />
                    </div>
                    <span class="text-white font-bold text-2xl">CRM PBX</span>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <slot />
            </div>
        </div>
    </div>
</template>
