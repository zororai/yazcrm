<?php

namespace App\Services;

use App\Models\ProcurementRequisition;
use App\Models\ProcurementRequisitionActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// §1 Procurement Requisition — stages 1-3 of the Procurement & Payment Process:
// (1) Budget holder raises the requisition, (2) Head of Programs reviews it,
// (3) Executive Director approves it. Either reviewer can reject.
class ProcurementRequisitionService
{
    public function create(User $actor, array $attributes, array $lines): ProcurementRequisition
    {
        return DB::transaction(function () use ($actor, $attributes, $lines) {
            [$estimatedTotal, $preparedLines] = $this->prepareLines($lines);

            $requisition = ProcurementRequisition::create($attributes + [
                'requested_by'     => $actor->id,
                'status'           => 'draft',
                'estimated_total'  => $estimatedTotal,
            ]);
            $requisition->update(['requisition_number' => 'REQ-'.str_pad((string) $requisition->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($preparedLines as $line) {
                $requisition->items()->create($line);
            }

            $this->log($actor, 'requisition_created', $requisition);

            return $requisition;
        });
    }

    public function submitForReview(ProcurementRequisition $requisition, User $actor): ProcurementRequisition
    {
        return $this->transition($requisition, $actor, 'draft', 'pending_review', 'requisition_submitted');
    }

    public function review(ProcurementRequisition $requisition, User $actor, ?string $notes = null): ProcurementRequisition
    {
        return DB::transaction(function () use ($requisition, $actor, $notes) {
            $this->assertStatus($requisition, 'pending_review');
            $requisition->update([
                'status'        => 'pending_approval',
                'reviewed_by'   => $actor->id,
                'reviewed_at'   => now(),
                'review_notes'  => $notes,
            ]);
            $this->log($actor, 'requisition_reviewed', $requisition, $notes);

            return $requisition;
        });
    }

    public function rejectAtReview(ProcurementRequisition $requisition, User $actor, string $reason): ProcurementRequisition
    {
        return DB::transaction(function () use ($requisition, $actor, $reason) {
            $this->assertStatus($requisition, 'pending_review');
            $requisition->update([
                'status'        => 'rejected',
                'reviewed_by'   => $actor->id,
                'reviewed_at'   => now(),
                'review_notes'  => $reason,
            ]);
            $this->log($actor, 'requisition_rejected_at_review', $requisition, $reason);

            return $requisition;
        });
    }

    public function approve(ProcurementRequisition $requisition, User $actor, ?string $notes = null): ProcurementRequisition
    {
        return DB::transaction(function () use ($requisition, $actor, $notes) {
            $this->assertStatus($requisition, 'pending_approval');
            $requisition->update([
                'status'          => 'approved',
                'approved_by'     => $actor->id,
                'approved_at'     => now(),
                'approval_notes'  => $notes,
            ]);
            $this->log($actor, 'requisition_approved', $requisition, $notes);

            return $requisition;
        });
    }

    public function rejectAtApproval(ProcurementRequisition $requisition, User $actor, string $reason): ProcurementRequisition
    {
        return DB::transaction(function () use ($requisition, $actor, $reason) {
            $this->assertStatus($requisition, 'pending_approval');
            $requisition->update([
                'status'          => 'rejected',
                'approved_by'     => $actor->id,
                'approved_at'     => now(),
                'approval_notes'  => $reason,
            ]);
            $this->log($actor, 'requisition_rejected_at_approval', $requisition, $reason);

            return $requisition;
        });
    }

    public function cancel(ProcurementRequisition $requisition, User $actor, string $reason): ProcurementRequisition
    {
        if (in_array($requisition->status, ['approved', 'rejected', 'cancelled'], true)) {
            throw new RuntimeException("A requisition in status '{$requisition->status}' cannot be cancelled.");
        }

        return DB::transaction(function () use ($requisition, $actor, $reason) {
            $requisition->update(['status' => 'cancelled']);
            $this->log($actor, 'requisition_cancelled', $requisition, $reason);

            return $requisition;
        });
    }

    private function transition(ProcurementRequisition $requisition, User $actor, string $from, string $to, string $action): ProcurementRequisition
    {
        return DB::transaction(function () use ($requisition, $actor, $from, $to, $action) {
            $this->assertStatus($requisition, $from);
            $requisition->update(['status' => $to]);
            $this->log($actor, $action, $requisition);

            return $requisition;
        });
    }

    private function assertStatus(ProcurementRequisition $requisition, string $expected): void
    {
        if ($requisition->status !== $expected) {
            throw new RuntimeException("This requisition must be '{$expected}' for that action (currently '{$requisition->status}').");
        }
    }

    private function prepareLines(array $lines): array
    {
        $total = 0;
        $prepared = [];

        foreach ($lines as $line) {
            $qty = (int) $line['quantity'];
            $cost = (float) $line['estimated_unit_cost'];
            $lineTotal = $qty * $cost;
            $total += $lineTotal;

            $prepared[] = [
                'item_id'              => $line['item_id'] ?? null,
                'description'          => $line['description'] ?? null,
                'quantity'             => $qty,
                'estimated_unit_cost'  => $cost,
                'line_total'           => $lineTotal,
            ];
        }

        return [$total, $prepared];
    }

    private function log(User $actor, string $action, ProcurementRequisition $requisition, ?string $notes = null): void
    {
        ProcurementRequisitionActivityLog::create([
            'requisition_id' => $requisition->id,
            'user_id'        => $actor->id,
            'action'         => $action,
            'notes'          => $notes,
        ]);
    }
}
