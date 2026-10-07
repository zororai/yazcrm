<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'asset_number', 'asset_category_id', 'name', 'description', 'manufacturer', 'model',
        'serial_number', 'barcode', 'purchase_date', 'purchase_cost', 'useful_life_years', 'salvage_value',
        'revaluation_cycle_years', 'last_revalued_at', 'current_value', 'depreciation_base_date',
        'supplier_name', 'supplier_id',
        'warranty_start', 'warranty_expiry', 'condition', 'status',
        'location_id', 'home_location_id', 'department_id', 'current_custodian_id', 'created_by',
    ];

    protected $casts = [
        'purchase_date'   => 'date',
        'warranty_start'  => 'date',
        'warranty_expiry' => 'date',
        'purchase_cost'   => 'decimal:2',
        'salvage_value'   => 'decimal:2',
        'useful_life_years' => 'integer',
        'revaluation_cycle_years' => 'integer',
        'last_revalued_at' => 'date',
        'current_value'    => 'decimal:2',
        'depreciation_base_date' => 'date',
    ];

    protected $appends = [
        'warranty_expiring', 'annual_depreciation', 'accumulated_depreciation', 'book_value',
        'next_revaluation_due', 'revaluation_due',
    ];

    public function getWarrantyExpiringAttribute(): bool
    {
        return $this->isWarrantyExpiring();
    }

    // The value/date depreciation is calculated from: reset to the revalued
    // amount and revaluation date whenever a revaluation has been recorded,
    // otherwise falls back to the original purchase cost/date.
    private function depreciationBaseCost(): ?float
    {
        $base = $this->current_value ?? $this->purchase_cost;

        return $base !== null ? (float) $base : null;
    }

    private function depreciationBaseDate()
    {
        return $this->depreciation_base_date ?? $this->purchase_date;
    }

    // Straight-line: (Base Cost − Salvage Value) ÷ Useful Life (years).
    // Requires a base cost, base date, and useful_life_years to all be set.
    public function getAnnualDepreciationAttribute(): ?float
    {
        $baseCost = $this->depreciationBaseCost();
        if (! $baseCost || ! $this->useful_life_years) {
            return null;
        }

        $depreciable = $baseCost - (float) ($this->salvage_value ?? 0);

        return round(max($depreciable, 0) / $this->useful_life_years, 2);
    }

    public function getAccumulatedDepreciationAttribute(): ?float
    {
        $annual   = $this->annual_depreciation;
        $baseDate = $this->depreciationBaseDate();
        if ($annual === null || ! $baseDate) {
            return null;
        }

        $yearsElapsed = min(
            $baseDate->floatDiffInYears(now()),
            $this->useful_life_years
        );

        $depreciable = $this->depreciationBaseCost() - (float) ($this->salvage_value ?? 0);

        return round(min($annual * $yearsElapsed, max($depreciable, 0)), 2);
    }

    public function getBookValueAttribute(): ?float
    {
        $baseCost = $this->depreciationBaseCost();

        if ($this->accumulated_depreciation === null) {
            return $baseCost;
        }

        return round($baseCost - $this->accumulated_depreciation, 2);
    }

    // Revaluation due date = last revaluation (or purchase date if never
    // revalued) + this asset's revaluation cycle (default 3 years).
    public function getNextRevaluationDueAttribute(): ?string
    {
        $anchor = $this->last_revalued_at ?? $this->purchase_date;
        if (! $anchor || ! $this->revaluation_cycle_years) {
            return null;
        }

        return $anchor->copy()->addYears($this->revaluation_cycle_years)->toDateString();
    }

    public function getRevaluationDueAttribute(): bool
    {
        $due = $this->next_revaluation_due;

        return $due !== null && now()->toDateString() >= $due;
    }

    public function revaluations(): HasMany
    {
        return $this->hasMany(FixedAssetRevaluation::class)->latest('revaluation_date');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // "Issued location" — where the asset is in use now (Assign/Transfer update it).
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    // "Asset location" — where the asset is normally kept.
    public function homeLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'home_location_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_custodian_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(FixedAssetAssignment::class)->latest('assigned_at');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(FixedAssetActivityLog::class)->latest('created_at');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(FixedAssetMaintenance::class)->latest('service_date');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(FixedAssetInspection::class)->latest('inspected_at');
    }

    public function isWarrantyExpiring(int $withinDays = 90): bool
    {
        return $this->warranty_expiry && $this->warranty_expiry->between(now(), now()->addDays($withinDays));
    }
}
