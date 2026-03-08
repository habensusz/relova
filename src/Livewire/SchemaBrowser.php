<?php

declare(strict_types=1);

namespace Relova\Livewire;

use Livewire\Component;
use Relova\Models\RelovaConnection;
use Relova\Services\RelovaConnectionManager;

/**
 * Schema browser — browse tables and columns of a Relova connection.
 */
class SchemaBrowser extends Component
{
    public ?string $connectionUid = null;

    public array $connections = [];

    public array $tables = [];

    public array $columns = [];

    public array $previewRows = [];

    public ?string $selectedTable = null;

    public bool $loading = false;

    public ?string $errorMessage = null;

    public int $previewLimit = 25;

    public function mount(?string $connectionUid = null): void
    {
        $this->connections = RelovaConnection::enabled()
            ->orderBy('name')
            ->get(['uid', 'name', 'driver_type', 'health_status'])
            ->toArray();

        if ($connectionUid) {
            $this->connectionUid = $connectionUid;
            $this->loadTables();
        }
    }

    public function selectConnection(string $uid): void
    {
        $this->connectionUid = $uid;
        $this->selectedTable = null;
        $this->columns = [];
        $this->previewRows = [];
        $this->errorMessage = null;
        $this->loadTables();
    }

    public function loadTables(): void
    {
        if (! $this->connectionUid) {
            return;
        }

        try {
            $connection = RelovaConnection::where('uid', $this->connectionUid)->firstOrFail();
            $manager = app(RelovaConnectionManager::class);

            $this->tables = $manager->getTables($connection);
            $this->errorMessage = null;
        } catch (\Exception $e) {
            $this->tables = [];
            $this->errorMessage = $e->getMessage();
        }
    }

    public function selectTable(string $tableName): void
    {
        $this->selectedTable = $tableName;
        $this->previewRows = [];
        $this->loadColumns();
    }

    public function loadColumns(): void
    {
        if (! $this->connectionUid || ! $this->selectedTable) {
            return;
        }

        try {
            $connection = RelovaConnection::where('uid', $this->connectionUid)->firstOrFail();
            $manager = app(RelovaConnectionManager::class);

            $this->columns = $manager->getColumns($connection, $this->selectedTable);
            $this->errorMessage = null;
        } catch (\Exception $e) {
            $this->columns = [];
            $this->errorMessage = $e->getMessage();
        }
    }

    public function loadPreview(): void
    {
        if (! $this->connectionUid || ! $this->selectedTable) {
            return;
        }

        try {
            $connection = RelovaConnection::where('uid', $this->connectionUid)->firstOrFail();
            $manager = app(RelovaConnectionManager::class);

            $this->previewRows = $manager->preview(
                $connection,
                $this->selectedTable,
                [],
                $this->previewLimit
            );
            $this->errorMessage = null;
        } catch (\Exception $e) {
            $this->previewRows = [];
            $this->errorMessage = $e->getMessage();
        }
    }

    public function refreshSchema(): void
    {
        if (! $this->connectionUid) {
            return;
        }

        try {
            $connection = RelovaConnection::where('uid', $this->connectionUid)->firstOrFail();
            $manager = app(RelovaConnectionManager::class);
            $manager->flushCache($connection);

            $this->loadTables();

            if ($this->selectedTable) {
                $this->loadColumns();
            }

            $this->dispatch('notify', message: __('relova::relova.cache_flushed'));
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('relova::livewire.schema-browser');
    }
}
