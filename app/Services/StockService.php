<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Stocktake;
use App\Models\StockActivityLog;
use App\Models\StockAdjustment;
use App\Models\StockIssue;
use App\Models\StockReceipt;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    private function stockFor(int $storeId, int $itemId): StoreStock
    {
        return StoreStock::firstOrCreate(['store_id' => $storeId, 'item_id' => $itemId]);
    }

    private function number(string $prefix, int $id): string
    {
        return $prefix.'-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    private function log(User $actor, string $action, array $attrs = []): void
    {
        StockActivityLog::create($attrs + [
            'user_id' => $actor->id,
            'action'  => $action,
        ]);
    }

    // §8 Stock Receiving — increases store_stock.quantity per line.
    public function receiveStock(Store $store, User $actor, array $lines, array $meta = []): StockReceipt
    {
        return DB::transaction(function () use ($store, $actor, $lines, $meta) {
            $receipt = StockReceipt::create([
                'store_id'         => $store->id,
                'received_by'      => $actor->id,
                'supplier_name'    => $meta['supplier_name'] ?? null,
                'reference_number' => $meta['reference_number'] ?? null,
                'notes'            => $meta['notes'] ?? null,
            ]);
            $receipt->update(['receipt_number' => $this->number('GRN', $receipt->id)]);

            foreach ($lines as $line) {
                $qty = (int) $line['quantity'];
                if ($qty <= 0) {
                    continue;
                }

                $receipt->items()->create([
                    'item_id'   => $line['item_id'],
                    'quantity'  => $qty,
                    'unit_cost' => $line['unit_cost'] ?? null,
                ]);

                $stock = $this->stockFor($store->id, $line['item_id']);
                $stock->increment('quantity', $qty);

                $this->log($actor, 'received', [
                    'store_id' => $store->id, 'item_id' => $line['item_id'],
                    'reference_type' => 'stock_receipt', 'reference_id' => $receipt->id,
                    'quantity_change' => $qty,
                ]);
            }

            return $receipt;
        });
    }

    // §9 Stock Issue — decreases store_stock.quantity, never below zero.
    public function issueStock(Store $store, User $actor, array $lines, array $meta = []): StockIssue
    {
        return DB::transaction(function () use ($store, $actor, $lines, $meta) {
            // Validate every line before mutating anything.
            foreach ($lines as $line) {
                $qty   = (int) $line['quantity'];
                $stock = $this->stockFor($store->id, $line['item_id']);

                if ($qty > $stock->available_quantity) {
                    $item = Item::find($line['item_id']);
                    throw new RuntimeException("Cannot issue {$qty} of \"{$item?->name}\" — only {$stock->available_quantity} available.");
                }
            }

            $issue = StockIssue::create([
                'store_id'      => $store->id,
                'issued_by'     => $actor->id,
                'department_id' => $meta['department_id'] ?? null,
                'issued_to'     => $meta['issued_to'] ?? null,
                'reason'        => $meta['reason'] ?? null,
            ]);
            $issue->update(['issue_number' => $this->number('ISS', $issue->id)]);

            foreach ($lines as $line) {
                $qty = (int) $line['quantity'];
                if ($qty <= 0) {
                    continue;
                }

                $issue->items()->create(['item_id' => $line['item_id'], 'quantity' => $qty]);

                $stock = $this->stockFor($store->id, $line['item_id']);
                $stock->decrement('quantity', $qty);

                $this->log($actor, 'issued', [
                    'store_id' => $store->id, 'item_id' => $line['item_id'],
                    'reference_type' => 'stock_issue', 'reference_id' => $issue->id,
                    'quantity_change' => -$qty,
                ]);
            }

            return $issue;
        });
    }

    // §10 Stock Transfer — create (no movement), dispatch (deduct source), receive (add destination).
    public function createTransfer(User $actor, int $fromStoreId, int $toStoreId, array $lines, ?string $notes = null): StockTransfer
    {
        if ($fromStoreId === $toStoreId) {
            throw new RuntimeException('Source and destination store must be different.');
        }

        return DB::transaction(function () use ($actor, $fromStoreId, $toStoreId, $lines, $notes) {
            $transfer = StockTransfer::create([
                'from_store_id' => $fromStoreId,
                'to_store_id'   => $toStoreId,
                'requested_by'  => $actor->id,
                'status'        => 'draft',
                'notes'         => $notes,
            ]);
            $transfer->update(['transfer_number' => $this->number('TRF', $transfer->id)]);

            foreach ($lines as $line) {
                $qty = (int) $line['quantity'];
                if ($qty > 0) {
                    $transfer->items()->create(['item_id' => $line['item_id'], 'quantity' => $qty]);
                }
            }

            return $transfer;
        });
    }

    public function dispatchTransfer(StockTransfer $transfer, User $actor): StockTransfer
    {
        if ($transfer->status !== 'draft') {
            throw new RuntimeException("Cannot dispatch a transfer in status '{$transfer->status}'.");
        }

        return DB::transaction(function () use ($transfer, $actor) {
            foreach ($transfer->items as $line) {
                $stock = $this->stockFor($transfer->from_store_id, $line->item_id);
                if ($line->quantity > $stock->available_quantity) {
                    $item = Item::find($line->item_id);
                    throw new RuntimeException("Cannot dispatch {$line->quantity} of \"{$item?->name}\" — only {$stock->available_quantity} available at the source store.");
                }
            }

            foreach ($transfer->items as $line) {
                $this->stockFor($transfer->from_store_id, $line->item_id)->decrement('quantity', $line->quantity);

                $this->log($actor, 'transfer_dispatched', [
                    'store_id' => $transfer->from_store_id, 'item_id' => $line->item_id,
                    'reference_type' => 'stock_transfer', 'reference_id' => $transfer->id,
                    'quantity_change' => -$line->quantity,
                ]);
            }

            $transfer->update(['status' => 'dispatched', 'dispatched_at' => now()]);

            return $transfer;
        });
    }

    public function receiveTransfer(StockTransfer $transfer, User $actor): StockTransfer
    {
        if ($transfer->status !== 'dispatched') {
            throw new RuntimeException("Cannot receive a transfer in status '{$transfer->status}'.");
        }

        return DB::transaction(function () use ($transfer, $actor) {
            foreach ($transfer->items as $line) {
                $this->stockFor($transfer->to_store_id, $line->item_id)->increment('quantity', $line->quantity);

                $this->log($actor, 'transfer_received', [
                    'store_id' => $transfer->to_store_id, 'item_id' => $line->item_id,
                    'reference_type' => 'stock_transfer', 'reference_id' => $transfer->id,
                    'quantity_change' => $line->quantity,
                ]);
            }

            $transfer->update(['status' => 'received', 'received_at' => now()]);

            return $transfer;
        });
    }

    public function cancelTransfer(StockTransfer $transfer, User $actor): StockTransfer
    {
        if ($transfer->status !== 'draft') {
            throw new RuntimeException('Only a draft transfer can be cancelled — a dispatched transfer must be received first.');
        }

        $transfer->update(['status' => 'cancelled']);
        $this->log($actor, 'transfer_cancelled', [
            'reference_type' => 'stock_transfer', 'reference_id' => $transfer->id,
        ]);

        return $transfer;
    }

    // §11 Stock Adjustment — controlled variance correction, reason required.
    public function adjustStock(Store $store, Item $item, User $actor, int $physicalQuantity, string $reason, ?string $notes = null, ?int $stocktakeId = null): StockAdjustment
    {
        return DB::transaction(function () use ($store, $item, $actor, $physicalQuantity, $reason, $notes, $stocktakeId) {
            $stock  = $this->stockFor($store->id, $item->id);
            $system = $stock->quantity;
            $variance = $physicalQuantity - $system;

            $adjustment = StockAdjustment::create([
                'store_id'          => $store->id,
                'item_id'           => $item->id,
                'system_quantity'   => $system,
                'physical_quantity' => $physicalQuantity,
                'variance'          => $variance,
                'reason'            => $reason,
                'notes'             => $notes,
                'adjusted_by'       => $actor->id,
                'stocktake_id'      => $stocktakeId,
            ]);
            $adjustment->update(['adjustment_number' => $this->number('ADJ', $adjustment->id)]);

            $stock->update(['quantity' => $physicalQuantity]);

            $this->log($actor, 'adjusted', [
                'store_id' => $store->id, 'item_id' => $item->id,
                'reference_type' => 'stock_adjustment', 'reference_id' => $adjustment->id,
                'quantity_change' => $variance,
                'notes' => $reason,
            ]);

            return $adjustment;
        });
    }

    // §12 Stocktaking — snapshot, count, then post variances as adjustments.
    public function startStocktake(Store $store, User $actor): Stocktake
    {
        return DB::transaction(function () use ($store, $actor) {
            $stocktake = Stocktake::create([
                'store_id'   => $store->id,
                'started_by' => $actor->id,
                'started_at' => now(),
                'status'     => 'counting',
            ]);
            $stocktake->update(['stocktake_number' => $this->number('ST', $stocktake->id)]);

            foreach ($store->stock as $stock) {
                $stocktake->items()->create([
                    'item_id'         => $stock->item_id,
                    'system_quantity' => $stock->quantity,
                ]);
            }

            $this->log($actor, 'stocktake_started', [
                'store_id' => $store->id,
                'reference_type' => 'stocktake', 'reference_id' => $stocktake->id,
            ]);

            return $stocktake;
        });
    }

    public function recordCounts(Stocktake $stocktake, array $counts): Stocktake
    {
        if ($stocktake->status !== 'counting') {
            throw new RuntimeException("Cannot record counts on a stocktake in status '{$stocktake->status}'.");
        }

        DB::transaction(function () use ($stocktake, $counts) {
            foreach ($counts as $itemId => $physicalQuantity) {
                $line = $stocktake->items()->where('item_id', $itemId)->first();
                if (! $line || $physicalQuantity === null || $physicalQuantity === '') {
                    continue;
                }

                $physicalQuantity = (int) $physicalQuantity;
                $line->update([
                    'physical_quantity' => $physicalQuantity,
                    'variance'          => $physicalQuantity - $line->system_quantity,
                ]);
            }
        });

        return $stocktake->fresh('items');
    }

    public function completeStocktake(Stocktake $stocktake, User $actor): Stocktake
    {
        if ($stocktake->status !== 'counting') {
            throw new RuntimeException("Cannot complete a stocktake in status '{$stocktake->status}'.");
        }

        return DB::transaction(function () use ($stocktake, $actor) {
            foreach ($stocktake->items as $line) {
                if ($line->physical_quantity === null || $line->variance === 0) {
                    continue;
                }

                $this->adjustStock(
                    $stocktake->store,
                    $line->item,
                    $actor,
                    $line->physical_quantity,
                    'counting_error',
                    "Stocktake {$stocktake->stocktake_number}",
                    $stocktake->id
                );
            }

            $stocktake->update([
                'status'       => 'completed',
                'completed_by' => $actor->id,
                'completed_at' => now(),
            ]);

            $this->log($actor, 'stocktake_completed', [
                'store_id' => $stocktake->store_id,
                'reference_type' => 'stocktake', 'reference_id' => $stocktake->id,
            ]);

            return $stocktake;
        });
    }
}
