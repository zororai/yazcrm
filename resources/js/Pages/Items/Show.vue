<script setup>
defineProps({ item: Object, stock: Array, isManager: Boolean });
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <AppLayout>
        <template #title>{{ item.name }}</template>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="card text-sm text-gray-600 space-y-1">
                <p><span class="text-gray-400">Item Code:</span> {{ item.item_code }}</p>
                <p><span class="text-gray-400">Category:</span> {{ item.category?.name ?? '—' }}</p>
                <p><span class="text-gray-400">Unit:</span> {{ item.unit_of_measure ?? '—' }}</p>
                <p><span class="text-gray-400">Default Store:</span> {{ item.default_store?.name ?? '—' }}</p>
            </div>
            <div class="card text-sm text-gray-600 space-y-1">
                <p><span class="text-gray-400">Minimum Stock:</span> {{ item.minimum_stock }}</p>
                <p><span class="text-gray-400">Maximum Stock:</span> {{ item.maximum_stock ?? '—' }}</p>
                <p><span class="text-gray-400">Reorder Level:</span> {{ item.reorder_level }}</p>
                <p>{{ item.description }}</p>
            </div>
        </div>

        <h3 class="font-semibold text-gray-900 mb-2">Stock by Store</h3>
        <div class="card p-0 overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="table-th">Store</th>
                        <th class="table-th">Quantity</th>
                        <th class="table-th">Reserved</th>
                        <th class="table-th">Available</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="s in stock" :key="s.id" class="hover:bg-gray-50">
                        <td class="table-td font-medium">{{ s.store?.name }}</td>
                        <td class="table-td">{{ s.quantity }}</td>
                        <td class="table-td">{{ s.reserved_quantity }}</td>
                        <td class="table-td font-medium">{{ s.available_quantity }}</td>
                    </tr>
                    <tr v-if="!stock.length">
                        <td colspan="4" class="table-td text-center text-gray-400 py-8">Not stocked in any store yet.</td>
                    </tr>
                </tbody>
                <tfoot v-if="stock.length" class="bg-gray-50 border-t border-gray-100">
                    <tr>
                        <td class="table-td font-medium">Total</td>
                        <td class="table-td font-medium">{{ stock.reduce((sum, s) => sum + s.quantity, 0) }}</td>
                        <td class="table-td font-medium">{{ stock.reduce((sum, s) => sum + s.reserved_quantity, 0) }}</td>
                        <td class="table-td font-medium">{{ stock.reduce((sum, s) => sum + s.available_quantity, 0) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </AppLayout>
</template>
