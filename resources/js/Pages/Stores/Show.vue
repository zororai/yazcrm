<script setup>
defineProps({ store: Object, stock: Array, isManager: Boolean });
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <AppLayout>
        <template #title>{{ store.name }}</template>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="card text-sm text-gray-600 space-y-1">
                <p><span class="text-gray-400">Code:</span> {{ store.code }}</p>
                <p><span class="text-gray-400">Location:</span> {{ store.location?.name }}</p>
                <p><span class="text-gray-400">Manager:</span> {{ store.manager?.name ?? '—' }}</p>
                <p><span class="text-gray-400">Storekeeper:</span> {{ store.storekeeper?.name ?? '—' }}</p>
            </div>
            <div class="card text-sm text-gray-600">
                <p>{{ store.description || 'No description.' }}</p>
            </div>
        </div>

        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Item</th>
                        <th class="table-th">Unit</th>
                        <th class="table-th">Quantity</th>
                        <th class="table-th">Reserved</th>
                        <th class="table-th">Available</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in stock" :key="s.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium">{{ s.item?.name }} <span class="text-xs text-gray-400">({{ s.item?.item_code }})</span></td>
                        <td class="table-td">{{ s.item?.unit_of_measure ?? '—' }}</td>
                        <td class="table-td">{{ s.quantity }}</td>
                        <td class="table-td">{{ s.reserved_quantity }}</td>
                        <td class="table-td font-medium">{{ s.available_quantity }}</td>
                    </tr>
                    <tr v-if="!stock.length">
                        <td colspan="5" class="table-td text-center text-gray-400 py-8">No stock recorded for this store yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
