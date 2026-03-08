<?php

declare(strict_types=1);

namespace Relova\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Introspects the HOST application's own database schema.
 *
 * Handles schema/database scoping for all common driver + multi-tenancy
 * strategy combinations that stancl/tenancy supports:
 *
 *  ┌──────────────┬─────────────────────────────────────┬────────────────────────────────────────┐
 *  │ Driver       │ Tenancy isolation                   │ Scope used                             │
 *  ├──────────────┼─────────────────────────────────────┼────────────────────────────────────────┤
 *  │ pgsql        │ PostgreSQLSchemaManager             │ current_schema() — set by search_path  │
 *  │              │ (schema per tenant, same DB)        │                                        │
 *  │ pgsql        │ PostgreSQLDatabaseManager           │ current_schema() still correct; info-  │
 *  │              │ (separate DB per tenant)            │ rmation_schema is already per-DB       │
 *  │ mysql        │ MySQLDatabaseManager                │ DATABASE() — connection is switched    │
 *  │              │ (separate DB per tenant)            │ to the tenant DB                       │
 *  │ sqlsrv       │ MSSQLDatabaseManager                │ DB_NAME() + SCHEMA_NAME() — connection │
 *  │              │ (separate DB per tenant)            │ is switched; schema is typically dbo   │
 *  │ sqlite       │ SQLiteDatabaseManager               │ Separate file per tenant — no          │
 *  │              │ (separate file per tenant)          │ filtering required at all              │
 *  └──────────────┴─────────────────────────────────────┴────────────────────────────────────────┘
 */
class HostSchemaService
{
    /**
     * Return all user tables scoped to the current tenant context.
     *
     * @return array<int, array{name: string}>
     */
    public function getTables(): array
    {
        return match (DB::getDriverName()) {
            'pgsql' => $this->pgsqlTables(),
            'mysql', 'mariadb' => $this->mysqlTables(),
            'sqlsrv' => $this->sqlsrvTables(),
            default => $this->schemaFacadeTables(), // sqlite and anything future
        };
    }

    /**
     * Return all columns for a given host table scoped to the current tenant.
     *
     * Each entry contains:
     *   name        — column name
     *   type        — data type
     *   nullable    — can the column be NULL?
     *   has_default — does the column have a default value (including sequences)?
     *   required    — true when NOT NULL, no default, and not an auto-managed column.
     *                 These are the fields that MUST be covered by a mapping.
     *
     * @return array<int, array{name: string, type: string, nullable: bool, has_default: bool, required: bool}>
     */
    public function getColumns(string $table): array
    {
        return match (DB::getDriverName()) {
            'pgsql' => $this->pgsqlColumns($table),
            'mysql', 'mariadb' => $this->mysqlColumns($table),
            'sqlsrv' => $this->sqlsrvColumns($table),
            default => $this->schemaFacadeColumns($table), // sqlite
        };
    }

    /**
     * Columns that are managed automatically by the framework / DB and should
     * never be flagged as "required to map" even when NOT NULL without a default.
     */
    private const AUTO_COLUMNS = [
        'id', 'uid', 'created_at', 'updated_at', 'deleted_at',
        'tenant_id', 'premises_id',
    ];

    /**
     * Build the normalised column entry array.
     */
    private function normaliseColumn(string $name, string $type, bool $nullable, ?string $default): array
    {
        $hasDefault = $default !== null;

        $required = ! $nullable
            && ! $hasDefault
            && ! in_array($name, self::AUTO_COLUMNS, true)
            && ! str_ends_with($name, '_at');   // any timestamp-ish column

        return [
            'name' => $name,
            'type' => $type,
            'nullable' => $nullable,
            'has_default' => $hasDefault,
            'required' => $required,
        ];
    }

    // ─── PostgreSQL ───────────────────────────────────────────────────────────
    // current_schema() reflects the active search_path.
    // With PostgreSQLSchemaManager it returns the tenant schema (e.g. tenantabc).
    // With PostgreSQLDatabaseManager it returns 'public' inside the tenant DB.

    /** @return array<int, array{name: string}> */
    private function pgsqlTables(): array
    {
        $rows = DB::select(
            "SELECT table_name AS name
             FROM information_schema.tables
             WHERE table_schema = current_schema()
               AND table_type = 'BASE TABLE'
             ORDER BY table_name"
        );

        return array_map(fn (object $r) => ['name' => $r->name], $rows);
    }

    /** @return array<int, array{name: string, type: string, nullable: bool, has_default: bool, required: bool}> */
    private function pgsqlColumns(string $table): array
    {
        $rows = DB::select(
            "SELECT column_name AS name, data_type AS type, is_nullable, column_default
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name = ?
             ORDER BY ordinal_position",
            [$table]
        );

        return array_map(
            fn (object $r) => $this->normaliseColumn(
                $r->name,
                $r->type,
                strtoupper($r->is_nullable) === 'YES',
                $r->column_default
            ),
            $rows
        );
    }

    // ─── MySQL / MariaDB ──────────────────────────────────────────────────────
    // MySQLDatabaseManager switches the DB connection to the tenant database
    // before each request, so DATABASE() already returns the correct tenant DB.

    /** @return array<int, array{name: string}> */
    private function mysqlTables(): array
    {
        $rows = DB::select(
            "SELECT table_name AS name
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_type = 'BASE TABLE'
             ORDER BY table_name"
        );

        return array_map(fn (object $r) => ['name' => $r->name], $rows);
    }

    /** @return array<int, array{name: string, type: string, nullable: bool, has_default: bool, required: bool}> */
    private function mysqlColumns(string $table): array
    {
        $rows = DB::select(
            "SELECT column_name AS name, data_type AS type, is_nullable, column_default
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
             ORDER BY ordinal_position",
            [$table]
        );

        return array_map(
            fn (object $r) => $this->normaliseColumn(
                $r->name,
                $r->type,
                strtoupper($r->is_nullable) === 'YES',
                $r->column_default
            ),
            $rows
        );
    }

    // ─── SQL Server ───────────────────────────────────────────────────────────
    // MSSQLDatabaseManager switches the connection to the tenant database.
    // DB_NAME() returns the current DB; SCHEMA_NAME() returns the default
    // schema for the current user (typically 'dbo').
    // information_schema in SQL Server is already scoped to the current DB,
    // so the DB_NAME() filter is redundant but makes the intent explicit.

    /** @return array<int, array{name: string}> */
    private function sqlsrvTables(): array
    {
        $rows = DB::select(
            "SELECT t.table_name AS name
             FROM information_schema.tables t
             WHERE t.table_catalog = DB_NAME()
               AND t.table_schema = SCHEMA_NAME()
               AND t.table_type = 'BASE TABLE'
             ORDER BY t.table_name"
        );

        return array_map(fn (object $r) => ['name' => $r->name], $rows);
    }

    /** @return array<int, array{name: string, type: string, nullable: bool, has_default: bool, required: bool}> */
    private function sqlsrvColumns(string $table): array
    {
        $rows = DB::select(
            "SELECT c.column_name AS name, c.data_type AS type, c.is_nullable, c.column_default
             FROM information_schema.columns c
             WHERE c.table_catalog = DB_NAME()
               AND c.table_schema = SCHEMA_NAME()
               AND c.table_name = ?
             ORDER BY c.ordinal_position",
            [$table]
        );

        return array_map(
            fn (object $r) => $this->normaliseColumn(
                $r->name,
                $r->type,
                strtoupper($r->is_nullable) === 'YES',
                $r->column_default
            ),
            $rows
        );
    }

    // ─── SQLite / fallback ────────────────────────────────────────────────────
    // SQLiteDatabaseManager uses a separate file per tenant, so the connection
    // is already fully isolated. The Schema facade handles SQLite natively.

    /** @return array<int, array{name: string}> */
    private function schemaFacadeTables(): array
    {
        return collect(Schema::getTables())
            ->sortBy('name')
            ->values()
            ->map(fn (array $t) => ['name' => $t['name']])
            ->all();
    }

    /** @return array<int, array{name: string, type: string, nullable: bool, has_default: bool, required: bool}> */
    private function schemaFacadeColumns(string $table): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return collect(Schema::getColumns($table))
            ->map(fn (array $col) => $this->normaliseColumn(
                $col['name'],
                $col['type_name'] ?? $col['type'] ?? 'unknown',
                $col['nullable'] ?? true,
                $col['default'] ?? null,
            ))
            ->values()
            ->all();
    }
}

