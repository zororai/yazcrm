<?php

namespace App\Services;

use App\Models\ProcurementBidActivityLog;
use App\Models\ProcurementBidProcess;
use App\Models\ProcurementRequisition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// §2 Vendor Selection — stages 4-8 of the Procurement & Payment Process, run
// against an already-approved requisition:
// (4) Request for Bids, (5) Evaluation & Selection, (6) Head of Programs
// review, (7) Head of Finance review + documentation, (8) ED/Board approval.
class ProcurementBidService
{
    public function start(ProcurementRequisition $requisition, User $actor, string $bidMethod, ?string $bidReference, array $quoteLines = []): ProcurementBidProcess
    {
        if ($requisition->status !== 'approved') {
            throw new RuntimeException('The requisition must be approved before starting vendor selection.');
        }

        if ($requisition->bidProcess()->exists()) {
            throw new RuntimeException('A vendor selection process already exists for this requisition.');
        }

        return DB::transaction(function () use ($requisition, $actor, $bidMethod, $bidReference, $quoteLines) {
            $bid = ProcurementBidProcess::create([
                'requisition_id' => $requisition->id,
                'initiated_by'   => $actor->id,
                'bid_method'     => $bidMethod,
                'bid_reference'  => $bidReference,
                'status'         => 'bids_requested',
            ]);

            foreach ($quoteLines as $line) {
                $bid->quotes()->create([
                    'supplier_id'    => $line['supplier_id'],
                    'quoted_amount'  => $line['quoted_amount'] ?? null,
                    'notes'          => $line['notes'] ?? null,
                ]);
            }

            $this->log($actor, 'bids_requested', $bid, $bidReference);

            return $bid;
        });
    }

    public function evaluate(ProcurementBidProcess $bid, User $actor, int $recommendedSupplierId, string $notes): ProcurementBidProcess
    {
        return DB::transaction(function () use ($bid, $actor, $recommendedSupplierId, $notes) {
            $this->assertStatus($bid, 'bids_requested');

            $bid->quotes()->update(['is_recommended' => false]);
            $bid->quotes()->where('supplier_id', $recommendedSupplierId)->update(['is_recommended' => true]);

            $bid->update([
                'status'                   => 'evaluated',
                'recommended_supplier_id'  => $recommendedSupplierId,
                'evaluation_notes'         => $notes,
                'evaluated_by'             => $actor->id,
                'evaluated_at'             => now(),
            ]);
            $this->log($actor, 'bid_evaluated', $bid, $notes);

            return $bid;
        });
    }

    public function hopReview(ProcurementBidProcess $bid, User $actor, ?string $notes = null): ProcurementBidProcess
    {
        return DB::transaction(function () use ($bid, $actor, $notes) {
            $this->assertStatus($bid, 'evaluated');
            $bid->update([
                'status'           => 'hop_reviewed',
                'hop_reviewed_by'  => $actor->id,
                'hop_reviewed_at'  => now(),
                'hop_notes'        => $notes,
            ]);
            $this->log($actor, 'hop_reviewed', $bid, $notes);

            return $bid;
        });
    }

    public function hofReview(ProcurementBidProcess $bid, User $actor, ?string $notes = null): ProcurementBidProcess
    {
        return DB::transaction(function () use ($bid, $actor, $notes) {
            $this->assertStatus($bid, 'hop_reviewed');
            $bid->update([
                'status'           => 'hof_reviewed',
                'hof_reviewed_by'  => $actor->id,
                'hof_reviewed_at'  => now(),
                'hof_notes'        => $notes,
            ]);
            $this->log($actor, 'hof_reviewed', $bid, $notes);

            return $bid;
        });
    }

    public function approve(ProcurementBidProcess $bid, User $actor, ?string $notes = null): ProcurementBidProcess
    {
        return DB::transaction(function () use ($bid, $actor, $notes) {
            $this->assertStatus($bid, 'hof_reviewed');
            $bid->update([
                'status'          => 'approved',
                'approved_by'     => $actor->id,
                'approved_at'     => now(),
                'approval_notes'  => $notes,
            ]);
            $this->log($actor, 'vendor_approved', $bid, $notes);

            return $bid;
        });
    }

    public function reject(ProcurementBidProcess $bid, User $actor, string $reason): ProcurementBidProcess
    {
        return DB::transaction(function () use ($bid, $actor, $reason) {
            if (in_array($bid->status, ['approved', 'rejected', 'cancelled'], true)) {
                throw new RuntimeException("A vendor selection in status '{$bid->status}' cannot be rejected.");
            }
            $bid->update(['status' => 'rejected']);
            $this->log($actor, 'vendor_selection_rejected', $bid, $reason);

            return $bid;
        });
    }

    public function cancel(ProcurementBidProcess $bid, User $actor, string $reason): ProcurementBidProcess
    {
        if (in_array($bid->status, ['approved', 'rejected', 'cancelled'], true)) {
            throw new RuntimeException("A vendor selection in status '{$bid->status}' cannot be cancelled.");
        }

        return DB::transaction(function () use ($bid, $actor, $reason) {
            $bid->update(['status' => 'cancelled']);
            $this->log($actor, 'vendor_selection_cancelled', $bid, $reason);

            return $bid;
        });
    }

    private function assertStatus(ProcurementBidProcess $bid, string $expected): void
    {
        if ($bid->status !== $expected) {
            throw new RuntimeException("This vendor selection must be '{$expected}' for that action (currently '{$bid->status}').");
        }
    }

    private function log(User $actor, string $action, ProcurementBidProcess $bid, ?string $notes = null): void
    {
        ProcurementBidActivityLog::create([
            'bid_process_id' => $bid->id,
            'user_id'        => $actor->id,
            'action'         => $action,
            'notes'          => $notes,
        ]);
    }
}
