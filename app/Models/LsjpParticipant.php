<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// A person trained under LSJP (Livelihood Skills & Job Preparation), followed
// up 1, 3 and 6 months after `training_completed_on`.
class LsjpParticipant extends Model
{
    use SoftDeletes;

    // Check-up month => label.
    public const CHECKUP_MONTHS = [1 => '1 month', 3 => '3 months', 6 => '6 months'];

    // A check-up counts as "due soon" this many days before its due date.
    public const DUE_SOON_DAYS = 14;

    protected $fillable = [
        'full_name', 'id_number', 'age', 'sex', 'phone', 'province', 'district', 'location',
        'key_population', 'current_activity', 'skill_trained', 'project',
        'training_completed_on', 'notes', 'created_by',
    ];

    protected $casts = [
        'training_completed_on' => 'date',
        'age'                   => 'integer',
    ];

    public function checkups(): HasMany
    {
        return $this->hasMany(LsjpCheckup::class)->orderBy('month');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // One entry per check-up month with its due date and state:
    // done | overdue | due_soon | upcoming. Uses loaded `checkups` if present.
    public function schedule(?Carbon $today = null): array
    {
        $today ??= now()->startOfDay();
        $done = $this->checkups->keyBy('month');

        return collect(self::CHECKUP_MONTHS)->map(function ($label, $month) use ($done, $today) {
            $due = $this->training_completed_on->copy()->addMonthsNoOverflow($month);
            $checkup = $done->get($month);

            $state = match (true) {
                $checkup !== null                                    => 'done',
                $due->lt($today)                                     => 'overdue',
                $due->lte($today->copy()->addDays(self::DUE_SOON_DAYS)) => 'due_soon',
                default                                              => 'upcoming',
            };

            return [
                'month'        => $month,
                'label'        => $label,
                'due_date'     => $due->toDateString(),
                'state'        => $state,
                'conducted_on' => $checkup?->conducted_on?->toDateString(),
                'progress'     => $checkup?->progress,
            ];
        })->values()->all();
    }
}
