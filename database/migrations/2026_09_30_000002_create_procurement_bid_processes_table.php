<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_bid_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->unique()->constrained('procurement_requisitions')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            // request_for_quotes | sole_source | public_invitation
            $table->string('bid_method')->nullable();
            $table->string('bid_reference')->nullable();
            // draft -> bids_requested (4) -> evaluated (5) -> hop_reviewed (6)
            // -> hof_reviewed (7) -> approved|rejected (8)
            $table->string('status')->default('draft');

            $table->foreignId('recommended_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->text('evaluation_notes')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evaluated_at')->nullable();

            $table->foreignId('hop_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hop_reviewed_at')->nullable();
            $table->text('hop_notes')->nullable();

            $table->foreignId('hof_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hof_reviewed_at')->nullable();
            $table->text('hof_notes')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('procurement_bid_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_process_id')->constrained('procurement_bid_processes')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('quoted_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_recommended')->default(false);
            $table->timestamps();
        });

        Schema::create('procurement_bid_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_process_id')->constrained('procurement_bid_processes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_bid_activity_logs');
        Schema::dropIfExists('procurement_bid_quotes');
        Schema::dropIfExists('procurement_bid_processes');
    }
};
