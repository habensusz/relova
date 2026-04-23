# RELOVA — Complete AI Copilot Specification
> Version 1.0 | Use this file as the system prompt or context document when asking an AI to implement, extend, or modify Relova.

---

## 1. WHAT RELOVA IS

Relova is a **standalone Laravel package** — a virtual, federated, read-only data connector platform. It connects applications to remote data sources (relational databases, enterprise systems, file-based sources) without ever copying or importing remote data into its own database.

Relova is consumed by host applications (itenance CMMS or any external customer) via a Laravel SDK or REST API. From Relova's perspective, itenance is just another consumer — it has no special status inside the package.

**The core principle:** Relova owns the connections. Consumer applications own the relationships. Remote systems own the data.

**What Relova never does:**
- Never writes remote row data to any local database table (cache lives in Redis only)
- Never uses `fetchAll()` on remote query results — always streams via Generator
- Never accepts raw SQL from outside — builds all SQL internally
- Never opens a remote connection without passing through SsrfGuard
- Never shares encryption keys across tenants
- Never allows write operations through any driver
- Never lets a failed remote connection propagate as an exception to end users
- Never accepts a tenant_id from request input — tenant_id is always resolved server-side from the authenticated session/API key
- Never stores a remote primary key directly on a host app record — always uses virtual_entity_references

**What Relova does cache (Redis only):**
- Schema metadata (table/column lists), 30-min TTL
- Hot row data (Zone B), plaintext, 60-second TTL — sub-millisecond reads
- Warm row data (Zone A), AES-256-GCM encrypted with per-tenant HKDF key, 30-min TTL
- Pre-built list pages, 300-second TTL
- Display snapshots remain on `virtual_entity_references` as minimal JSONB (not the full row)

---

## 2. TECHNOLOGY STACK

```
Language:       PHP 8.3
Framework:      Laravel 12
Package type:   Composer package (no framework dependency beyond Laravel)
Database:       PostgreSQL (host app's DB, tenant-scoped schema via Stancl Tenancy v3)
                Stores ONLY: connections, virtual_entity_references, mappings,
                sync_configs, audit_logs. Never row data.
Cache:          Redis (production) / Array driver (development — no Redis needed)
                Two zones: Zone B (plaintext, 60s) + Zone A (encrypted, 30min) + List pages (300s)
Queue:          Redis (production) / sync driver (development)
Encryption:     libsodium (sodium_crypto_aead_aes256gcm / sodium_crypto_kdf_derive_from_key)
Testing:        PHPUnit / Pest
```

**Development environment variables (.env):**
```
CACHE_DRIVER=array
QUEUE_CONNECTION=sync
RELOVA_HOT_CACHE_DRIVER=array
RELOVA_PERSISTENT_CACHE_DRIVER=array
RELOVA_SYNC_QUEUE=sync
```

**Production environment variables (.env):**
```
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_CLIENT=phpredis
RELOVA_HOT_CACHE_DRIVER=redis
RELOVA_PERSISTENT_CACHE_DRIVER=redis
RELOVA_SYNC_QUEUE=relova-sync
RELOVA_MASTER_KEY=base64_encoded_32_byte_key
```

---

## 3. PACKAGE STRUCTURE

```
relova/
├── src/
│   ├── RelovaServiceProvider.php
│   ├── Contracts/
│   │   ├── ConnectorDriver.php
│   │   └── ModuleDataConsumer.php
│   ├── Drivers/
│   │   ├── PdoDriver.php              # MySQL, PostgreSQL, SQL Server
│   │   ├── OracleDriver.php           # Oracle via yajra/laravel-oci8
│   │   ├── SapHanaDriver.php          # SAP HANA via PDO
│   │   ├── SpreadsheetDriver.php      # Excel/CSV via PhpSpreadsheet
│   │   └── ApiDriver.php              # REST/OData sources
│   ├── Models/
│   │   ├── RelovaConnection.php
│   │   ├── VirtualEntityReference.php
│   │   ├── ConnectorModuleMapping.php
│   │   ├── RelovaSyncConfig.php
│   │   └── RelovaAuditLog.php
│   ├── Services/
│   │   ├── ConnectionRegistry.php
│   │   ├── SchemaInspector.php
│   │   ├── QueryExecutor.php
│   │   ├── ReferenceResolver.php
│   │   ├── SnapshotManager.php
│   │   ├── CacheManager.php
│   │   └── SyncEngine.php
│   ├── Cache/
│   │   ├── SchemaCache.php
│   │   ├── TwoZoneCache.php
│   │   └── ListCache.php
│   ├── Security/
│   │   ├── CredentialEncryptor.php
│   │   ├── CacheEncryptor.php
│   │   ├── SsrfGuard.php
│   │   ├── QuerySanitizer.php
│   │   ├── TenantIsolationGuard.php
│   │   ├── FieldMasker.php
│   │   └── AuditLogger.php
│   ├── Jobs/
│   │   ├── SyncRemoteTable.php
│   │   ├── RefreshCacheRecord.php
│   │   ├── RefreshSnapshot.php
│   │   ├── WarmSchemaCache.php
│   │   ├── HealthCheckConnector.php
│   │   ├── ValidateOrphanedReferences.php
│   │   ├── WriteAuditLog.php
│   │   └── IncrementAccessCount.php
│   ├── DTO/
│   │   ├── CacheResult.php
│   │   ├── CacheListResult.php
│   │   └── CacheGetResult.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ConnectionController.php
│   │   │   ├── SchemaController.php
│   │   │   ├── QueryController.php
│   │   │   └── ReferenceController.php
│   │   └── Middleware/
│   │       └── RelovaAuth.php
│   ├── Exceptions/
│   │   ├── RelovaConnectionException.php
│   │   ├── RelovaQueryException.php
│   │   ├── RelovaSsrfException.php
│   │   ├── QuerySecurityException.php
│   │   ├── TenantContextException.php
│   │   └── CustomerKeyDecryptionException.php
│   └── SDK/
│       └── RelovaClient.php
├── database/migrations/
│   ├── 2024_01_01_000001_create_relova_connections_table.php
│   ├── 2024_01_01_000002_create_relova_virtual_entity_references_table.php
│   ├── 2024_01_01_000003_create_relova_connector_module_mappings_table.php
│   ├── 2024_01_01_000004_create_relova_sync_configs_table.php
│   └── 2024_01_01_000005_create_relova_audit_logs_table.php
├── config/
│   └── relova.php
├── routes/
│   └── api.php
├── tests/
│   ├── Unit/
│   └── Feature/
└── composer.json
```

---

## 4. DATABASE SCHEMA

### 4.1 relova_connections
Stores connection configuration per tenant. Never stores remote data.

```sql
CREATE TABLE relova_connections (
    id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id               VARCHAR NOT NULL,
    name                    VARCHAR NOT NULL,
    driver                  VARCHAR NOT NULL, -- 'mysql'|'pgsql'|'sqlsrv'|'oracle'|'hana'|'excel'|'api'
    host                    VARCHAR,
    port                    SMALLINT,
    database                VARCHAR,
    credentials_encrypted   TEXT NOT NULL,    -- JSON blob, AES-256 per-tenant key
    tls_mode                VARCHAR NOT NULL DEFAULT 'verify-full', -- 'disabled'|'required'|'verify-ca'|'verify-full'
    tls_ca_cert_encrypted   TEXT,
    tls_client_cert_encrypted TEXT,
    tls_client_key_encrypted  TEXT,
    options                 JSONB DEFAULT '{}',
    status                  VARCHAR DEFAULT 'active', -- 'active'|'error'|'unreachable'
    last_checked_at         TIMESTAMP,
    last_error              TEXT,
    created_at              TIMESTAMP,
    updated_at              TIMESTAMP,
    deleted_at              TIMESTAMP
);
CREATE INDEX idx_relova_connections_tenant ON relova_connections(tenant_id);
```

### 4.2 relova_virtual_entity_references
One row per unique remote entity ever referenced. Never stores full records.

```sql
CREATE TABLE relova_virtual_entity_references (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           VARCHAR NOT NULL,
    connection_id       UUID NOT NULL REFERENCES relova_connections(id),
    remote_table        VARCHAR NOT NULL,
    remote_pk_column    VARCHAR NOT NULL,
    remote_pk_value     VARCHAR NOT NULL,
    display_snapshot    JSONB DEFAULT '{}',   -- Last-known display fields ONLY
    snapshot_taken_at   TIMESTAMP,
    snapshot_status     VARCHAR DEFAULT 'stale', -- 'fresh'|'stale'|'unavailable'
    created_at          TIMESTAMP,
    updated_at          TIMESTAMP,
    UNIQUE(tenant_id, connection_id, remote_table, remote_pk_column, remote_pk_value)
);
```

### 4.3 relova_connector_module_mappings
Maps a connection to a consuming module with field mapping config.

```sql
CREATE TABLE relova_connector_module_mappings (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       VARCHAR NOT NULL,
    connection_id   UUID NOT NULL REFERENCES relova_connections(id),
    module_key      VARCHAR NOT NULL,  -- 'assets'|'tickets'|'inventory'|'schedules'
    remote_table    VARCHAR NOT NULL,
    pk_column       VARCHAR NOT NULL,  -- Remote primary key column name
    field_mappings  JSONB NOT NULL,    -- {"local_field": "REMOTE_COLUMN", ...}
    display_columns JSONB NOT NULL,    -- Columns shown in list/picker
    filters         JSONB DEFAULT '{}',
    sync_behavior   VARCHAR DEFAULT 'warm', -- 'hot'|'warm'|'on_demand'|'virtual'
    cache_ttl_minutes SMALLINT DEFAULT 30,
    active          BOOLEAN DEFAULT true,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
);
```

### 4.4 relova_sync_configs
Controls how aggressively each table is cached.

```sql
CREATE TABLE relova_sync_configs (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id           VARCHAR NOT NULL,
    connection_id       UUID NOT NULL,
    remote_table        VARCHAR NOT NULL,
    pk_column           VARCHAR NOT NULL,
    sync_mode           VARCHAR DEFAULT 'warm', -- 'hot'|'warm'|'on_demand'|'virtual'
    ttl_minutes         SMALLINT DEFAULT 30,
    hot_set_size        INTEGER DEFAULT 500,
    sync_filter         JSONB DEFAULT '{}',
    cached_columns      JSONB DEFAULT '[]',     -- Empty = all columns
    display_columns     JSONB NOT NULL,
    sync_status         VARCHAR DEFAULT 'idle', -- 'idle'|'syncing'|'error'
    sync_error          TEXT,
    last_full_sync_at   TIMESTAMP,
    next_sync_at        TIMESTAMP,
    active              BOOLEAN DEFAULT true,
    created_at          TIMESTAMP,
    updated_at          TIMESTAMP
);
```

### 4.5 relova_audit_logs
Full traceability. Logs access metadata — never logs actual data values.

```sql
CREATE TABLE relova_audit_logs (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id       VARCHAR NOT NULL,
    actor_type      VARCHAR NOT NULL,  -- 'user'|'system'|'sync_job'
    actor_id        VARCHAR NOT NULL,
    action          VARCHAR NOT NULL,  -- 'query'|'sync'|'connection_test'|'schema_browse'
    connection_id   UUID,
    remote_table    VARCHAR,
    rows_accessed   INTEGER DEFAULT 0,
    source_ip       VARCHAR,
    user_agent      VARCHAR,
    query_metadata  JSONB NOT NULL,    -- Which columns filtered — NOT values
    result          VARCHAR NOT NULL,  -- 'success'|'failure'|'blocked'
    failure_reason  TEXT,
    occurred_at     TIMESTAMP NOT NULL
);
CREATE INDEX idx_relova_audit_tenant ON relova_audit_logs(tenant_id, occurred_at);
```

---

## 5. THE CONNECTOR DRIVER INTERFACE

Every driver implements this contract exactly. No exceptions.

```php
<?php

namespace Relova\Contracts;

interface ConnectorDriver
{
    /**
     * Test connection. Returns true on success.
     * Throws RelovaConnectionException on failure.
     * Must respect configured timeout. Must not persist any state.
     */
    public function testConnection(array $config): bool;

    /**
     * Return list of available table/entity names.
     * Called only on cache miss — result cached in Redis by SchemaCache.
     * Must return within timeout. Must not persist anything.
     *
     * @return string[]
     */
    public function getTables(array $config): array;

    /**
     * Return column definitions for a table.
     * Each item: ['name' => string, 'type' => string, 'nullable' => bool]
     * Also cached in Redis on return.
     *
     * @return array<array{name: string, type: string, nullable: bool}>
     */
    public function getColumns(array $config, string $table): array;

    /**
     * Execute a read-only query. Returns Generator — never array.
     * MUST enforce read-only. MUST stream results. MUST respect timeout.
     * MUST NOT write anything to any local storage.
     *
     * @return \Generator<array>
     */
    public function query(array $config, string $sql, array $bindings = [], array $options = []): \Generator;

    /**
     * Return driver identifier string.
     */
    public function driverName(): string;
}
```

---

## 6. THE THREE DATA ACCESS MECHANISMS

### Mechanism 1 — Pass-Through Query Execution
Used for: live search, "view live" actions, background sync jobs, cache misses on first access.

- Open connection via ConnectionPool (reuse if pooled, establish if cold)
- Pass through SsrfGuard before every new connection
- Build SQL internally — never accept raw SQL from outside
- Enforce read-only at transaction level regardless of credentials
- Stream results via Generator — never fetchAll()
- Write nothing to local database
- Close connection (or return to pool)

**Expected latency:** 11ms (pooled, single record) to 321ms (cold, 500-row sync chunk over VPN)

### Mechanism 2 — Schema Metadata Cache (Redis)
Used for: schema browser, dropdown population, mapping validation.

- Check Redis key `relova:schema:{tenant_id}:{connection_id}:tables`
- Hit: return immediately (~0.5ms)
- Miss: fetch from remote via pass-through, store in Redis with 30-minute TTL
- Schema metadata only — never row data
- Invalidated on manual tenant refresh

**Expected latency:** 0.5ms (hit) / 100-500ms (miss, populates cache)

### Mechanism 3 — Display Snapshot
Used for: rendering ticket/record labels when remote system may be unavailable.

- Stored on VirtualEntityReference as `display_snapshot` JSONB
- Contains only display fields (name, code, location, category)
- Never authoritative — always shows `snapshot_taken_at` timestamp
- Refreshed on every successful live access
- Refreshed by background RefreshSnapshot job
- On connection failure: serve stale snapshot with warning, never throw

**Expected latency:** 1-5ms (local PostgreSQL read)

---

## 7. THE REDIS HOT CACHE ARCHITECTURE

Relova's cache lives **entirely in Redis** (or the array driver in development). PostgreSQL is never used as a row-data cache — it stores only configuration, references, sync state, and audit logs.

### Read Decision Tree (execute in order, stop at first hit)

```
SHOW VIEW (single record):
  1. Zone B Redis (plaintext, 60s TTL)     → ~0.31ms total
  2. Zone A Redis (encrypted, 30min TTL)   → ~0.92ms total (decrypt + repopulate B)
  3. Pass-through to remote source         → ~11-500ms (write both zones after fetch)

LIST VIEW (paginated):
  1. Pre-built page Redis key (300s TTL)   → ~0.32ms total
  2. Pass-through to remote (build + cache)→ ~50-500ms (write list page after fetch)
```

On pass-through, the request always writes the result back to Redis before returning so the next read is fast. There is no PostgreSQL fallback layer — Redis is authoritative for cached row data, and the remote source is authoritative for everything else.

### Tenant Isolation in Redis (three layers, defence in depth)

Every Redis read and write is isolated per-tenant by three independent mechanisms:

1. **Key namespacing** — `tenant_id` is structurally embedded in every key (`relova:hot:{tenant_id}:...`). A query for tenant A's keys cannot return tenant B's data because the keys do not collide.
2. **Cryptographic binding (Zone A)** — AES-256-GCM uses `tenant_id` as Additional Authenticated Data (AAD). Ciphertext written for tenant A *cannot* be decrypted with tenant B's derived key — decryption fails with an authentication tag error.
3. **Server-side tenant resolution** — `tenant_id` is *never* read from request input. It is resolved server-side from the authenticated session (host app) or API key (`RelovaApiAuth` middleware). A malicious client cannot supply another tenant's ID.

### Zone B — Redis Hot Set (plaintext, 60-second TTL)
```
Key pattern:  relova:hot:{tenant_id}:{connection_id}:{table}:{pk_value}
Value:        JSON-encoded row array
TTL:          60 seconds
Purpose:      Sub-millisecond reads for recently accessed records
Driver:       Cache::store(config('relova.hot_cache'))
              → 'array' in development, 'redis' in production
Tradeoff:     Plaintext — if Redis itself is compromised, last 60s of hot rows are exposed.
              Acceptable for the speed gain; sensitive data lives in Zone A.
```

### Zone A — Redis Encrypted (encrypted, 30-minute TTL)
```
Key pattern:  relova:enc:{tenant_id}:{connection_id}:{table}:{pk_value}
Value:        base64(nonce || AES-256-GCM ciphertext) with tenant_id as AAD
TTL:          1800 seconds
Purpose:      Persistent encrypted layer survives Zone B expiry.
              On Zone A hit, the decrypted row is re-written to Zone B before returning.
Driver:       Cache::store(config('relova.persistent_cache'))
```

### Pre-Built List Pages
```
Key pattern:  relova:list:{tenant_id}:{connection_id}:{table}:{filter_md5}:{sort}:{page}:{per_page}
Value:        JSON {rows: [...], total: N, page: N}
TTL:          300 seconds
Purpose:      Serve complete list views in one Redis GET
Invalidated:  On every successful table sync completion via ListCache::forgetTable()
```

### Memory Bounds

Because Redis is the only cache tier, large datasets must use `sync_mode='on_demand'` to avoid unbounded growth. Recommended limits:
- `hot` mode: tables under 5,000 rows
- `warm` mode: tables under 25,000 rows
- `on_demand`: any size — only fetched records are cached

For datasets exceeding these bounds, a future PostgreSQL durability tier may be added; not in scope for v1.0.

---

## 8. ENCRYPTION ARCHITECTURE

### Per-Tenant Key Derivation
```php
// Master key from environment — never stored in database
$masterKey = base64_decode(config('relova.master_key'));

// Derive unique key per tenant using HKDF (sodium_crypto_kdf_derive_from_key)
$tenantKey = sodium_crypto_kdf_derive_from_key(
    SODIUM_CRYPTO_KDF_BYTES_MAX,
    1,                              // subkey ID
    str_pad(substr($tenantId, 0, 8), 8), // 8-byte context
    $masterKey
);

// Key is cached in PHP process memory for the request lifecycle
// NOT stored in Redis, NOT stored in database
// Cost: 2µs first call, 0.001µs subsequent calls (from process cache)
```

### Encryption per Record
```php
// AES-256-GCM via libsodium
$nonce      = random_bytes(SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);
$plaintext  = json_encode($data);
$ciphertext = sodium_crypto_aead_aes256gcm_encrypt(
    $plaintext,
    $tenantId,   // Additional authenticated data — binds ciphertext to tenant
    $nonce,
    $key
);
$stored = base64_encode($nonce . $ciphertext);

// Cost per record: 3.88µs encrypt / 3.88µs decrypt
// Cost for 50 records (list view): 0.221ms total
```

### Bulk Decryption (List Views)
```php
// Derive key ONCE per request, reuse for all records
// Never derive per-record
public function decryptMany(array $encryptedRecords, string $tenantId): array
{
    $key = $this->deriveTenantKey($tenantId); // Once
    return array_map(fn($r) => $this->decryptWithKey($r, $key, $tenantId), $encryptedRecords);
}
```

### Three Encryption Tiers (Commercial)

**Standard** — Relova-managed keys (HKDF derivation from master key)
**Enterprise** — Customer-managed keys (BYOK — customer provides key per-request via SDK)
**On-Premise Agent** — No data leaves customer network; agent runs inside customer infrastructure

---

## 9. SECURITY LAYERS

### SSRF Guard
- Called before EVERY new remote connection attempt
- Resolves hostname to IP via gethostbyname()
- Blocks: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.0/8, 169.254.0.0/16, ::1/128, fc00::/7
- Not called on pooled connection reuse (only on new connections)
- Cost: 1-5ms DNS lookup (paid once per new connection)

### Query Sanitizer
- Validates all internally-generated SQL before execution
- Blocks (with `\b` word boundaries to avoid false positives on column names like `CREATE_DATE`):
  `INTO OUTFILE`, `INTO DUMPFILE`, `COPY TO`, `xp_cmdshell`, `EXEC`, `EXECUTE`,
  `CREATE`, `DROP`, `ALTER`, `INSERT`, `UPDATE`, `DELETE`, `TRUNCATE`,
  `SLEEP(`, `BENCHMARK(`, stacked queries (`;` outside string literals)
- Enforces leading `SELECT` (after stripping whitespace and CTE `WITH` clauses)
- Cost: 21.89µs per query (15 compiled regex patterns)
- Throws `QuerySecurityException` on violation
- Defence-in-depth only — Relova never accepts raw SQL from outside

### Read-Only Enforcement (Multi-Layer)
1. Remote DB credentials must be read-only (validated at connection test)
2. Transactions opened in READ ONLY mode before every query
3. Query sanitizer blocks any write-capable SQL pattern
4. Driver-level PDO options set ATTR_ERRMODE_EXCEPTION

### Tenant Isolation
- Global Eloquent scope on all Relova models enforces tenant_id on every query
- Throws TenantContextException if tenant context not set
- Keys in Redis are namespaced: `relova:*:{tenant_id}:*`
- Encryption uses tenant_id as Additional Authenticated Data (AAD) — ciphertext is bound to its tenant

### Audit Logging
- Every data access logged to relova_audit_logs
- Logs: who accessed, what table, how many rows, which columns filtered
- Does NOT log actual data values — only metadata
- Normal access: async via WriteAuditLog job (0ms user impact)
- Security events (blocked, auth failure): synchronous (1-3ms, worth it)

### TLS Configuration
```
tls_mode options:
  'disabled'    — no TLS (dev only, blocked in production)
  'required'    — TLS enforced, certificate not verified
  'verify-ca'   — TLS + CA certificate verified
  'verify-full' — TLS + mTLS (mutual authentication, production default)
```

---

## 10. BACKGROUND JOBS

### Job Queue Configuration
```
relova-sync-priority   — First-run sync when user is waiting for skeleton
relova-sync            — Regular background refresh (every 5-30 min)
relova-audit           — Async audit log writes (lowest priority)
relova-stats           — Access count increments (lowest priority)
```

### SyncRemoteTable
- Triggered by: scheduler (every 5-30 min per config) or on cache miss
- Streams remote table in chunks of 500 rows (never loads full table into memory)
- Upserts each chunk into relova_cache_records (one query per chunk)
- After sync: marks orphaned records, invalidates Redis list pages, promotes hot records to Zone B
- Marks config as 'syncing' before start — prevents duplicate jobs
- Timeout: 300 seconds max

### RefreshCacheRecord
- Triggered by: CacheManager when a record is read with sync_status='stale'
- Fetches single record from remote, writes to Zone A and Zone B
- Timeout: 30 seconds

### HealthCheckConnector
- Triggered by: scheduler every 15 minutes
- Tests connection, updates status and last_checked_at
- Does not fetch any data

### WarmSchemaCache
- Triggered by: scheduler every hour
- Fetches table and column lists, stores in Redis with 30-minute TTL

### ValidateOrphanedReferences
- Triggered by: scheduler daily
- Checks that all VirtualEntityReference records still resolve to real remote PKs
- Marks non-resolving references as snapshot_status='unavailable'

### WriteAuditLog (async)
- Triggered by: AuditLogger::logAsync() on every data access
- Writes to relova_audit_logs
- Queued on 'relova-audit' — never blocks user requests

### IncrementAccessCount (async)
- Triggered by: CacheManager on every cache hit
- Increments an in-Redis hotness counter (`relova:stats:{tenant}:{conn}:{table}:{pk}`) with a 24h TTL
- Used for hotness scoring — determines which records are pre-warmed by next sync
- Queued on 'relova-stats' — never blocks user requests

---

## 11. SCHEDULER CONFIGURATION

```php
// In host application Console/Kernel.php or Relova's own scheduler

// Health checks — all connections
$schedule->job(new HealthCheckConnector())->everyFifteenMinutes()->onQueue('relova-sync');

// Schema cache warming
$schedule->job(new WarmSchemaCache())->hourly()->onQueue('relova-sync');

// Hot tables (equipment master, asset registry) — sync every 5 minutes
$schedule->job(new SyncRemoteTable($hotConfigId))->everyFiveMinutes()->onQueue('relova-sync');

// Warm tables (spare parts, locations, cost centers) — sync every 30 minutes
$schedule->job(new SyncRemoteTable($warmConfigId))->everyThirtyMinutes()->onQueue('relova-sync');

// Mark stale records — runs frequently, lightweight
$schedule->call(function () {
    RelovaCacheRecord::where('cached_at', '<', now()->subMinutes(30))
        ->where('sync_status', 'fresh')
        ->update(['sync_status' => 'stale']);
})->everyFiveMinutes();

// Orphan detection — daily, off-peak
$schedule->job(new ValidateOrphanedReferences())->dailyAt('03:00')->onQueue('relova-sync');
```

---

## 12. THE SDK (Host Application Interface)

The SDK is what itenance or any external customer uses. All methods are driver-agnostic.

```php
namespace Relova\SDK;

class RelovaClient
{
    // Get available tables — served from Redis schema cache
    public function getTables(string $connectionId): array;

    // Get columns for a table — served from Redis schema cache
    public function getColumns(string $connectionId, string $table): array;

    // Search for entities (asset picker) — pass-through, returns array
    public function search(
        string $connectionId,
        string $table,
        string $searchColumn,
        string $term,
        array $displayColumns,
        int $limit = 20
    ): array;

    // Select an entity — creates/retrieves VirtualEntityReference
    // Returns local UUID — this is what host app stores as FK
    public function selectEntity(
        string $tenantId,
        string $connectionId,
        string $table,
        string $pkColumn,
        string $pkValue,
        array $displayFields,
        array $displayData
    ): string;

    // Get display data for a reference — snapshot or live, never throws
    public function getDisplayData(string $referenceId, array $displayFields): array;

    // Browse a remote table — returns Generator, nothing buffered
    public function browse(
        string $connectionId,
        string $table,
        int $limit = 50,
        int $offset = 0,
        array $conditions = []
    ): \Generator;

    // Get cached list — fast path, serves from Redis or PostgreSQL
    public function list(
        string $tenantId,
        string $connectionId,
        string $remoteTable,
        array $filters = [],
        string $sortColumn = 'id',
        string $sortDirection = 'asc',
        int $perPage = 50,
        int $page = 1
    ): CacheListResult;

    // Get single cached record — fast path, Zone B → Zone A → PG → pass-through
    public function get(
        string $tenantId,
        string $connectionId,
        string $remoteTable,
        string $pkValue
    ): CacheGetResult;
}
```

---

## 13. THE CacheResult DTO

Every read returns this DTO. Host application uses `source` and `isStale` to show freshness UI.

```php
class CacheResult
{
    public readonly mixed  $data;
    public readonly string $source;     // 'redis_zone_b' | 'redis_zone_a' | 'live' | 'stale_snapshot'
    public readonly bool   $isFresh;
    public readonly ?string $cachedAt;
    public readonly bool   $isStale;
    public readonly bool   $found;
    public readonly ?string $warning;
}

// UI badge logic in host app:
// source='redis_zone_b'  + isStale=false  → 🟢 Live
// source='redis_zone_a'  + isStale=false  → 🟢 Cached
// source='live'          + isStale=false  → 🟢 Live
// source='stale_snapshot'+ isStale=true   → 🔴 Remote unavailable
```

---

## 14. SUPPORTED DRIVERS AND BUILD PHASES

### Phase 1 — Relational via PDO (implement first)
- MySQL, PostgreSQL, SQL Server
- Shared PdoDriver base, dialect-specific SQL for INFORMATION_SCHEMA
- Covers majority of customer use cases
- All architecture patterns established here

### Phase 2 — Enterprise Databases
- Oracle via yajra/laravel-oci8 (requires pdo_oci PHP extension)
- SAP HANA via PDO (requires HANA ODBC driver)
- Schema introspection via ALL_TABLES/ALL_COLUMNS (Oracle) or SYS.TABLES (HANA)

### Phase 3 — File-Based Sources
- Excel/CSV via PhpSpreadsheet (streaming reader for large files)
- Remote URL, SFTP, SharePoint as file sources
- Parsed on demand, never stored as full file
- Virtual "table" = sheet, virtual "columns" = header row
- Large files (>5MB) run as chunked background jobs

### Phase 4 — API Sources
- REST/OData endpoints mapped to table metaphor
- SAP OData, Salesforce, custom REST APIs
- Each endpoint = one virtual table
- Pagination via OData $skip/$top or Link headers

### Phase 5 — Standalone Product
- Independent branding and domain (relova.io or similar)
- Public API documentation
- Developer portal
- Independent billing and auth
- White-label management UI

---

## 15. MODULE DATA CONSUMER CONTRACT

Modules that consume Relova data implement this interface:

```php
namespace Relova\Contracts;

interface ModuleDataConsumer
{
    // What fields this module wants from the remote source
    public function expectedFields(): array;

    // Fields that must be present for the module to function
    public function requiredFields(): array;

    // What to do with a mapped record (upsert to local module table, etc.)
    public function consume(array $record): void;

    // Unique key for this module ('assets', 'tickets', 'inventory', etc.)
    public function moduleKey(): string;
}
```

---

## 16. PERFORMANCE BENCHMARKS (ACTUAL MEASURED)

All measurements on commodity hardware. Use these as acceptance criteria.

```
Operation                              Target      Measured
──────────────────────────────────────────────────────────
Zone B Redis hit (show view)           <1ms        0.31ms
Zone A Redis hit (show view)           <2ms        0.92ms
List view, Redis pre-built page        <1ms        0.32ms
List view, pass-through (cold)         <500ms      ~150ms
JSON encode+decode per record          <50µs       6.98µs
AES-256 encrypt per record             <10µs       3.88µs
Decrypt 50 records (list view)         <1ms        0.221ms
Key derivation (once per request)      <10µs       2.10µs
Query sanitizer (15 patterns)          <100µs      21.89µs
Cache key generation                   <10µs       3.17µs
Full security overhead on cached read  <0.5ms      <0.3ms
──────────────────────────────────────────────────────────
Remote (Oracle on-prem, pooled conn):
  Single record fetch                  <50ms       ~11ms
  Search 20 results                    <100ms      ~23ms
  500-row sync chunk (background)      <500ms      ~321ms
Remote (SAP HANA cloud, pooled):
  Single record fetch                  <100ms      ~25ms
  Search 20 results                    <100ms      ~32ms
Excel/SharePoint (background only):
  500-row file                         <500ms      ~80ms
  5000-row file                        <2000ms     ~666ms
```

---

## 17. ITENANCE-SPECIFIC INTEGRATION NOTES

itenance is one of Relova's consumers. It uses Relova identically to any external customer. These are the specific integration points:

### Equipment as Foundation Layer
- Equipment (the physical entity) is always present in itenance regardless of active modules
- Equipment can be a local record OR a VirtualEntityReference from Relova
- All modules (Tickets, Schedules, Inspections) reference equipment — never assets directly
- Assets module extends equipment with financial/lifecycle data (optional module)

### Module-to-Connector Field Mappings
```
Module Key    Remote Table Examples    Required Fields          Optional Fields
──────────────────────────────────────────────────────────────────────────────
assets        EQUIPMENT, ASSET         name, code               location, category, install_date
tickets       EQUIPMENT                name, code               location, status
inventory     MATERIAL, SPARE_PARTS    name, code, unit         quantity, storage_location
schedules     EQUIPMENT, WORK_CENTERS  name, code               location, type
```

### Sync Behavior Recommendations per Module
```
Module        Recommended Sync Mode    Rationale
──────────────────────────────────────────────────────────────────────────────
assets        hot                      Frequently browsed, rarely changes
tickets       warm                     Accessed per work order, moderate frequency
inventory     warm                     Updated daily, moderate access
schedules     on_demand                Accessed for specific planning windows
```

### Asset Picker Component (Livewire)
Uses `RelovaClient::search()` — pass-through, live results as user types.
On selection: calls `RelovaClient::selectEntity()` — creates VirtualEntityReference.
Ticket stores `virtual_entity_reference_id` (UUID FK) — never the remote PK directly.

---

## 18. IMPLEMENTATION RULES FOR AI

When implementing any part of Relova, the following rules are absolute:

1. **Never write remote row data to the database.** Only schema metadata (Redis, TTL-expiring) and display snapshots (minimal JSONB on VirtualEntityReference) may be stored from remote sources.

2. **Always use Generator for remote query results.** Never `fetchAll()`. Stream rows one at a time.

3. **Never accept raw SQL from outside the package.** Build all SQL internally in QueryExecutor. External callers pass table, columns, conditions as structured parameters.

4. **Always pass through SsrfGuard before a new connection.** Not on pooled reuse — only on new connection establishment.

5. **Always derive encryption keys per-tenant.** Never use a shared key. Cache the derived key in PHP process memory for the request duration.

6. **Always enforce read-only at transaction level.** Do not trust that credentials are read-only — also wrap queries in READ ONLY transactions.

7. **Never throw on remote connection failure in user-facing paths.** SnapshotManager and CacheManager must always return something — stale data with a warning is always better than an exception.

8. **Always dispatch audit logs async.** Never synchronously in the request path except for security-critical events (blocked attempts, auth failures).

9. **Always use Laravel's Cache facade (not Redis directly).** This ensures the array driver works identically in development.

10. **Never write a row from a remote source into a PostgreSQL table.** Cache lives only in Redis. PostgreSQL stores configuration, references, sync state, and audit logs — never row data.

11. **The cache is best-effort, not authoritative.** On a cold cache the code MUST pass through to the remote source and write the result back to Redis before returning. Users see a slower response, never an empty list.

12. **Always include tenant_id in every Relova model query.** The TenantIsolationGuard global scope enforces this but every query should also pass tenant_id explicitly as a safety check.

13. **Zone B Redis TTL is 60 seconds (plaintext). Zone A Redis TTL is 1800 seconds (encrypted). List page TTL is 300 seconds.** Do not change these without understanding the impact on memory usage and staleness.

14. **The array cache driver is a first-class citizen.** All code must work identically with `CACHE_DRIVER=array` and `QUEUE_CONNECTION=sync`. This is the development environment and no special-casing for it is acceptable.

15. **Background jobs must never impact user-facing request latency.** Everything that can be deferred to a queue must be deferred. The user request returns, then the job runs.

---

## 19. CONFIGURATION REFERENCE

```php
// config/relova.php
return [
    // Cache drivers — 'array' in dev, 'redis' in prod
    'hot_cache'        => env('RELOVA_HOT_CACHE_DRIVER', 'array'),
    'persistent_cache' => env('RELOVA_PERSISTENT_CACHE_DRIVER', 'array'),

    // Encryption — 32-byte key, base64 encoded
    // Generate: base64_encode(random_bytes(32))
    'master_key'       => env('RELOVA_MASTER_KEY'),

    // Queue — 'sync' in dev, queue name in prod
    'sync_queue'          => env('RELOVA_SYNC_QUEUE', 'sync'),
    'audit_queue'         => env('RELOVA_AUDIT_QUEUE', 'sync'),
    'stats_queue'         => env('RELOVA_STATS_QUEUE', 'sync'),

    // Timeouts
    'connection_timeout'  => env('RELOVA_CONNECTION_TIMEOUT', 10),  // seconds
    'query_timeout'       => env('RELOVA_QUERY_TIMEOUT', 30),       // seconds
    'sync_timeout'        => env('RELOVA_SYNC_TIMEOUT', 300),       // seconds

    // Cache TTLs (seconds)
    'zone_b_ttl'          => 60,
    'zone_a_ttl'          => 1800,
    'list_page_ttl'       => 300,
    'schema_cache_ttl'    => 1800,
    'snapshot_fresh_ttl'  => 1800,   // When snapshot is considered stale

    // Sync chunk size
    'sync_chunk_size'     => 500,

    // SSRF blocked ranges (add customer-specific ranges here if needed)
    'ssrf_blocked_ranges' => [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '169.254.0.0/16',
    ],

    // TLS default
    'default_tls_mode'   => env('RELOVA_DEFAULT_TLS_MODE', 'verify-full'),

    // API authentication
    'api_key_header'     => 'X-Relova-Key',
];
```

---

## 20. WHAT SUCCESS LOOKS LIKE

A correctly implemented Relova satisfies all of these:

- A list view of 50 remote assets loads in under 1ms after first sync (Redis pre-built page hit)
- A show view of a single remote asset loads in under 1ms (Zone B Redis hit) or under 2ms on Zone A hit (encrypted)
- A remote system going offline does not cause any user-visible errors — stale cache is served with a warning badge
- Two tenants' data is completely invisible to each other at every layer (Redis keys, PostgreSQL rows, encryption keys)
- A Relova staff member with full PostgreSQL access cannot read tenant data (encrypted at rest, key not in DB)
- Running with `CACHE_DRIVER=array` and `QUEUE_CONNECTION=sync` produces identical functional results to production Redis/queue
- All background sync jobs complete without blocking any user request
- The audit log contains a record of every data access with actor, table, row count, and timestamp — but no actual data values
- Adding a new driver requires only implementing ConnectorDriver — no other files change

---

## 21. DEPLOYMENT NOTES (Laravel Forge + DigitalOcean)

Relova runs cleanly on Laravel Forge with a DigitalOcean droplet plus DigitalOcean Managed PostgreSQL and Managed Redis. The following operational steps are required:

### Server provisioning (Forge)
- **PHP 8.3** with `ext-sodium` (default on Ubuntu 22.04+, no action needed)
- **`phpredis` extension** — install via Forge's PHP Extensions panel or deploy script:
  `sudo apt-get install -y php8.3-redis && sudo service php8.3-fpm restart`
- **Scheduler** — Forge auto-adds the `* * * * * php artisan schedule:run` cron entry; no extra setup
- **Queue daemons** — create one Forge Daemon per queue (or use Horizon to manage all four):
  - `php artisan queue:work redis --queue=relova-sync-priority --tries=3 --timeout=300`
  - `php artisan queue:work redis --queue=relova-sync --tries=3 --timeout=300`
  - `php artisan queue:work redis --queue=relova-audit --tries=5 --timeout=30`
  - `php artisan queue:work redis --queue=relova-stats --tries=1 --timeout=30`

### DigitalOcean Managed Redis
- TLS is enforced — set `REDIS_SCHEME=tls` and the appropriate CA-bundle path in `config/database.php`
- Use the connection string from the DO control panel (includes username, password, port 25061)
- Do NOT enable persistence (RDB/AOF) for Relova's cache — TTL-driven volatility is intentional

### DigitalOcean Managed PostgreSQL
- Cluster user must have `CREATE SCHEMA` privilege (default for the admin user) so Stancl Tenancy can create per-tenant schemas
- Stores ONLY: `relova_connections`, `relova_virtual_entity_references`, `relova_connector_module_mappings`, `relova_sync_configs`, `relova_audit_logs` — small footprint, no row-data growth
- Daily automated backups are sufficient (no row data to lose)

### Environment variables (Forge → Site → Environment)
```
RELOVA_HOT_CACHE_DRIVER=redis
RELOVA_PERSISTENT_CACHE_DRIVER=redis
RELOVA_SYNC_QUEUE=relova-sync
RELOVA_AUDIT_QUEUE=relova-audit
RELOVA_STATS_QUEUE=relova-stats
RELOVA_MASTER_KEY=<base64_encode(random_bytes(32)) — set via Forge UI, never committed>
RELOVA_DEFAULT_TLS_MODE=verify-full
```

### Connection pooling reality on PHP-FPM
- PHP-FPM has no shared memory across requests — each FPM worker maintains its own remote DB connections
- `PDO::ATTR_PERSISTENT=true` provides per-worker connection persistence (sufficient for typical loads)
- For very high concurrency to a single remote PostgreSQL, run **PgBouncer** as a sidecar on the droplet pointing at the remote DB; configure Relova's connection to point at the local PgBouncer

### Monitoring
- Audit log writes go to the `relova-audit` queue — monitor queue depth via Horizon or Forge's queue monitoring
- Health check job runs every 15 minutes — failures surface in `relova_connections.status='error'`
- Redis memory: alert at 75% of plan capacity; Zone A's 30-min TTL bounds the working set naturally

---

*End of Relova Copilot Specification v1.0*
*This document is the single source of truth for AI-assisted Relova implementation.*
*When in doubt, consult the rules in Section 18.*
