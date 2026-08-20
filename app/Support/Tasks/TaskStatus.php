<?php

namespace App\Support\Tasks;

class TaskStatus
{
    public const NOT_STARTED = 'not_started';
    public const IN_PROGRESS = 'in_progress';
    public const BLOCKED = 'blocked';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    public const ALL = [
        self::NOT_STARTED,
        self::IN_PROGRESS,
        self::BLOCKED,
        self::COMPLETED,
        self::CANCELLED,
    ];

    public const TRANSITIONS = [
        self::NOT_STARTED => [self::IN_PROGRESS, self::CANCELLED],
        self::IN_PROGRESS => [self::BLOCKED, self::COMPLETED, self::CANCELLED],
        self::BLOCKED      => [self::IN_PROGRESS, self::CANCELLED],
        self::COMPLETED    => [self::IN_PROGRESS],
        self::CANCELLED    => [self::NOT_STARTED],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
