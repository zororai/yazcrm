<?php

namespace App\Models;

use App\Support\DataCollection\FormAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Grants one user, or everyone with a CRM role, permissions on a form.
class DataCollectionFormAssignment extends Model
{
    public const PERMISSIONS = ['collect', 'view', 'edit', 'export', 'map'];

    // The data-collection roles, as per-form permission presets.
    public const PRESETS = [
        'enumerator' => ['collect'],
        'viewer'     => ['view'],
        'analyst'    => ['view', 'edit', 'export'],
        'designer'   => ['collect', 'view', 'edit', 'export', 'map'],
    ];

    protected $fillable = ['form_id', 'user_id', 'crm_role', 'permissions', 'assigned_by'];

    protected $casts = [
        'permissions' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => FormAccess::forget());
        static::deleted(fn () => FormAccess::forget());
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(DataCollectionForm::class, 'form_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
