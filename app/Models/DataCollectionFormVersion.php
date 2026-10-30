<?php

namespace App\Models;

use App\Support\DataCollection\SchemaWalker;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// status: draft | deployed | archived. Deployed and archived versions are
// immutable and stay valid for ingestion, since a device may still be on one.
class DataCollectionFormVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id', 'version_number', 'version_label', 'schema', 'checksum', 'errors',
        'source_path', 'source_name', 'status', 'deployed_at', 'deployed_by', 'created_by',
    ];

    protected $casts = [
        'schema'      => 'array',
        'errors'      => 'array',
        'deployed_at' => 'datetime',
    ];

    protected $hidden = ['source_path'];

    public function form(): BelongsTo
    {
        return $this->belongsTo(DataCollectionForm::class, 'form_id');
    }

    public function deployedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deployed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acceptsSubmissions(): bool
    {
        return in_array($this->status, ['deployed', 'archived'], true);
    }

    public function questionCount(): int
    {
        return count(SchemaWalker::fields($this->schema ?? []));
    }
}
