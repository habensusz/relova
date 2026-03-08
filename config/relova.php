<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Schema Cache TTL
    |--------------------------------------------------------------------------
    |
    | Default time-to-live (in seconds) for cached schema metadata
    | (table lists, column definitions). Actual row data is always live.
    |
    */
    'schema_cache_ttl' => env('RELOVA_SCHEMA_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Query Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time (in seconds) for remote queries before they are terminated.
    | Prevents cascading failures from slow or unresponsive remote systems.
    |
    */
    'query_timeout' => env('RELOVA_QUERY_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Connection Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time (in seconds) to wait for a connection to be established.
    |
    */
    'connection_timeout' => env('RELOVA_CONNECTION_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Max Rows Per Query
    |--------------------------------------------------------------------------
    |
    | Hard limit on the number of rows returned per query.
    |
    */
    'max_rows_per_query' => env('RELOVA_MAX_ROWS', 10000),

    /*
    |--------------------------------------------------------------------------
    | Default Page Size
    |--------------------------------------------------------------------------
    |
    | Default pagination size for queries.
    |
    */
    'default_page_size' => env('RELOVA_PAGE_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Snapshot Refresh Interval
    |--------------------------------------------------------------------------
    |
    | How often (in seconds) display snapshots on virtual entity references
    | should be refreshed. Default: 24 hours.
    |
    */
    'snapshot_refresh_interval' => env('RELOVA_SNAPSHOT_REFRESH', 86400),

    /*
    |--------------------------------------------------------------------------
    | Health Check Interval
    |--------------------------------------------------------------------------
    |
    | How often (in seconds) the background health-check job validates
    | that remote sources are still reachable. Default: 5 minutes.
    |
    */
    'health_check_interval' => env('RELOVA_HEALTH_CHECK_INTERVAL', 300),

    /*
    |--------------------------------------------------------------------------
    | SSRF Protection — Blocked IP Ranges
    |--------------------------------------------------------------------------
    |
    | Internal IP ranges and reserved address spaces that are blocklisted.
    | Tenants cannot point connectors at internal infrastructure.
    |
    | Set RELOVA_SSRF_PROTECTION=false to disable entirely (local dev only).
    | List specific hosts in `allowed_hosts` to whitelist them regardless
    | of their resolved IP (useful for local DB containers etc.).
    |
    */
    'ssrf_protection' => env('RELOVA_SSRF_PROTECTION', true),

    'ssrf_allowed_hosts' => array_filter(explode(',', env('RELOVA_SSRF_ALLOWED_HOSTS', ''))),

    'blocked_ip_ranges' => [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '0.0.0.0/8',
        '::1/128',
        'fc00::/7',
        'fe80::/10',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Connector Drivers
    |--------------------------------------------------------------------------
    |
    | Which connector driver types are enabled. Each must have a corresponding
    | driver class implementing Relova\Contracts\ConnectorDriver.
    |
    */
    'drivers' => [
        'mysql' => \Relova\Drivers\MySqlDriver::class,
        'pgsql' => \Relova\Drivers\PostgreSqlDriver::class,
        'sqlsrv' => \Relova\Drivers\SqlServerDriver::class,
        // Phase 2:
        // 'oracle' => \Relova\Drivers\OracleDriver::class,
        // 'sap_hana' => \Relova\Drivers\SapHanaDriver::class,
        // Phase 3:
        // 'csv' => \Relova\Drivers\CsvDriver::class,
        // 'excel' => \Relova\Drivers\ExcelDriver::class,
        // Phase 4:
        // 'rest_api' => \Relova\Drivers\RestApiDriver::class,
        // 'odata' => \Relova\Drivers\ODataDriver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api' => [
        'prefix' => 'api/relova/v1',
        'middleware' => ['api'],
        'rate_limit' => env('RELOVA_API_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload
    |--------------------------------------------------------------------------
    |
    | Settings for file-based connectors (CSV, Excel).
    |
    */
    'file' => [
        'max_upload_size' => env('RELOVA_MAX_UPLOAD_KB', 10240),
        'allowed_types' => ['csv', 'xlsx', 'xls'],
        'storage_disk' => env('RELOVA_STORAGE_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption
    |--------------------------------------------------------------------------
    |
    | By default Relova uses the application encryption key.
    | Set a separate key for additional credential isolation.
    |
    */
    'encryption_key' => env('RELOVA_ENCRYPTION_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Database Table Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for all Relova database tables.
    |
    */
    'table_prefix' => 'relova_',
];
