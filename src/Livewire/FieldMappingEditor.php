<?php

declare(strict_types=1);

namespace Relova\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Relova\Models\RelovaConnection;
use Relova\Models\RelovaFieldMapping;
use Relova\Services\ColumnProvisionerService;
use Relova\Services\HostSchemaService;
use Relova\Services\RelovaConnectionManager;

/**
 * Livewire field mapping editor — allows tenants to configure how
 * remote columns map to itenance module fields.
 */
#[Layout('components.layouts.app')]
class FieldMappingEditor extends Component
{
    public ?string $connectionUid = null;

    public ?string $mappingUid = null;

    public array $connections = [];

    public array $remoteTables = [];

    public array $remoteColumns = [];

    public array $localTables = [];

    public array $localColumns = [];

    // Form fields
    public string $name = '';

    public string $description = '';

    public string $target_module = '';

    public string $source_table = '';

    public bool $enabled = true;

    /** @var array<int, array{remote_column: string, local_field: string}> */
    public array $column_mappings = [];

    // Sync / change-detection
    public string $timestamp_column = 'updated_at';

    // Preview state
    public array $previewRaw = [];

    public array $previewMapped = [];

    public bool $showPreview = false;

    public ?string $errorMessage = null;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'target_module' => 'required|string',
            'source_table' => 'required|string|max:255',
            'enabled' => 'boolean',
            'column_mappings' => 'required|array|min:1',
            'column_mappings.*.remote_column' => 'required|string',
            'column_mappings.*.local_field' => 'required|string',
            'timestamp_column' => 'nullable|string|max:100',
        ];
    }

    public function mount(?string $connectionUid = null, ?string $mappingUid = null): void
    {
        $this->connections = RelovaConnection::enabled()
            ->orderBy('name')
            ->get(['uid', 'name', 'driver_type'])
            ->toArray();

        $this->connectionUid = $connectionUid;
        $this->mappingUid = $mappingUid;

        $this->loadLocalTables();

        if ($connectionUid) {
            $this->loadRemoteTables();
        }

        if ($mappingUid) {
            $this->loadExistingMapping();
        }
    }

    public function updatedConnectionUid(): void
    {
        $this->source_table = '';
        $this->remoteColumns = [];
        $this->column_mappings = [];
        $this->loadRemoteTables();
    }

    public function updatedSourceTable(): void
    {
        $this->loadRemoteColumns();
    }

    public function updatedTargetModule(): void
    {
        $this->localColumns = [];
        $this->loadLocalColumns();
    }

    protected function loadLocalTables(): void
    {
        try {
            $this->localTables = app(HostSchemaService::class)->getTables();
        } catch (\Exception $e) {
            $this->localTables = [];
        }
    }

    protected function loadLocalColumns(): void
    {
        if (! $this->target_module) {
            $this->localColumns = [];

            return;
        }

        try {
            $this->localColumns = app(HostSchemaService::class)->getColumns($this->target_module);
        } catch (\Exception $e) {
            $this->localColumns = [];
        }
    }

    protected function loadRemoteTables(): void
    {
        if (! $this->connectionUid) {
            $this->remoteTables = [];

            return;
        }

        try {
            $connection = RelovaConnection::where('uid', $this->connectionUid)->firstOrFail();
            $manager = app(RelovaConnectionManager::class);

            $this->remoteTables = $manager->getTables($connection);
            $this->errorMessage = null;
        } catch (\Exception $e) {
            $this->remoteTables = [];
            $this->errorMessage = $e->getMessage();
        }
    }

    protected function loadRemoteColumns(): void
    {
        if (! $this->connectionUid || ! $this->source_table) {
            $this->remoteColumns = [];

            return;
        }

        try {
            $connection = RelovaConnection::where('uid', $this->connectionUid)->firstOrFail();
            $manager = app(RelovaConnectionManager::class);

            $this->remoteColumns = $manager->getColumns($connection, $this->source_table);
            $this->errorMessage = null;
        } catch (\Exception $e) {
            $this->remoteColumns = [];
            $this->errorMessage = $e->getMessage();
        }
    }

    protected function loadExistingMapping(): void
    {
        $mapping = RelovaFieldMapping::where('uid', $this->mappingUid)->first();

        if (! $mapping) {
            return;
        }

        $this->name = $mapping->name;
        $this->description = $mapping->description ?? '';
        $this->target_module = $mapping->target_module;
        $this->source_table = $mapping->source_table;
        $this->enabled = $mapping->enabled;
        $this->column_mappings = $mapping->column_mappings ?? [];
        $this->timestamp_column = $mapping->timestamp_column ?? 'updated_at';
        $this->connectionUid = $mapping->connection?->uid;

        if ($this->connectionUid) {
            $this->loadRemoteTables();
        }
        if ($this->source_table) {
            $this->loadRemoteColumns();
        }

        if ($this->target_module) {
            $this->loadLocalColumns();
        }
    }

    public function addColumnMapping(): void
    {
        $this->column_mappings[] = [
            'remote_column' => '',
            'local_field' => '',
        ];
    }

    public function removeColumnMapping(int $index): void
    {
        unset($this->column_mappings[$index]);
        $this->column_mappings = array_values($this->column_mappings);
    }

    public function delete(): void
    {
        if (! $this->mappingUid) {
            return;
        }

        $mapping = RelovaFieldMapping::where('uid', $this->mappingUid)->firstOrFail();
        $targetModule = $mapping->target_module;

        $mapping->delete();

        if (RelovaFieldMapping::where('target_module', $targetModule)->count() === 0) {
            app(ColumnProvisionerService::class)->dropRelovaColumns($targetModule);
        }

        $this->dispatch('notify', message: __('relova.mapping_deleted'));
        $this->redirect(
            tenancy()->initialized
                ? tenant()->route('relova.dashboard')
                : route('relova.dashboard'),
            navigate: true,
        );
    }

    public function save(): void
    {
        $this->validate();

        $connection = RelovaConnection::where('uid', $this->connectionUid)->firstOrFail();

        $data = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'connection_id' => $connection->id,
            'target_module' => $this->target_module,
            'source_table' => $this->source_table,
            'column_mappings' => $this->column_mappings,
            'query_mode' => 'snapshot',
            'enabled' => $this->enabled,
            'timestamp_column' => $this->timestamp_column ?: null,
        ];

        if ($this->mappingUid) {
            $mapping = RelovaFieldMapping::where('uid', $this->mappingUid)->firstOrFail();
            $mapping->update($data);
        } else {
            RelovaFieldMapping::create($data);

            // Automatically provision Relova tracking columns on the target
            // table the first time a mapping is created for it. Idempotent.
            app(ColumnProvisionerService::class)->ensureRelovaColumns($this->target_module);
        }

        $this->dispatch('notify', message: __('relova.mapping_saved'));
        $this->dispatch('mapping-saved');
    }

    public function preview(): void
    {
        if (! $this->connectionUid || ! $this->source_table || empty($this->column_mappings)) {
            return;
        }

        try {
            $connection = RelovaConnection::where('uid', $this->connectionUid)->firstOrFail();
            $manager = app(RelovaConnectionManager::class);

            $this->previewRaw = $manager->preview($connection, $this->source_table, [], 5);

            $tempMapping = new RelovaFieldMapping([
                'column_mappings' => $this->column_mappings,
            ]);

            $this->previewMapped = array_map(
                fn (array $row) => $tempMapping->applyToRow($row),
                $this->previewRaw
            );

            $this->showPreview = true;
            $this->errorMessage = null;
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('relova::livewire.field-mapping-editor');
    }
}
