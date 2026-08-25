<?php

namespace App\Support\Assets;

class AssetStatus
{
    public const AVAILABLE = 'available';
    public const RESERVED = 'reserved';
    public const ASSIGNED = 'assigned';
    public const IN_TRANSIT = 'in_transit';
    public const UNDER_MAINTENANCE = 'under_maintenance';
    public const DAMAGED = 'damaged';
    public const LOST = 'lost';
    public const STOLEN = 'stolen';
    public const RETIRED = 'retired';
    public const DISPOSED = 'disposed';

    public const ALL = [
        self::AVAILABLE, self::RESERVED, self::ASSIGNED, self::IN_TRANSIT,
        self::UNDER_MAINTENANCE, self::DAMAGED, self::LOST, self::STOLEN,
        self::RETIRED, self::DISPOSED,
    ];

    // Statuses an asset cannot be assigned from — it's already spoken for,
    // out of service, or gone.
    public const NOT_ASSIGNABLE = [
        self::ASSIGNED, self::UNDER_MAINTENANCE, self::DISPOSED,
        self::RETIRED, self::LOST, self::STOLEN,
    ];

    public const CONDITIONS = ['new', 'excellent', 'good', 'fair', 'poor', 'damaged', 'unserviceable'];
}
