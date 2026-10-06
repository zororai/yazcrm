<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_number')->nullable()->unique();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('justification')->nullable();
            $table->decimal('estimated_total', 12, 2)->default(0);
            $table->string('currency')->default('USD');
            $table->date('required_by_date')->nullable();
            // draft -> pending_review (Head of Programs) -> pending_approval (Executive Director) -> approved|rejected
            $table->string('status')->default('draft');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('procurement_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('estimated_unit_cost', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('procurement_req_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('procurement_requisitions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_req_activity_logs');
        Schema::dropIfExists('procurement_requisition_items');
        Schema::dropIfExists('procurement_requisitions');
    }
};
