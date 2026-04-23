<?php

use Relova\Drivers\ApiDriver;
use Relova\Drivers\CsvDriver;
use Relova\Drivers\MySqlDriver;
use Relova\Drivers\OracleDriver;
use Relova\Drivers\PostgreSqlDriver;
use Relova\Drivers\SapHanaDriver;
use Relova\Drivers\SqlServerDriver;
use Relova\Drivers\XlsxDriver;

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
        'mysql' => MySqlDriver::class,
        'pgsql' => PostgreSqlDriver::class,
        'sqlsrv' => SqlServerDriver::class,
        'oracle' => OracleDriver::class,
        'sap_hana' => SapHanaDriver::class,
        'csv' => CsvDriver::class,
        'xlsx' => XlsxDriver::class,
        // Spec Phase 4 — registered as a stub; throws RuntimeException until implemented.
        'api' => ApiDriver::class,
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

    /*
    |--------------------------------------------------------------------------
    | Snapshot freshness
    |--------------------------------------------------------------------------
    |
    | Minutes after which a virtual entity reference's display snapshot is
    | considered stale. Reading a stale snapshot triggers a live remote
    | fetch (with graceful fallback to the stale snapshot if the remote
    | source is unreachable).
    |
    */
    'snapshot_fresh_minutes' => env('RELOVA_SNAPSHOT_FRESH_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Redis Cache Architecture (Spec §7)
    |--------------------------------------------------------------------------
    |
    | Relova caches remote row data exclusively in Redis (or the array driver
    | in development). PostgreSQL is never used as a row-data cache.
    |
    | hot_cache         — Cache::store name for Zone B (plaintext, 60s).
    | persistent_cache  — Cache::store name for Zone A (encrypted, 30min)
    |                     and the list-page tier (300s).
    |
    | In production set both to 'redis'. In development the default ('array')
    | works without any external services.
    |
    */
    'hot_cache' => env('RELOVA_HOT_CACHE_DRIVER', 'array'),
    'persistent_cache' => env('RELOVA_PERSISTENT_CACHE_DRIVER', 'array'),

    'zone_b_ttl' => (int) env('RELOVA_ZONE_B_TTL', 60),
    'zone_a_ttl' => (int) env('RELOVA_ZONE_A_TTL', 1800),
    'list_page_ttl' => (int) env('RELOVA_LIST_PAGE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Master Encryption Key (Spec §8)
    |--------------------------------------------------------------------------
    |
    | 32-byte key used to derive per-tenant Zone A keys via HKDF
    | (sodium_crypto_kdf_derive_from_key). Generate with:
    |
    |   php -r "echo base64_encode(random_bytes(32));"
    |
    | NEVER commit this value. Set via Forge → Site → Environment.
    |
    */
    'master_key' => env('RELOVA_MASTER_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Queue Names (Spec §10)
    |--------------------------------------------------------------------------
    */
    'sync_queue' => env('RELOVA_SYNC_QUEUE', 'sync'),
    'audit_queue' => env('RELOVA_AUDIT_QUEUE', 'sync'),
    'stats_queue' => env('RELOVA_STATS_QUEUE', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Sync Behaviour
    |--------------------------------------------------------------------------
    */
    'sync_chunk_size' => (int) env('RELOVA_SYNC_CHUNK_SIZE', 500),
    'sync_timeout' => (int) env('RELOVA_SYNC_TIMEOUT', 300),

    /*
    |--------------------------------------------------------------------------
    | TLS Defaults (Spec §9)
    |--------------------------------------------------------------------------
    */
    'default_tls_mode' => env('RELOVA_DEFAULT_TLS_MODE', 'verify-full'),

    /*
    |--------------------------------------------------------------------------
    | API Key Header
    |--------------------------------------------------------------------------
    */
    'api_key_header' => env('RELOVA_API_KEY_HEADER', 'X-Relova-Key'),

    /*
    |--------------------------------------------------------------------------
    | Sensitive Field Fragments (FieldMasker)
    |--------------------------------------------------------------------------
    |
    | Additional column-name fragments (case-insensitive substrings) treated
    | as sensitive by FieldMasker. The defaults already cover password,
    | secret, token, ssn, etc.
    |
    */
    'sensitive_field_fragments' => array_filter(explode(',', (string) env('RELOVA_SENSITIVE_FIELDS', ''))),
];
