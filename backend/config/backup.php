<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Portable production backups
    |--------------------------------------------------------------------------
    |
    | A backup code is installation-independent. Each customer only configures
    | the destination and binaries in .env. Keep BACKUP_PATH outside any public
    | document root whenever the hosting account allows it.
    |
    */
    'enabled' => (bool) env('BACKUP_ENABLED', false),
    'path' => ($path = trim((string) env('BACKUP_PATH', ''))) !== ''
        ? $path
        : storage_path('app/private/backups'),
    'retention_days' => max(1, (int) env('BACKUP_RETENTION_DAYS', 14)),
    'include_media' => (bool) env('BACKUP_INCLUDE_MEDIA', true),
    'mysqldump_binary' => env('BACKUP_MYSQLDUMP_BINARY', 'mysqldump'),
    'gzip_binary' => env('BACKUP_GZIP_BINARY', 'gzip'),
    'timeout_seconds' => max(30, (int) env('BACKUP_TIMEOUT_SECONDS', 300)),

    'media_paths' => [
        'businesses' => storage_path('app/public/businesses'),
        'services' => storage_path('app/public/services'),
    ],
];
