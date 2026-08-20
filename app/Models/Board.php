<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Board extends Model
{
    use SoftDeletes;

    protected $fillable = ['workspace_id', 'owner_id', 'name', 'description', 'is_archived'];

    protected $casts = [
        'is_archived' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TaskGroup::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
