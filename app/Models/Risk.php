<?php

namespace App\Models;

use App\Support\RiskScorer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Risk extends Model
{
    protected $fillable = [
        'asset_id', 'risk_ref', 'category', 'description', 'cause',
        'likelihood', 'impact', 'inherent_score', 'residual_score', 'risk_owner',
    ];

    protected $casts = [
        'likelihood'     => 'integer',
        'impact'         => 'integer',
        'inherent_score' => 'integer',
        'residual_score' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Risk $risk) {
            $risk->inherent_score = $risk->likelihood * $risk->impact;

            $best = $risk->controls()->orderByRaw("FIELD(effectiveness,'high','medium','low')")->value('effectiveness');
            $risk->residual_score = RiskScorer::residual($risk->inherent_score, $best);
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function controls(): HasMany
    {
        return $this->hasMany(Control::class);
    }

    public function priorityAction(): HasOne
    {
        return $this->hasOne(PriorityAction::class);
    }
}
