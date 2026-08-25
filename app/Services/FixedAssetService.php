<?php

namespace App\Services;

use App\Models\FixedAsset;
use App\Models\FixedAssetActivityLog;
use App\Models\FixedAssetAssignment;
use App\Models\FixedAssetInspection;
use App\Models\FixedAssetMaintenance;
use App\Models\User;
use App\Support\Assets\AssetStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FixedAssetService
{
    public function registerAsset(User $actor, array $attributes): FixedAsset
    {
        return DB::transaction(function () use ($actor, $attributes) {
            $asset = FixedAsset::create($attributes + [
                'status'     => AssetStatus::AVAILABLE,
                'condition'  => $attributes['condition'] ?? 'good',
                'created_by' => $actor->id,
            ]);
            $asset->update(['asset_number' => 'AST-'.str_pad((string) $asset->id, 6, '0', STR_PAD_LEFT)]);

            $this->log($asset, $actor, 'created', newStatus: $asset->status);

            return $asset;
        });
    }

    public function updateAsset(FixedAsset $asset, User $actor, array $data): FixedAsset
    {
        return DB::transaction(function () use ($asset, $actor, $data) {
            $asset->update($data);

            $this->log($asset, $actor, 'updated', changedFields: array_keys($data));

            return $asset;
        });
    }

    // §15 Asset Assignment — never overwrites prior history, one active
    // custodian at a time, blocked for disposed/retired/maintenance/lost assets.
    public function assignAsset(FixedAsset $asset, User $actor, int $assignedToUserId, ?int $departmentId, ?int $locationId, ?string $notes = null): FixedAssetAssignment
    {
        if (in_array($asset->status, AssetStatus::NOT_ASSIGNABLE, true)) {
            throw new RuntimeException("An asset with status '{$asset->status}' cannot be assigned.");
        }

        return DB::transaction(function () use ($asset, $actor, $assignedToUserId, $departmentId, $locationId, $notes) {
            $oldStatus = $asset->status;

            $assignment = FixedAssetAssignment::create([
                'fixed_asset_id' => $asset->id,
                'assigned_to'    => $assignedToUserId,
                'department_id'  => $departmentId,
                'location_id'    => $locationId,
                'assigned_by'    => $actor->id,
                'assigned_at'    => now(),
                'notes'          => $notes,
                'status'         => 'active',
            ]);

            $asset->update([
                'status'                => AssetStatus::ASSIGNED,
                'current_custodian_id'  => $assignedToUserId,
                'department_id'         => $departmentId ?? $asset->department_id,
                'location_id'           => $locationId ?? $asset->location_id,
            ]);

            $this->log($asset, $actor, 'assigned', oldStatus: $oldStatus, newStatus: AssetStatus::ASSIGNED);

            return $assignment;
        });
    }

    // §16 Asset Return — closes the active assignment, records condition,
    // routes the asset to available/damaged/maintenance based on what came back.
    public function returnAsset(FixedAsset $asset, User $actor, string $condition, ?string $notes = null): FixedAsset
    {
        $assignment = $asset->assignments()->where('status', 'active')->first();
        if (! $assignment) {
            throw new RuntimeException('This asset has no active assignment to return.');
        }

        return DB::transaction(function () use ($asset, $actor, $assignment, $condition, $notes) {
            $oldStatus = $asset->status;

            $assignment->update([
                'returned_at'      => now(),
                'return_condition' => $condition,
                'return_notes'     => $notes,
                'status'           => 'returned',
            ]);

            $newStatus = $condition === 'damaged' ? AssetStatus::DAMAGED : AssetStatus::AVAILABLE;

            $asset->update([
                'status'                => $newStatus,
                'condition'             => $condition,
                'current_custodian_id'  => null,
            ]);

            $this->log($asset, $actor, 'returned', oldStatus: $oldStatus, newStatus: $newStatus, reason: $notes);

            return $asset;
        });
    }

    // §17 Asset Transfer — moves department/location without necessarily
    // changing custodian; always logged, never silently overwritten.
    public function transferAsset(FixedAsset $asset, User $actor, ?int $departmentId, ?int $locationId, ?string $notes = null): FixedAsset
    {
        if (in_array($asset->status, [AssetStatus::DISPOSED, AssetStatus::RETIRED], true)) {
            throw new RuntimeException("An asset with status '{$asset->status}' cannot be transferred.");
        }

        return DB::transaction(function () use ($asset, $actor, $departmentId, $locationId, $notes) {
            $changed = [];
            if ($departmentId !== null && $departmentId !== $asset->department_id) {
                $changed[] = 'department_id';
            }
            if ($locationId !== null && $locationId !== $asset->location_id) {
                $changed[] = 'location_id';
            }

            $asset->update([
                'department_id' => $departmentId ?? $asset->department_id,
                'location_id'   => $locationId ?? $asset->location_id,
            ]);

            $this->log($asset, $actor, 'transferred', changedFields: $changed, reason: $notes);

            return $asset;
        });
    }

    // §20 Asset Disposal — never deletes the record, only marks it disposed.
    public function disposeAsset(FixedAsset $asset, User $actor, string $reason): FixedAsset
    {
        if ($asset->status === AssetStatus::DISPOSED) {
            throw new RuntimeException('This asset is already disposed.');
        }
        if ($asset->status === AssetStatus::ASSIGNED) {
            throw new RuntimeException('An assigned asset must be returned before it can be disposed.');
        }

        return DB::transaction(function () use ($asset, $actor, $reason) {
            $oldStatus = $asset->status;

            $asset->update(['status' => AssetStatus::DISPOSED]);

            $this->log($asset, $actor, 'disposed', oldStatus: $oldStatus, newStatus: AssetStatus::DISPOSED, reason: $reason);

            return $asset;
        });
    }

    // §18 Asset Maintenance — takes the asset out of circulation while work is done.
    public function scheduleMaintenance(FixedAsset $asset, User $actor, array $data): FixedAssetMaintenance
    {
        if (in_array($asset->status, [AssetStatus::ASSIGNED, AssetStatus::DISPOSED, AssetStatus::RETIRED], true)) {
            throw new RuntimeException("An asset with status '{$asset->status}' cannot be sent for maintenance — return it first if assigned.");
        }

        return DB::transaction(function () use ($asset, $actor, $data) {
            $oldStatus = $asset->status;

            $record = FixedAssetMaintenance::create($data + [
                'fixed_asset_id' => $asset->id,
                'status'         => 'scheduled',
                'created_by'     => $actor->id,
            ]);

            $asset->update(['status' => AssetStatus::UNDER_MAINTENANCE]);

            $this->log($asset, $actor, 'maintenance_scheduled', oldStatus: $oldStatus, newStatus: AssetStatus::UNDER_MAINTENANCE);

            return $record;
        });
    }

    public function completeMaintenance(FixedAssetMaintenance $record, User $actor, ?string $notes = null): FixedAssetMaintenance
    {
        if ($record->status !== 'scheduled') {
            throw new RuntimeException('This maintenance record is not open.');
        }

        return DB::transaction(function () use ($record, $actor, $notes) {
            $asset = $record->fixedAsset;
            $oldStatus = $asset->status;

            $record->update([
                'status'       => 'completed',
                'performed_by' => $record->performed_by ?? $actor->id,
                'notes'        => $notes ?? $record->notes,
            ]);

            $asset->update(['status' => AssetStatus::AVAILABLE]);

            $this->log($asset, $actor, 'maintenance_completed', oldStatus: $oldStatus, newStatus: AssetStatus::AVAILABLE);

            return $record;
        });
    }

    public function cancelMaintenance(FixedAssetMaintenance $record, User $actor): FixedAssetMaintenance
    {
        if ($record->status !== 'scheduled') {
            throw new RuntimeException('This maintenance record is not open.');
        }

        return DB::transaction(function () use ($record, $actor) {
            $asset = $record->fixedAsset;
            $oldStatus = $asset->status;

            $record->update(['status' => 'cancelled']);
            $asset->update(['status' => AssetStatus::AVAILABLE]);

            $this->log($asset, $actor, 'maintenance_cancelled', oldStatus: $oldStatus, newStatus: AssetStatus::AVAILABLE);

            return $record;
        });
    }

    // §19 Asset Inspections — append-only observation log; the asset's
    // recorded condition tracks the most recent inspection.
    public function recordInspection(FixedAsset $asset, User $actor, array $data): FixedAssetInspection
    {
        return DB::transaction(function () use ($asset, $actor, $data) {
            $inspection = FixedAssetInspection::create($data + [
                'fixed_asset_id' => $asset->id,
                'inspector_id'   => $actor->id,
            ]);

            $asset->update(['condition' => $data['condition']]);

            $this->log($asset, $actor, 'inspected');

            return $inspection;
        });
    }

    public function changeStatus(FixedAsset $asset, User $actor, string $status, ?string $reason = null): FixedAsset
    {
        if (! in_array($status, AssetStatus::ALL, true)) {
            throw new RuntimeException("Unknown asset status '{$status}'.");
        }

        return DB::transaction(function () use ($asset, $actor, $status, $reason) {
            $oldStatus = $asset->status;

            $asset->update(['status' => $status]);

            $this->log($asset, $actor, 'status_changed', oldStatus: $oldStatus, newStatus: $status, reason: $reason);

            return $asset;
        });
    }

    private function log(
        FixedAsset $asset,
        User $actor,
        string $action,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?array $changedFields = null,
        ?string $reason = null,
    ): void {
        FixedAssetActivityLog::create([
            'fixed_asset_id' => $asset->id,
            'user_id'        => $actor->id,
            'action'         => $action,
            'old_status'     => $oldStatus,
            'new_status'     => $newStatus,
            'changed_fields' => $changedFields,
            'reason'         => $reason,
        ]);
    }
}
