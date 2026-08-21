<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataCollectionFormVersion extends Model
{
    protected $fillable = [
        'form_id', 'version_number', 'version_label', 'schema', 'status',
        'published_at', 'published_by', 'created_by',
    ];

    protected $casts = [
        'schema'       => 'array',
        'published_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(DataCollectionForm::class, 'form_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questionCount(): int
    {
        return collect($this->schema['sections'] ?? [])
            ->sum(fn ($section) => count($section['questions'] ?? []));
    }
}
