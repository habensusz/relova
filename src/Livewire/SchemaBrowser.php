<?php

declare(strict_types=1);

namespace Relova\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Relova\Models\RelovaConnection;
use Relova\Services\QueryExecutor;
use Relova\Services\SchemaInspector;
use Throwable;

/**
 * Browse a remote source's structure.
 *
 * Reads table list from Redis-cached schema metadata (cache miss only goes
 * remote). Fetching column definitions for a single table also hits cache.
 * Preview rows are streamed via QueryExecutor â€” never bulk-loaded.
 */
#[Layout('components.layouts.app')]
class SchemaBrowser extends Component
{
    public string $tenantId = '';

    public ?string $connectionUid = null;

    public ?string $selectedTable = null;

    public string $tableSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $tables = [];

    /** @var array<int, array<string, mixed>> */
    public array $columns = [];

    /** @var array<int, array<string, mixed>> */
    public array $previewRows = [];

    public ?string $tablesError = null;

    public ?string $columnsError = null;

    public ?string $previewError = null;

    /**
     * Accept both {uid} (route param) and $connectionUid (direct mount).
     */
    public function mount(?string $uid = null, ?string $connectionUid = null): void
    {
        $this->tenantId = (string) (function_exists('tenant') && tenant() ? tenant('id') : '');

        $resolved = $uid ?? $connectionUid;
        if ($resolved) {
            $this->connectionUid = $resolved;
            $this->loadTables();
        }
    }

    public function selectConnection(string $uid): void
    {
        $this->connectionUid = $uid;
        $this->selectedTable = null;
        $this->tableSearch = '';
        $this->columns = [];
        $this->previewRows = [];
        $this->tablesError = null;
        $this->columnsError = null;
        $this->previewError = null;
        $this->loadTables();
    }

    public function selectTable(string $table, SchemaInspector $inspector, QueryExecutor $executor): void
    {
        $this->selectedTable = $table;
        $this->columns = [];
        $this->previewRows = [];
        $this->columnsError = null;
        $this->previewError = null;

        $connection = $this->resolveConnection();
        if (! $connection) {
            return;
        }

        try {
            $this->columns = $inspector->columns($connection, $table);
        } catch (Throwable $e) {
            $this->columnsError = $e->getMessage();
        }

        try {
            $generator = $executor->executePassThrough($connection, $table, limit: 25);
            $this->previewRows = iterator_to_array($generator, false);
        } catch (Throwable $e) {
            $this->previewError = $e->getMessage();
        }
    }

    public function flushCache(SchemaInspector $inspector): void
    {
        $connection = $this->resolveConnection();
        if ($connection) {
            $this->selectedTable = null;
            $this->columns = [];
            $this->previewRows = [];
            $this->columnsError = null;
            $this->previewError = null;
            $this->loadTables(forceRefresh: true);
        }
    }

    public function updatedTableSearch(): void
    {
        // Reactive — no extra DB work needed; filtering is done in render()
    }

    private function loadTables(bool $forceRefresh = false): void
    {
        $this->tables = [];
        $this->tablesError = null;

        $connection = $this->resolveConnection();
        if (! $connection) {
            return;
        }

        try {
            $inspector = app(SchemaInspector::class);
            if ($forceRefresh) {
                $inspector->invalidate($connection);
            }
            $this->tables = $inspector->tables($connection);
        } catch (Throwable $e) {
            $this->tablesError = $e->getMessage();
        }
    }

    private function resolveConnection(): ?RelovaConnection
    {
        if (! $this->connectionUid) {
            return null;
        }

        return RelovaConnection::query()
            ->where('tenant_id', $this->tenantId)
            ->where('uid', $this->connectionUid)
            ->first();
    }

    public function render()
    {
        $connections = RelovaConnection::query()
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['uid', 'name', 'driver']);

        $filteredTables = empty($this->tableSearch)
            ? $this->tables
            : array_values(array_filter(
                $this->tables,
                fn (array $t) => str_contains(strtolower($t['name']), strtolower($this->tableSearch)),
            ));

        return view('relova::livewire.schema-browser', [
            'connections' => $connections,
            'filteredTables' => $filteredTables,
        ]);
    }
}
