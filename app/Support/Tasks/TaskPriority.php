<?php

namespace App\Support\Tasks;

class TaskPriority
{
    public const LOW = 'low';
    public const MEDIUM = 'medium';
    public const HIGH = 'high';
    public const URGENT = 'urgent';

    public const ALL = [
        self::LOW,
        self::MEDIUM,
        self::HIGH,
        self::URGENT,
    ];
}
