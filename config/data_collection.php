<?php

return [
    // URL prefix for the beneficiary app's data collection API, under /api.
    // The Flutter app is built against "v1" — change both together.
    'api_prefix' => env('DATA_COLLECTION_API_PREFIX', 'v1'),

    // POST /submissions/batch refuses envelopes with more items than this.
    'batch_max' => 50,

    // Where uploaded photos/files are stored. Any disk in filesystems.php
    // (local by default; set to s3 to keep media off the server).
    'media_disk' => env('DATA_COLLECTION_MEDIA_DISK', 'local'),

    // Largest accepted media upload, in kilobytes.
    'media_max_kb' => (int) env('DATA_COLLECTION_MEDIA_MAX_KB', 5120),

    // Mobile endpoints: requests per minute, per user and per device token.
    'rate_limit_per_minute' => (int) env('DATA_COLLECTION_RATE_LIMIT', 120),

    // Disk for queued exports.
    'export_disk' => env('DATA_COLLECTION_EXPORT_DISK', 'local'),
];
