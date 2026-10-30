<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

// status: draft (never deployed) | deployed | archived (no longer collected).
// current_version_id points at the deployed version the app downloads.
class DataCollectionForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'code', 'name', 'description', 'status', 'current_version_id', 'created_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(DataCollectionProject::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DataCollectionFormVersion::class, 'form_id')->orderByDesc('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DataCollectionFormVersion::class, 'current_version_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DataCollectionFormAssignment::class, 'form_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(DataCollectionSubmission::class, 'form_id');
    }

    public function mapping(): HasOne
    {
        return $this->hasOne(DataCollectionFormMapping::class, 'form_id');
    }

    public function isDeployed(): bool
    {
        return $this->status === 'deployed' && $this->current_version_id !== null;
    }
}
