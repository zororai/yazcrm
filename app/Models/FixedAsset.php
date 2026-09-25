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
        'supplier_name', 'supplier_id',
        'warranty_start', 'warranty_expiry', 'condition', 'status',
        'location_id', 'department_id', 'current_custodian_id', 'created_by',
    ];

    protected $casts = [
        'purchase_date'   => 'date',
        'warranty_start'  => 'date',
        'warranty_expiry' => 'date',
        'purchase_cost'   => 'decimal:2',
        'salvage_value'   => 'decimal:2',
        'useful_life_years' => 'integer',
    ];

    protected $appends = ['warranty_expiring', 'annual_depreciation', 'accumulated_depreciation', 'book_value'];

    public function getWarrantyExpiringAttribute(): bool
    {
        return $this->isWarrantyExpiring();
    }

    // Straight-line: (Purchase Cost − Salvage Value) ÷ Useful Life (years).
    // Requires purchase_cost, purchase_date, and useful_life_years to all be set.
    public function getAnnualDepreciationAttribute(): ?float
    {
        if (! $this->purchase_cost || ! $this->useful_life_years) {
            return null;
        }

        $depreciable = (float) $this->purchase_cost - (float) ($this->salvage_value ?? 0);

        return round(max($depreciable, 0) / $this->useful_life_years, 2);
    }

    public function getAccumulatedDepreciationAttribute(): ?float
    {
        $annual = $this->annual_depreciation;
        if ($annual === null || ! $this->purchase_date) {
            return null;
        }

        $yearsElapsed = min(
            $this->purchase_date->floatDiffInYears(now()),
            $this->useful_life_years
        );

        $depreciable = (float) $this->purchase_cost - (float) ($this->salvage_value ?? 0);

        return round(min($annual * $yearsElapsed, max($depreciable, 0)), 2);
    }

    public function getBookValueAttribute(): ?float
    {
        if ($this->accumulated_depreciation === null) {
            return $this->purchase_cost !== null ? (float) $this->purchase_cost : null;
        }

        return round((float) $this->purchase_cost - $this->accumulated_depreciation, 2);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
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
