<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\StockActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseOrderService
{
    public function create(User $actor, array $attributes, array $lines): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $attributes, $lines) {
            [$subtotal, $preparedLines] = $this->prepareLines($lines);
            $tax = (float) ($attributes['tax'] ?? 0);

            $po = PurchaseOrder::create($attributes + [
                'requested_by' => $actor->id,
                'status'       => 'draft',
                'subtotal'     => $subtotal,
                'tax'          => $tax,
                'total'        => $subtotal + $tax,
            ]);
            $po->update(['po_number' => 'PO-'.str_pad((string) $po->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($preparedLines as $line) {
                $po->items()->create($line);
            }

            $this->log($actor, 'po_created', $po);

            return $po;
        });
    }

    public function submitForApproval(PurchaseOrder $po, User $actor): PurchaseOrder
    {
        return $this->transition($po, $actor, 'draft', 'pending_approval', 'po_submitted');
    }

    public function approve(PurchaseOrder $po, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $actor) {
            $this->assertStatus($po, 'pending_approval');
            $po->update(['status' => 'approved', 'approved_by' => $actor->id]);
            $this->log($actor, 'po_approved', $po);

            return $po;
        });
    }

    public function reject(PurchaseOrder $po, User $actor, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $actor, $reason) {
            $this->assertStatus($po, 'pending_approval');
            $po->update(['status' => 'rejected']);
            $this->log($actor, 'po_rejected', $po, $reason);

            return $po;
        });
    }

    public function markSent(PurchaseOrder $po, User $actor): PurchaseOrder
    {
        return $this->transition($po, $actor, 'approved', 'sent', 'po_sent');
    }

    public function cancel(PurchaseOrder $po, User $actor, string $reason): PurchaseOrder
    {
        if (in_array($po->status, ['received', 'cancelled'], true)) {
            throw new RuntimeException("A purchase order in status '{$po->status}' cannot be cancelled.");
        }

        return DB::transaction(function () use ($po, $actor, $reason) {
            $po->update(['status' => 'cancelled']);
            $this->log($actor, 'po_cancelled', $po, $reason);

            return $po;
        });
    }

    // Called after a GRN posts against this PO — recomputes fulfillment
    // status from each line's quantity_received vs quantity ordered.
    public function syncFulfillment(PurchaseOrder $po): PurchaseOrder
    {
        $items = $po->items()->get();
        $fullyReceived = $items->every(fn ($i) => $i->quantity_received >= $i->quantity);
        $anyReceived = $items->contains(fn ($i) => $i->quantity_received > 0);

        $status = $fullyReceived ? 'received' : ($anyReceived ? 'partially_received' : $po->status);

        if ($status !== $po->status) {
            $po->update(['status' => $status]);
        }

        return $po;
    }

    private function transition(PurchaseOrder $po, User $actor, string $from, string $to, string $action): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $actor, $from, $to, $action) {
            $this->assertStatus($po, $from);
            $po->update(['status' => $to]);
            $this->log($actor, $action, $po);

            return $po;
        });
    }

    private function assertStatus(PurchaseOrder $po, string $expected): void
    {
        if ($po->status !== $expected) {
            throw new RuntimeException("This purchase order must be '{$expected}' for that action (currently '{$po->status}').");
        }
    }

    private function prepareLines(array $lines): array
    {
        $subtotal = 0;
        $prepared = [];

        foreach ($lines as $line) {
            $qty = (int) $line['quantity'];
            $cost = (float) $line['unit_cost'];
            $lineTotal = $qty * $cost;
            $subtotal += $lineTotal;

            $prepared[] = [
                'item_id'     => $line['item_id'] ?? null,
                'description' => $line['description'] ?? null,
                'quantity'    => $qty,
                'unit_cost'   => $cost,
                'line_total'  => $lineTotal,
            ];
        }

        return [$subtotal, $prepared];
    }

    private function log(User $actor, string $action, PurchaseOrder $po, ?string $notes = null): void
    {
        StockActivityLog::create([
            'user_id'         => $actor->id,
            'action'          => $action,
            'reference_type'  => 'purchase_order',
            'reference_id'    => $po->id,
            'notes'           => $notes,
        ]);
    }
}
