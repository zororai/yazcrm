<?php

namespace App\Services;

use App\Models\ProcurementPaymentActivityLog;
use App\Models\ProcurementPaymentRequisition;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// §3 Payment Requisition — stages 11-14 of the Procurement & Payment Process:
// (11) IO prepares → Head of Finance reviews → Executive Director approves,
// (12) Head of Finance loads the payment into the banking system,
// (13) Executive Director/Board authorizes and releases it,
// (14) Finance Officer records it.
class ProcurementPaymentService
{
    public function create(User $actor, PurchaseOrder $po, array $attributes): ProcurementPaymentRequisition
    {
        return DB::transaction(function () use ($actor, $po, $attributes) {
            $payment = ProcurementPaymentRequisition::create($attributes + [
                'purchase_order_id' => $po->id,
                'prepared_by'       => $actor->id,
                'status'            => 'draft',
            ]);
            $payment->update(['payment_number' => 'PAY-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)]);

            $this->log($actor, 'payment_created', $payment);

            return $payment;
        });
    }

    public function submitForReview(ProcurementPaymentRequisition $payment, User $actor): ProcurementPaymentRequisition
    {
        return $this->transition($payment, $actor, 'draft', 'pending_review', 'payment_submitted');
    }

    public function review(ProcurementPaymentRequisition $payment, User $actor, ?string $notes = null): ProcurementPaymentRequisition
    {
        return DB::transaction(function () use ($payment, $actor, $notes) {
            $this->assertStatus($payment, 'pending_review');
            $payment->update([
                'status' => 'pending_approval', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_notes' => $notes,
            ]);
            $this->log($actor, 'payment_reviewed', $payment, $notes);

            return $payment;
        });
    }

    public function rejectAtReview(ProcurementPaymentRequisition $payment, User $actor, string $reason): ProcurementPaymentRequisition
    {
        return DB::transaction(function () use ($payment, $actor, $reason) {
            $this->assertStatus($payment, 'pending_review');
            $payment->update(['status' => 'rejected', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_notes' => $reason]);
            $this->log($actor, 'payment_rejected_at_review', $payment, $reason);

            return $payment;
        });
    }

    public function approve(ProcurementPaymentRequisition $payment, User $actor, ?string $notes = null): ProcurementPaymentRequisition
    {
        return DB::transaction(function () use ($payment, $actor, $notes) {
            $this->assertStatus($payment, 'pending_approval');
            $payment->update([
                'status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'approval_notes' => $notes,
            ]);
            $this->log($actor, 'payment_approved', $payment, $notes);

            return $payment;
        });
    }

    public function rejectAtApproval(ProcurementPaymentRequisition $payment, User $actor, string $reason): ProcurementPaymentRequisition
    {
        return DB::transaction(function () use ($payment, $actor, $reason) {
            $this->assertStatus($payment, 'pending_approval');
            $payment->update(['status' => 'rejected', 'approved_by' => $actor->id, 'approved_at' => now(), 'approval_notes' => $reason]);
            $this->log($actor, 'payment_rejected_at_approval', $payment, $reason);

            return $payment;
        });
    }

    // Stage 12 — Head of Finance loads the approved payment into the bank system.
    public function loadToBank(ProcurementPaymentRequisition $payment, User $actor, string $bankReference, ?string $notes = null): ProcurementPaymentRequisition
    {
        return DB::transaction(function () use ($payment, $actor, $bankReference, $notes) {
            $this->assertStatus($payment, 'approved');
            $payment->update([
                'status' => 'loaded', 'loaded_by' => $actor->id, 'loaded_at' => now(),
                'bank_reference' => $bankReference, 'loading_notes' => $notes,
            ]);
            $this->log($actor, 'payment_loaded', $payment, $notes);

            return $payment;
        });
    }

    // Stage 13 — Executive Director / Board authorizes and releases the payment.
    public function release(ProcurementPaymentRequisition $payment, User $actor, ?string $notes = null): ProcurementPaymentRequisition
    {
        return DB::transaction(function () use ($payment, $actor, $notes) {
            $this->assertStatus($payment, 'loaded');
            $payment->update([
                'status' => 'released', 'released_by' => $actor->id, 'released_at' => now(), 'release_notes' => $notes,
            ]);
            $this->log($actor, 'payment_released', $payment, $notes);

            return $payment;
        });
    }

    // Stage 14 — Finance Officer records the completed payment. Terminal state.
    public function record(ProcurementPaymentRequisition $payment, User $actor, ?string $reference = null, ?string $notes = null): ProcurementPaymentRequisition
    {
        return DB::transaction(function () use ($payment, $actor, $reference, $notes) {
            $this->assertStatus($payment, 'released');
            $payment->update([
                'status' => 'recorded', 'recorded_by' => $actor->id, 'recorded_at' => now(),
                'recording_reference' => $reference, 'recording_notes' => $notes,
            ]);
            $this->log($actor, 'payment_recorded', $payment, $notes);

            return $payment;
        });
    }

    public function cancel(ProcurementPaymentRequisition $payment, User $actor, string $reason): ProcurementPaymentRequisition
    {
        if (in_array($payment->status, ['released', 'recorded', 'rejected', 'cancelled'], true)) {
            throw new RuntimeException("A payment requisition in status '{$payment->status}' cannot be cancelled.");
        }

        return DB::transaction(function () use ($payment, $actor, $reason) {
            $payment->update(['status' => 'cancelled']);
            $this->log($actor, 'payment_cancelled', $payment, $reason);

            return $payment;
        });
    }

    private function transition(ProcurementPaymentRequisition $payment, User $actor, string $from, string $to, string $action): ProcurementPaymentRequisition
    {
        return DB::transaction(function () use ($payment, $actor, $from, $to, $action) {
            $this->assertStatus($payment, $from);
            $payment->update(['status' => $to]);
            $this->log($actor, $action, $payment);

            return $payment;
        });
    }

    private function assertStatus(ProcurementPaymentRequisition $payment, string $expected): void
    {
        if ($payment->status !== $expected) {
            throw new RuntimeException("This payment requisition must be '{$expected}' for that action (currently '{$payment->status}').");
        }
    }

    private function log(User $actor, string $action, ProcurementPaymentRequisition $payment, ?string $notes = null): void
    {
        ProcurementPaymentActivityLog::create([
            'payment_id' => $payment->id,
            'user_id'    => $actor->id,
            'action'     => $action,
            'notes'      => $notes,
        ]);
    }
}
