<?php

namespace App\Models;

use App\Support\Tasks\TaskPriority;
use App\Support\Tasks\TaskStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'board_id', 'group_id', 'parent_id', 'appraisal_id', 'created_by',
        'title', 'description', 'status', 'priority',
        'start_date', 'due_date', 'completed_at',
        'position', 'is_archived',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'due_date'     => 'date',
        'completed_at' => 'datetime',
        'is_archived'  => 'boolean',
    ];

    protected $appends = ['progress'];

    public function getProgressAttribute(): int
    {
        return $this->progress();
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TaskGroup::class, 'group_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers')->withPivot('created_at');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TaskActivityLog::class)->latest('created_at');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [TaskStatus::COMPLETED, TaskStatus::CANCELLED]);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', TaskStatus::COMPLETED);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_date')->where('due_date', '<', now()->toDateString());
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->whereHas('assignees', fn ($q) => $q->where('users.id', $userId));
    }

    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->whereIn('priority', [TaskPriority::HIGH, TaskPriority::URGENT]);
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->lt(Carbon::today())
            && ! in_array($this->status, [TaskStatus::COMPLETED, TaskStatus::CANCELLED], true);
    }

    public function isDueToday(): bool
    {
        return $this->due_date && $this->due_date->isToday();
    }

    public function isDueSoon(int $days = 3): bool
    {
        return $this->due_date
            && ! $this->isOverdue()
            && $this->due_date->between(Carbon::today(), Carbon::today()->addDays($days));
    }

    public function progress(): int
    {
        $subtasks = $this->subtasks;

        if ($subtasks->isEmpty()) {
            return $this->status === TaskStatus::COMPLETED ? 100 : 0;
        }

        $completed = $subtasks->where('status', TaskStatus::COMPLETED)->count();

        return (int) round(($completed / $subtasks->count()) * 100);
    }
}
