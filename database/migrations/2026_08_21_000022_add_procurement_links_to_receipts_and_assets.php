<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->after('store_id')
                ->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('supplier_name')
                ->constrained('suppliers')->nullOnDelete();
        });

        Schema::table('stock_receipt_items', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')->nullable()->after('item_id')
                ->constrained('purchase_order_items')->nullOnDelete();
        });

        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('supplier_name')
                ->constrained('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::table('stock_receipt_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_item_id');
        });

        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
