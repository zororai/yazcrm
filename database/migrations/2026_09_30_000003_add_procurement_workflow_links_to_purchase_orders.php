<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Stage 9 traceability — links a PO back to the requisition/vendor
            // selection it was issued from (nullable: POs can still be raised
            // standalone, outside this workflow).
            $table->foreignId('requisition_id')->nullable()->after('id')->constrained('procurement_requisitions')->nullOnDelete();
            $table->foreignId('bid_process_id')->nullable()->after('requisition_id')->constrained('procurement_bid_processes')->nullOnDelete();

            // Stage 10 — service/goods delivery + invoice confirmation. Goods
            // POs already track receipt quantities via stock receipts; this
            // covers service POs (no store) and the invoice paperwork for both.
            $table->foreignId('delivery_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->string('invoice_reference')->nullable();
            $table->date('invoice_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requisition_id');
            $table->dropConstrainedForeignId('bid_process_id');
            $table->dropConstrainedForeignId('delivery_confirmed_by');
            $table->dropColumn(['delivered_at', 'delivery_notes', 'invoice_reference', 'invoice_date']);
        });
    }
};
