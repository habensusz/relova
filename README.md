# Relova — Universal Data Connector Platform

> Virtual-first data federation for Laravel applications. Connect remote databases, browse schemas, run read-only queries, and map fields — all without moving data.

---

## What Is Relova?

Relova is a Laravel package that lets your application **connect to and query external databases** as if they were local. Instead of importing data, Relova federates it in place — the remote data stays where it is, and your app fetches what it needs on demand.

**Core capabilities:**

- Connect to MySQL, PostgreSQL, and SQL Server sources (extensible via custom drivers)
- Browse remote schemas (tables, columns, types) with automatic caching
- Execute read-only queries with per-connection timeouts and row limits
- Map remote fields to your local entities via `EntityReference` and `FieldMapping` models
- SSRF protection blocking connections to internal/reserved IP ranges
- REST API with API-key authentication for external consumers
- Livewire UI components for connection management, schema browsing, and field mapping
- Background health checks and schema snapshot jobs

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.2` |
| Laravel | `^11.0` or `^12.0` |

---

## Installation

### Option A — Composer (from GitHub)

Add the repository and require the package in your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/habensusz/relova.git"
        }
    ],
    "require": {
        "relova/core": "^1.0"
    }
}
```

Then install:

```bash
composer install
```

> **Private repo?** Generate a personal access token on GitHub with `repo` scope and either add it to `~/.composer/auth.json` or use `composer config --global github-oauth.github.com <token>`.

### Option B — Local Path Repository (monorepo / development)

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/relova",
            "options": { "symlink": true }
        }
    ],
    "require": {
        "relova/core": "^1.0"
    }
}
```

```bash
composer require relova/core
```

---

## Setup

### 1. Publish the config

```bash
php artisan vendor:publish --tag=relova-config
```

This creates `config/relova.php`.

### 2. Run migrations

**Central (non-tenanted) app:**

```bash
php artisan migrate
```

**Multi-tenant app (stancl/tenancy):**

The Relova tables are tenant-scoped. Run the migrations against every tenant:

```bash
php artisan tenants:migrate --path=packages/relova/database/migrations
```

Or, to keep it automatic in future `tenants:migrate` runs, copy the migrations into your tenant migration path:

```bash
cp packages/relova/database/migrations/* database/migrations/tenant/
php artisan tenants:migrate
```

### 3. Add env variables (optional overrides)

```env
RELOVA_SCHEMA_CACHE_TTL=300
RELOVA_QUERY_TIMEOUT=30
RELOVA_CONNECTION_TIMEOUT=10
RELOVA_MAX_ROWS=10000
RELOVA_PAGE_SIZE=100
RELOVA_SNAPSHOT_REFRESH=86400
RELOVA_HEALTH_CHECK_INTERVAL=300
```

---

## Configuration

`config/relova.php` exposes all tunables.

```php
return [
    // Seconds schema metadata (tables, columns) is cached
    'schema_cache_ttl' => env('RELOVA_SCHEMA_CACHE_TTL', 300),

    // Max seconds a remote query may run before being killed
    'query_timeout' => env('RELOVA_QUERY_TIMEOUT', 30),

    // Hard cap on rows returned per query
    'max_rows_per_query' => env('RELOVA_MAX_ROWS', 10000),

    // Default page size for paginated queries
    'default_page_size' => env('RELOVA_PAGE_SIZE', 100),

    // Health check interval in seconds (background job)
    'health_check_interval' => env('RELOVA_HEALTH_CHECK_INTERVAL', 300),

    // Prefix for all Relova database tables  
    'table_prefix' => 'relova_',

    // Registered connector drivers
    'drivers' => [
        'mysql'  => \Relova\Drivers\MySqlDriver::class,
        'pgsql'  => \Relova\Drivers\PostgreSqlDriver::class,
        'sqlsrv' => \Relova\Drivers\SqlServerDriver::class,
    ],

    // SSRF protection — blocked IP ranges (CIDR)
    'blocked_ip_ranges' => [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '::1/128',
        'fc00::/7',
    ],

    // API configuration
    'api' => [
        'prefix'       => 'api/relova/v1',
        'middleware'   => ['api'],
        'internal_key' => env('RELOVA_INTERNAL_API_KEY'),
    ],
];
```

---

## Architecture

```
relova/
├── config/
│   └── relova.php                  # All configuration
├── database/
│   └── migrations/
│       ├── ..._create_relova_connections_table.php
│       ├── ..._create_relova_entity_references_table.php
│       ├── ..._create_relova_field_mappings_table.php
│       └── ..._create_relova_api_keys_table.php
├── routes/
│   └── api.php                     # REST API routes (auto-loaded)
└── src/
    ├── RelovaServiceProvider.php
    ├── Contracts/
    │   ├── ConnectionManager.php   # Interface: connect, test, getTables, query…
    │   └── ConnectorDriver.php     # Interface: every driver must implement this
    ├── Drivers/
    │   ├── AbstractPdoDriver.php   # Shared PDO logic (query, columns, security)
    │   ├── MySqlDriver.php
    │   ├── PostgreSqlDriver.php
    │   └── SqlServerDriver.php
    ├── Exceptions/
    │   ├── ConnectionException.php
    │   ├── QueryException.php
    │   ├── ReadOnlyViolationException.php
    │   └── SsrfException.php
    ├── Facades/
    │   └── Relova.php
    ├── Http/
    │   ├── Controllers/Api/        # REST controllers
    │   └── Middleware/
    │       └── RelovaApiAuth.php   # API key auth middleware
    ├── Jobs/
    │   ├── ConnectionHealthCheckJob.php
    │   └── RefreshSnapshotsJob.php
    ├── Models/
    │   ├── RelovaConnection.php       # A configured remote source
    │   ├── RelovaEntityReference.php  # A remote table/entity pinned locally
    │   ├── RelovaFieldMapping.php     # Column-to-column field map
    │   └── RelovaApiKey.php          # API key for external consumers
    ├── Sdk/
    │   └── RelovaClient.php           # HTTP client wrapping the REST API
    └── Services/
        ├── DriverRegistry.php         # Resolves driver by type string
        ├── EntityReferenceService.php # Resolve / search entity references
        ├── RelovaConnectionManager.php # Central manager (open, cache, health)
        └── SecurityService.php        # SSRF host validation
```

---

## Core Concepts

### Connection (`RelovaConnection`)

A connection is a saved configuration pointing at a remote database — host, port, credentials (encrypted at rest), driver type. Connections are not kept open; they are opened on demand, used, and closed.

```php
$connection = RelovaConnection::create([
    'name'        => 'Production ERP',
    'driver_type' => 'mysql',
    'host'        => 'erp.internal.example.com',
    'port'        => 3306,
    'database_name' => 'erp_prod',
    'username'    => 'reader',
    'password'    => 'secret',   // encrypted via Crypt before storage
    'enabled'     => true,
]);
```

Health statuses: `unknown` → `healthy` / `degraded` / `unhealthy`

Query modes: `virtual` (always live), `snapshot` (cached copy), `on_demand`

### ConnectorDriver

Every database type is a driver implementing `Relova\Contracts\ConnectorDriver`:

```php
interface ConnectorDriver
{
    public function getDriverName(): string;
    public function getDisplayName(): string;
    public function testConnection(array $config): bool;
    public function getTables(array $config): array;
    public function getColumns(array $config, string $table): array;
    public function query(array $config, string $sql, array $bindings = []): array;
    public function healthCheck(array $config): array;
}
```

All queries are read-only — any write statement throws `ReadOnlyViolationException`.

### ConnectionManager

Injected via the `Relova\Contracts\ConnectionManager` contract:

```php
use Relova\Contracts\ConnectionManager;

class MyService
{
    public function __construct(protected ConnectionManager $relova) {}

    public function getProducts(RelovaConnection $conn): array
    {
        return $this->relova->query($conn, 'SELECT * FROM products WHERE active = ?', [1]);
    }
}
```

Available methods:

| Method | Description |
|---|---|
| `connect($connection)` | Returns the resolved driver (validates host first) |
| `test($connection)` | Tests connectivity and updates health status |
| `getTables($connection)` | Returns table list (cached by TTL) |
| `getColumns($connection, $table)` | Returns column definitions (cached) |
| `query($connection, $sql, $bindings)` | Runs a read-only query |
| `flushCache($connection)` | Clears schema cache for the connection |
| `healthCheck($connection)` | Runs a full health check |

### EntityReference (`RelovaEntityReference`)

Pins a specific remote table to a local context — enabling display snapshots, cross-system virtual joins, and field-level mapping.

### FieldMapping (`RelovaFieldMapping`)

Maps a remote column to a local model field, with optional transformation rules.

---

## Security

### Credential Encryption

Passwords are encrypted using Laravel's `Crypt::encryptString()` before storage and decrypted transparently via the `password` accessor. The `encrypted_password` column is always hidden from serialization. SSH credentials (password, private key, passphrase) are similarly encrypted.

### SSH Tunnel Support

Relova can reach databases that are not publicly accessible (e.g. Laravel Forge servers where PostgreSQL is bound to `localhost` only) via an SSH port-forward tunnel.

**How it works:**
1. Before each operation, `SshTunnelService` opens an SSH tunnel: `ssh -N -L {localPort}:{dbHost}:{dbPort} {sshUser}@{sshHost}`
2. The driver config is redirected to `127.0.0.1:{localPort}`
3. The PDO connection is made through the tunnel
4. After the operation completes, the tunnel is torn down

**Configuration (UI form):** Enable the *SSH Tunnel* section in the connection form and fill in:

| Field | Description |
|---|---|
| SSH Host | The SSH/bastion server IP or hostname. Leave blank to use the DB host. |
| SSH Port | Default `22` |
| SSH User | e.g. `forge` |
| Auth Method | `Private Key` (recommended) or `Password` |
| Private Key | PEM-formatted private key content (stored encrypted) |
| Key Passphrase | Optional passphrase for the private key |
| SSH Password | Password-based auth (requires `sshpass` on Linux) |

**Requirements:**
- `ssh` binary must be installed and in `PATH` on the app server
- For password auth: `sshpass` must be installed (`apt install sshpass`)
- For key auth: works cross-platform (Linux, macOS, Windows with OpenSSH)

**Forge server example:**

1. Get your server's public IP from Forge (e.g. `129.212.253.105`)
2. In Relova's connection form:
   - Host: `localhost` (the DB host as seen from *inside* the server)
   - Port: `5432`
   - SSH Host: `129.212.253.105`
   - SSH User: `forge`
   - Auth Method: Private Key
   - Private Key: paste the private key that has access to the Forge server
3. Test the connection — Relova will SSH in and forward the port

### SSRF Protection

Before any connection attempt, `SecurityService::validateHost()` resolves the hostname and checks every resolved IP against the blocked ranges configured in `relova.blocked_ip_ranges`. Connections to `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `127.0.0.0/8`, and other private/reserved ranges throw `SsrfException`.

To **allow** internal hosts (e.g. a trusted internal DB in a private network), remove the relevant CIDR range from config:

```php
// config/relova.php
'blocked_ip_ranges' => [
    // Remove '10.0.0.0/8' if you need to allow 10.x.x.x hosts
    '172.16.0.0/12',
    '192.168.0.0/16',
    '127.0.0.0/8',
],
```

### Read-Only Enforcement

All query methods validate SQL does not contain `INSERT`, `UPDATE`, `DELETE`, `DROP`, `TRUNCATE`, `ALTER`, `CREATE`, or `GRANT`. Any violation throws `ReadOnlyViolationException` before the query is sent.

### API Key Auth

The REST API uses `RelovaApiAuth` middleware. API keys are stored in the `relova_api_keys` table (hashed). Generate keys via the UI or directly:

```php
RelovaApiKey::create([
    'name'    => 'My integration',
    'key'     => hash('sha256', $rawKey = Str::random(64)),
    'enabled' => true,
]);
```

The `Authorization: Bearer <raw-key>` header is required on all API requests.

---

## REST API

All routes carry the prefix `/api/relova/v1` (configurable). Authentication via `Authorization: Bearer <api-key>`.

### Connections

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/connections` | List all connections |
| `POST` | `/connections` | Create a connection |
| `GET` | `/connections/{uid}` | Get a connection |
| `PUT` | `/connections/{uid}` | Update a connection |
| `DELETE` | `/connections/{uid}` | Delete a connection |
| `POST` | `/connections/{uid}/test` | Test connectivity |
| `GET` | `/connections/{uid}/health` | Health check |

### Schema

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/connections/{uid}/tables` | List remote tables |
| `GET` | `/connections/{uid}/tables/{table}/columns` | List columns |
| `GET` | `/connections/{uid}/tables/{table}/preview` | Preview first N rows |
| `POST` | `/connections/{uid}/flush-cache` | Flush schema cache |

### Query

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/connections/{uid}/query` | Execute SQL (read-only) |
| `POST` | `/connections/{uid}/select` | Structured SELECT builder |

### Entity References

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/connections/{uid}/references` | List references |
| `POST` | `/connections/{uid}/references/resolve` | Resolve a reference |
| `POST` | `/connections/{uid}/references/search` | Search references |
| `GET` | `/connections/{uid}/references/{ref}` | Get reference |
| `POST` | `/connections/{uid}/references/{ref}/refresh` | Refresh snapshot |

### Field Mappings

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/field-mappings` | List mappings |
| `POST` | `/field-mappings` | Create mapping |
| `GET` | `/field-mappings/{uid}` | Get mapping |
| `PUT` | `/field-mappings/{uid}` | Update mapping |
| `DELETE` | `/field-mappings/{uid}` | Delete mapping |

---

## SDK Client

The `RelovaClient` wraps the REST API for use in PHP:

```php
use Relova\Sdk\RelovaClient;

// From config (internal use, same app)
$client = RelovaClient::fromConfig();

// From explicit values (external consumer)
$client = new RelovaClient(
    baseUrl: 'https://yourapp.com/api/relova/v1',
    apiKey: 'your-api-key',
    timeout: 30,
);

// List all connections
$connections = $client->listConnections();

// Test a connection
$result = $client->testConnection($uid);

// List tables from a remote source
$tables = $client->getTables($connectionUid);

// Get columns for a remote table
$columns = $client->getColumns($connectionUid, 'orders');

// Run a read-only query
$rows = $client->executeQuery($connectionUid, 'SELECT * FROM orders LIMIT 10');

// List field mappings
$mappings = $client->listFieldMappings();
```

---

## Writing a Custom Driver

Implement `Relova\Contracts\ConnectorDriver`:

```php
namespace App\Relova\Drivers;

use Relova\Contracts\ConnectorDriver;
use Relova\Exceptions\ConnectionException;

class OracleDriver implements ConnectorDriver
{
    public function getDriverName(): string { return 'oracle'; }
    public function getDisplayName(): string { return 'Oracle Database'; }

    public function testConnection(array $config): bool
    {
        // Attempt connection, throw ConnectionException on failure
    }

    public function getTables(array $config): array
    {
        // Return: [['name' => 'orders', 'schema' => null, 'type' => 'TABLE', 'row_count' => null], ...]
    }

    public function getColumns(array $config, string $table): array
    {
        // Return: [['name' => 'id', 'type' => 'NUMBER', 'nullable' => false, 'default' => null, 'primary' => true, 'length' => null], ...]
    }

    public function query(array $config, string $sql, array $bindings = []): array
    {
        // Return: [['id' => 1, 'name' => 'Widget'], ...]
    }

    public function healthCheck(array $config): array
    {
        return ['status' => 'healthy', 'latency_ms' => 12];
    }
}
```

Register it in `config/relova.php`:

```php
'drivers' => [
    'mysql'  => \Relova\Drivers\MySqlDriver::class,
    'pgsql'  => \Relova\Drivers\PostgreSqlDriver::class,
    'sqlsrv' => \Relova\Drivers\SqlServerDriver::class,
    'oracle' => \App\Relova\Drivers\OracleDriver::class, // ← custom
],
```

Or register at runtime (e.g. in a service provider):

```php
use Relova\Services\DriverRegistry;

app(DriverRegistry::class)->register('oracle', OracleDriver::class);
```

---

## Background Jobs

Two jobs handle async maintenance tasks:

### `ConnectionHealthCheckJob`

Runs health checks on all enabled connections. Schedule it in your `console/Kernel.php` or `routes/console.php`:

```php
Schedule::job(new ConnectionHealthCheckJob)->everyFiveMinutes();
```

### `RefreshSnapshotsJob`

Refreshes display snapshots for entity references that have exceeded `relova.snapshot_refresh_interval`. Schedule it:

```php
Schedule::job(new RefreshSnapshotsJob)->hourly();
```

---

## Multi-Tenancy (stancl/tenancy)

Relova tables are tenant-scoped (no shared central data). When using `stancl/tenancy`:

**Initial migration across all tenants:**

```bash
php artisan tenants:migrate --path=packages/relova/database/migrations
```

**To include Relova automatically in every future `tenants:migrate`**, copy migrations into the tenant migration path:

```bash
cp packages/relova/database/migrations/* database/migrations/tenant/
```

Then future runs of `php artisan tenants:migrate` will include them automatically.

**Running against a single tenant:**

```bash
php artisan tenants:migrate --tenants=tenant-uuid-here --path=packages/relova/database/migrations
```

---

## UI (Livewire Components)

When installed inside a Laravel app, the following Livewire components are available. Register routes pointing to them:

```php
// routes/tenant.php (or web.php)
Route::middleware(['auth'])->prefix('relova')->name('relova.')->group(function () {
    Route::get('/connections', \App\Livewire\Relova\ConnectionManager::class)->name('connections');
    Route::get('/schema', \App\Livewire\Relova\SchemaBrowser::class)->name('schema');
    Route::get('/schema/{connectionUid}', \App\Livewire\Relova\SchemaBrowser::class)->name('schema.connection');
    Route::get('/mappings', \App\Livewire\Relova\FieldMappingEditor::class)->name('mappings');
});
```

| Component | Route | Description |
|---|---|---|
| `ConnectionManager` | `/relova/connections` | Create, edit, test, and delete connections |
| `SchemaBrowser` | `/relova/schema` | Browse remote tables and columns |
| `FieldMappingEditor` | `/relova/mappings` | Define and manage field mappings |

---

## Exceptions

| Exception | When thrown |
|---|---|
| `ConnectionException` | Driver failed to open a connection |
| `QueryException` | Remote query execution failed |
| `ReadOnlyViolationException` | SQL contained a write statement |
| `SsrfException` | Host resolves to a blocked IP range |

All extend `\RuntimeException` and carry contextual details in their constructor.

---

## Facade

```php
use Relova\Facades\Relova;

// Shorthand for ConnectionManager methods
Relova::test($connection);
Relova::getTables($connection);
Relova::query($connection, 'SELECT 1');
```

---

## Testing

The package ships with unit and feature tests (Pest). To run them inside the host application:

```bash
php artisan test tests/Feature/Relova/
php artisan test tests/Unit/Relova/
```

SQLite in-memory is used for tests. PostgreSQL-specific migrations are guarded automatically.

---

## Changelog

### v1.1.0 (2026-03-02)

- **SSH Tunnel support** — connections to private/Forge servers via SSH port-forward
  - Key-based auth (recommended, cross-platform)
  - Password-based auth (Linux + `sshpass`)
  - Private key content stored encrypted at rest
  - `SshTunnelService` manages tunnel lifecycle (open → query → close)
  - UI form: collapsible SSH Tunnel section with live auth method toggle

### v1.0.0 (2026-03-02)

- Initial release
- MySQL, PostgreSQL, SQL Server drivers
- REST API with API-key auth
- Schema cache with configurable TTL
- SSRF protection
- EntityReference and FieldMapping models
- Background health check and snapshot refresh jobs
- SDK client (`RelovaClient`)
- Livewire UI components

---

## License

Proprietary. All rights reserved.
