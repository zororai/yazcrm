<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_payment_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->nullable()->unique();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prepared_by')->constrained('users')->cascadeOnDelete();

            $table->string('payee_name');
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('USD');
            $table->string('payment_method')->nullable(); // bank_transfer|cheque|mobile_money|cash
            $table->text('description')->nullable();

            // draft -> pending_review (11: IO prepares) -> pending_approval (HoF reviews)
            // -> approved (ED approves) -> loaded (12: HoF loads to bank)
            // -> released (13: ED/Board authorizes+releases) -> recorded (14: Finance Officer)
            $table->string('status')->default('draft');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            $table->foreignId('loaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('loaded_at')->nullable();
            $table->string('bank_reference')->nullable();
            $table->text('loading_notes')->nullable();

            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->text('release_notes')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->string('recording_reference')->nullable();
            $table->text('recording_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('procurement_payment_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('procurement_payment_requisitions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_payment_activity_logs');
        Schema::dropIfExists('procurement_payment_requisitions');
    }
};
