<?php

declare(strict_types=1);

namespace Relova\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Relova\Contracts\ModuleDataConsumer;
use Relova\Models\ConnectorModuleMapping;
use Relova\Models\RelovaConnection;
use Relova\Services\SchemaInspector;
use Relova\Services\SyncEngine;

#[Layout('components.layouts.app')]
class MappingManager extends Component
{
    public string $tenantId = '';

    /** Premises this manager is scoped to (auto-set from the authenticated user). */
    public ?int $premisesId = null;

    public bool $showForm = false;

    public bool $editing = false;

    public ?string $editingUid = null;

    // ── Form fields ──────────────────────────────────────────────────────────

    public string $connectionUid = '';

    public string $moduleKey = '';

    public string $remoteTable = '';

    /** Column in the remote table that uniquely identifies each record. */
    public string $remotePkColumn = 'id';

    public string $syncBehavior = 'snapshot_cache';

    public int $cacheTtlMinutes = 30;

    public bool $active = true;

    /** @var array<int, array{local: string, remote: string}> */
    public array $fieldMappingRows = [['local' => '', 'remote' => '']];

    /** @var array<int, string> */
    public array $displayFieldSelections = [];

    /** Used for manually typing a display field name when columns aren't loaded. */
    public string $newDisplayField = '';

    /** @var array<int, array{column: string, value: string}> */
    public array $filterRows = [];

    /**
     * Default values for local FK columns the remote system does not have.
     * Keyed by local column name (e.g. 'location_id'), value is the local entity ID.
     *
     * @var array<string, string>
     */
    public array $defaultValues = [];

    /**
     * JOIN specifications added by the user.
     * Each row: {table: string, type: string, foreign_key: string, references: string}
     *
     * @var array<int, array{table: string, type: string, foreign_key: string, references: string}>
     */
    public array $joinRows = [];

    // ── Remote metadata (loaded reactively) ─────────────────────────────────

    /** @var array<int, array<string, mixed>> */
    public array $remoteTables = [];

    /** @var array<int, array<string, mixed>> */
    public array $remoteColumns = [];

    /** @var array<string, array<int, array<string, mixed>>> Keyed by join table name. */
    public array $joinedTableColumns = [];

    public string $tablesError = '';

    public string $columnsError = '';

    /** @var array<int, string> Local DB table names used for the module key picker. */
    public array $localTables = [];

    /** @var array<int, string> Column names from the selected local module table (for the local-field picker). */
    public array $localColumns = [];

    /** Whether the optional collapsible sections are expanded. */
    public bool $showDefaultValues = false;

    public bool $showJoins = false;

    public bool $showFilters = false;

    /** @var array<int, string> */
    public array $syncBehaviors = ['virtual', 'snapshot_cache', 'on_demand'];

    // ────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        if (function_exists('tenant') && tenant()) {
            $this->tenantId = (string) tenant('id');
        } elseif (app()->bound('relova.current_tenant')) {
            $this->tenantId = (string) app('relova.current_tenant');
        } else {
            $this->tenantId = '';
        }
        $this->premisesId = Auth::user()?->premises_id;

        // List only tables in the current tenant's PostgreSQL schema.
        $this->localTables = collect(
            DB::select(
                "SELECT table_name FROM information_schema.tables
                  WHERE table_schema = current_schema()
                    AND table_type = 'BASE TABLE'
                  ORDER BY table_name"
            )
        )->pluck('table_name')->all();
    }

    /** Load remote table list when the connection changes. */
    public function updatedConnectionUid(): void
    {
        $this->remoteTables = [];
        $this->remoteColumns = [];
        $this->remoteTable = '';
        $this->tablesError = '';
        $this->columnsError = '';
        $this->displayFieldSelections = [];

        if ($this->connectionUid === '') {
            return;
        }

        try {
            $this->remoteTables = app(SchemaInspector::class)->tables(
                $this->resolveConnection($this->connectionUid)
            );
        } catch (\Throwable $e) {
            $this->tablesError = $e->getMessage();
        }
    }

    /** Load remote column list when the table changes. */
    public function updatedRemoteTable(): void
    {
        $this->remoteColumns = [];
        $this->joinedTableColumns = [];
        $this->columnsError = '';
        $this->displayFieldSelections = [];
        $this->joinRows = [];

        if ($this->connectionUid === '' || $this->remoteTable === '') {
            return;
        }

        try {
            $this->remoteColumns = app(SchemaInspector::class)->columns(
                $this->resolveConnection($this->connectionUid),
                $this->remoteTable
            );
        } catch (\Throwable $e) {
            $this->columnsError = $e->getMessage();
        }
    }

    /**
     * In create mode, auto-fill field rows and display fields from the
     * registered consumer's declared defaults when the module key changes.
     */
    public function updatedModuleKey(): void
    {
        $this->localColumns = $this->loadLocalColumns($this->moduleKey);

        if ($this->editing || $this->moduleKey === '') {
            return;
        }

        $this->applyModuleDefaults($this->moduleKey);
    }

    /** (Re-)apply a registered consumer's default field + display field suggestions. */
    public function applyModuleDefaults(string $key): void
    {
        /** @var iterable<ModuleDataConsumer> $consumers */
        $consumers = app()->tagged('relova.module_consumers');

        foreach ($consumers as $consumer) {
            if ($consumer->moduleKey() !== $key) {
                continue;
            }

            $this->fieldMappingRows = [];

            foreach ($consumer->defaultFieldMappings() as $local => $remote) {
                $this->fieldMappingRows[] = ['local' => (string) $local, 'remote' => (string) $remote];
            }

            if (empty($this->fieldMappingRows)) {
                $this->fieldMappingRows = [['local' => '', 'remote' => '']];
            }

            $this->displayFieldSelections = array_values($consumer->displayFields());

            return;
        }

        // No registered consumer — fall back to local table introspection.
        // Pre-populate field rows with the local module's column names so the user
        // only needs to fill in the matching remote column name for each one.
        $this->fieldMappingRows = $this->introspectLocalColumns($key);
    }

    /**
     * Load all (non-internal) column names for a local table — used to populate
     * the local-field picker in the field mapping rows.
     *
     * @return array<int, string>
     */
    private function loadLocalColumns(string $moduleKey): array
    {
        if ($moduleKey === '') {
            return [];
        }

        $skip = ['id', 'uid', 'created_at', 'updated_at', 'deleted_at', 'premises_id', 'tenant_id'];

        try {
            $rows = DB::select(
                'SELECT column_name FROM information_schema.columns
                 WHERE table_schema = current_schema()
                   AND table_name = ?
                 ORDER BY ordinal_position',
                [$moduleKey]
            );
        } catch (\Throwable) {
            return [];
        }

        $result = [];

        foreach ($rows as $row) {
            $col = is_object($row) ? $row->column_name : ($row['column_name'] ?? '');

            if ($col !== '' && ! in_array($col, $skip, true)) {
                $result[] = $col;
            }
        }

        return $result;
    }

    /**
     * Query information_schema for the local module table and return
     * pre-populated field mapping rows (local filled, remote blank).
     *
     * Skips auto-managed columns (id, uid, timestamps, FK helpers, etc.) so
     * only "meaningful" data columns appear in the mapping form.
     *
     * @return array<int, array{local: string, remote: string}>
     */
    private function introspectLocalColumns(string $moduleKey): array
    {
        $autoManaged = [
            'id', 'uid', 'created_at', 'updated_at', 'deleted_at',
            'premises_id',
        ];

        try {
            $rows = DB::select(
                'SELECT column_name
                 FROM information_schema.columns
                 WHERE table_schema = current_schema()
                   AND table_name = ?
                 ORDER BY ordinal_position',
                [$moduleKey]
            );
        } catch (\Throwable) {
            return [['local' => '', 'remote' => '']];
        }

        $results = [];
        foreach ($rows as $row) {
            $col = is_object($row) ? $row->column_name : ($row['column_name'] ?? '');
            if ($col === '' || in_array($col, $autoManaged, true)) {
                continue;
            }
            // Skip internal FK and enum columns
            if (str_ends_with($col, '_id') || str_ends_with($col, '_enum')) {
                continue;
            }
            $results[] = ['local' => $col, 'remote' => ''];
        }

        return $results ?: [['local' => '', 'remote' => '']];
    }

    // ── Field mapping rows ───────────────────────────────────────────────────

    public function addFieldRow(): void
    {
        $this->fieldMappingRows[] = ['local' => '', 'remote' => ''];
    }

    public function removeFieldRow(int $index): void
    {
        array_splice($this->fieldMappingRows, $index, 1);

        if (empty($this->fieldMappingRows)) {
            $this->fieldMappingRows = [['local' => '', 'remote' => '']];
        }
    }

    // ── Display fields ───────────────────────────────────────────────────────

    public function toggleDisplayField(string $column): void
    {
        if (in_array($column, $this->displayFieldSelections, true)) {
            $this->displayFieldSelections = array_values(
                array_filter($this->displayFieldSelections, fn ($c) => $c !== $column)
            );
        } else {
            $this->displayFieldSelections[] = $column;
        }
    }

    public function addDisplayFieldManual(): void
    {
        $trimmed = trim($this->newDisplayField);

        if ($trimmed !== '' && ! in_array($trimmed, $this->displayFieldSelections, true)) {
            $this->displayFieldSelections[] = $trimmed;
        }

        $this->newDisplayField = '';
    }

    public function selectAllDisplayFields(): void
    {
        $this->displayFieldSelections = array_column($this->remoteColumns, 'name');

        foreach ($this->joinedTableColumns as $joinTable => $cols) {
            foreach ($cols as $col) {
                $this->displayFieldSelections[] = $joinTable.'.'.$col['name'];
            }
        }
    }

    // ── Joins ────────────────────────────────────────────────────────────────

    public function addJoinRow(): void
    {
        $this->joinRows[] = ['table' => '', 'type' => 'LEFT', 'foreign_key' => '', 'references' => 'id'];
        $this->showJoins = true;
    }

    public function removeJoinRow(int $index): void
    {
        $table = $this->joinRows[$index]['table'] ?? '';
        array_splice($this->joinRows, $index, 1);

        if ($table !== '') {
            unset($this->joinedTableColumns[$table]);
        }
    }

    /** Load columns for the joined table at the given row index. */
    public function loadJoinTableColumns(int $index): void
    {
        $table = trim($this->joinRows[$index]['table'] ?? '');

        if ($table === '' || $this->connectionUid === '') {
            return;
        }

        try {
            $this->joinedTableColumns[$table] = app(SchemaInspector::class)->columns(
                $this->resolveConnection($this->connectionUid),
                $table
            );
        } catch (\Throwable) {
            // Columns just won't appear in picker — not a fatal error.
        }
    }

    public function clearDisplayFields(): void
    {
        $this->displayFieldSelections = [];
    }

    // ── Filter rows ──────────────────────────────────────────────────────────

    public function addFilterRow(): void
    {
        $this->filterRows[] = ['column' => '', 'value' => ''];
        $this->showFilters = true;
    }

    public function removeFilterRow(int $index): void
    {
        array_splice($this->filterRows, $index, 1);
    }

    // ── Section toggles ──────────────────────────────────────────────────────

    public function toggleSection(string $section): void
    {
        $allowed = ['showDefaultValues', 'showFilters'];

        if (in_array($section, $allowed, true)) {
            $this->$section = ! $this->$section;
        }
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(string $uid): void
    {
        $mapping = ConnectorModuleMapping::query()
            ->where('tenant_id', $this->tenantId)
            ->where('uid', $uid)
            ->firstOrFail();

        $this->resetForm();

        $this->editing = true;
        $this->editingUid = $mapping->uid;
        $this->connectionUid = (string) optional($mapping->connection)->uid;
        $this->moduleKey = (string) $mapping->module_key;
        $this->remotePkColumn = (string) ($mapping->remote_pk_column ?: 'id');
        $this->syncBehavior = (string) $mapping->sync_behavior;
        $this->cacheTtlMinutes = (int) $mapping->cache_ttl_minutes;
        $this->active = (bool) $mapping->active;

        // Load remote schema so dropdowns are populated immediately when editing.
        // Order matters: updatedConnectionUid resets remoteTable; we restore it after.
        if ($this->connectionUid !== '') {
            $this->updatedConnectionUid();
            $this->remoteTable = (string) $mapping->remote_table;

            if ($this->remoteTable !== '') {
                // Manually load columns (updatedRemoteTable resets joinRows; we restore below).
                try {
                    $this->remoteColumns = app(SchemaInspector::class)->columns(
                        $this->resolveConnection($this->connectionUid),
                        $this->remoteTable
                    );
                } catch (\Throwable) {
                    // Non-fatal.
                }
            }
        } else {
            $this->remoteTable = (string) $mapping->remote_table;
        }

        // Restore persisted row data AFTER the schema-loading resets above.
        $fieldMappings = $mapping->field_mappings ?? [];
        $this->fieldMappingRows = [];

        foreach ($fieldMappings as $local => $remote) {
            $this->fieldMappingRows[] = ['local' => (string) $local, 'remote' => (string) $remote];
        }

        if (empty($this->fieldMappingRows)) {
            $this->fieldMappingRows = [['local' => '', 'remote' => '']];
        }

        $this->displayFieldSelections = array_values($mapping->display_fields ?? []);

        foreach ($mapping->filters ?? [] as $col => $val) {
            $this->filterRows[] = ['column' => (string) $col, 'value' => (string) $val];
        }

        $this->defaultValues = array_map('strval', $mapping->default_values ?? []);

        // Restore JOIN rows and load joined-table columns.
        $this->joinRows = [];
        foreach ($mapping->joins ?? [] as $joinTable => $spec) {
            $this->joinRows[] = [
                'table' => (string) $joinTable,
                'type' => (string) ($spec['type'] ?? 'LEFT'),
                'foreign_key' => (string) ($spec['foreign_key'] ?? ''),
                'references' => (string) ($spec['references'] ?? 'id'),
            ];
        }

        // Load columns for each joined table so pickers populate immediately.
        foreach ($this->joinRows as $idx => $_) {
            $this->loadJoinTableColumns($idx);
        }

        // Populate local-column picker and auto-expand sections that have data.
        $this->localColumns = $this->loadLocalColumns($this->moduleKey);
        $this->showDefaultValues = ! empty($this->defaultValues);
        $this->showJoins = ! empty($this->joinRows);
        $this->showFilters = ! empty($this->filterRows);

        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'connectionUid' => 'required|string',
            'moduleKey' => 'required|string|max:64',
            'remoteTable' => 'required|string|max:255',
            'remotePkColumn' => 'required|string|max:64',
            'syncBehavior' => 'required|in:virtual,snapshot_cache,on_demand',
            'cacheTtlMinutes' => 'required|integer|min:0|max:1440',
            'active' => 'boolean',
        ]);

        $connection = $this->resolveConnection($this->connectionUid);

        $fieldMappings = [];

        foreach ($this->fieldMappingRows as $row) {
            if (($row['local'] ?? '') !== '' && ($row['remote'] ?? '') !== '') {
                $fieldMappings[$row['local']] = $row['remote'];
            }
        }

        $displayFields = array_values(
            array_filter($this->displayFieldSelections, fn ($v) => $v !== '')
        );

        $filters = [];

        foreach ($this->filterRows as $row) {
            if (($row['column'] ?? '') !== '') {
                $filters[$row['column']] = $row['value'] ?? '';
            }
        }

        $defaultValues = array_filter(
            $this->defaultValues,
            fn ($v) => $v !== '' && $v !== null
        );

        $joins = [];

        foreach ($this->joinRows as $row) {
            $jTable = trim($row['table'] ?? '');
            if ($jTable === '') {
                continue;
            }
            $type = strtoupper(trim($row['type'] ?? 'LEFT'));
            $joins[$jTable] = [
                'type' => in_array($type, ['LEFT', 'INNER'], true) ? $type : 'LEFT',
                'foreign_key' => trim($row['foreign_key'] ?? ''),
                'references' => trim($row['references'] ?? 'id') ?: 'id',
            ];
        }

        $payload = [
            'tenant_id' => $this->tenantId,
            'premises_id' => $this->premisesId,
            'connection_id' => $connection->id,
            'module_key' => $this->moduleKey,
            'remote_table' => $this->remoteTable,
            'remote_pk_column' => $this->remotePkColumn,
            'field_mappings' => $fieldMappings,
            'display_fields' => $displayFields,
            'filters' => $filters,
            'default_values' => $defaultValues,
            'joins' => $joins,
            'sync_behavior' => $this->syncBehavior,
            'cache_ttl_minutes' => $this->cacheTtlMinutes,
            'active' => $this->active,
        ];

        if ($this->editing && $this->editingUid) {
            ConnectorModuleMapping::query()
                ->where('tenant_id', $this->tenantId)
                ->where('uid', $this->editingUid)
                ->update($payload);
        } else {
            $newMapping = ConnectorModuleMapping::query()->create($payload);

            // Kick off an initial sync immediately so remote rows appear without waiting
            // for the first page load to trigger autoSyncForTableAsync.
            if ($newMapping->active && $newMapping->sync_behavior !== 'on_demand') {
                dispatch(fn () => app(SyncEngine::class)->forceSync($newMapping))->afterResponse();
            }
        }

        $this->closeForm();
    }

    public function toggle(string $uid): void
    {
        $mapping = ConnectorModuleMapping::query()
            ->where('tenant_id', $this->tenantId)
            ->where('uid', $uid)
            ->firstOrFail();

        $mapping->update(['active' => ! $mapping->active]);
    }

    public function delete(string $uid): void
    {
        ConnectorModuleMapping::query()
            ->where('tenant_id', $this->tenantId)
            ->where('uid', $uid)
            ->delete();
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    private function resetForm(): void
    {
        $this->editing = false;
        $this->editingUid = null;
        $this->connectionUid = '';
        $this->moduleKey = '';
        $this->remoteTable = '';
        $this->remotePkColumn = 'id';
        $this->syncBehavior = 'snapshot_cache';
        $this->cacheTtlMinutes = 30;
        $this->active = true;
        $this->fieldMappingRows = [['local' => '', 'remote' => '']];
        $this->displayFieldSelections = [];
        $this->newDisplayField = '';
        $this->filterRows = [];
        $this->defaultValues = [];
        $this->joinRows = [];
        $this->remoteTables = [];
        $this->remoteColumns = [];
        $this->joinedTableColumns = [];
        $this->tablesError = '';
        $this->columnsError = '';
        $this->localColumns = [];
        $this->showDefaultValues = false;
        $this->showFilters = false;
        $this->resetErrorBag();
    }

    private function resolveConnection(string $uid): RelovaConnection
    {
        return RelovaConnection::query()
            ->where('tenant_id', $this->tenantId)
            ->where('uid', $uid)
            ->firstOrFail();
    }

    public function render(): View
    {
        $mappings = ConnectorModuleMapping::query()
            ->where('tenant_id', $this->tenantId)
            ->when($this->premisesId !== null, fn ($q) => $q->where(function ($q) {
                $q->where('premises_id', $this->premisesId)->orWhereNull('premises_id');
            }))
            ->with('connection:id,uid,name,driver')
            ->orderBy('module_key')
            ->get();

        $connections = RelovaConnection::query()
            ->where('tenant_id', $this->tenantId)
            ->orderBy('name')
            ->get(['uid', 'name', 'driver']);

        $moduleOptions = collect(app()->tagged('relova.module_consumers'))
            ->map(fn (ModuleDataConsumer $c) => ['key' => $c->moduleKey()])
            ->values()
            ->all();

        // Merge primary-table columns and all joined-table columns for pickers.
        $allColumns = $this->remoteColumns;
        foreach ($this->joinedTableColumns as $joinTable => $joinCols) {
            foreach ($joinCols as $col) {
                $allColumns[] = [
                    'name' => $joinTable.'.'.$col['name'],
                    'type' => $col['type'] ?? '',
                ];
            }
        }

        $localFkOptions = $this->resolveLocalFkOptions();
        $localFkColumns = array_values(array_intersect($this->localColumns, array_keys($localFkOptions)));

        return view('relova::livewire.mapping-manager', [
            'mappings' => $mappings,
            'connections' => $connections,
            'moduleOptions' => $moduleOptions,
            'allColumns' => $allColumns,
            'localFkOptions' => $localFkOptions,
            'localFkColumns' => $localFkColumns,
        ]);
    }

    /**
     * Load local entity options for known FK columns.
     *
     * Returns an array keyed by column name, each value being an array of
     * objects with 'id' and 'label'. Queries are small (reference tables only)
     * and results are not cached on the component — they are fresh on each render.
     *
     * The FK picker map is the single source of truth for which columns get a
     * local entity picker vs a free-text input in the default_values section.
     * Add entries here as new local FK relationships are introduced.
     *
     * @return array<string, array<int, object{id: int, label: string}>>
     */
    private function resolveLocalFkOptions(): array
    {
        $map = [
            'location_id'     => ['table' => 'locations',     'label_col' => 'location_name'],
            'manufacturer_id' => ['table' => 'manufacturers', 'label_col' => 'manufacturer_name'],
            'supplier_id'     => ['table' => 'suppliers',     'label_col' => 'supplier_name'],
            'part_id'         => ['table' => 'parts',         'label_col' => 'part_name'],
            'premises_id'     => ['table' => 'premises',      'label_col' => 'premises_name'],
            'category_id'     => ['table' => 'categories',    'label_col' => 'category_name'],
            'priority_id'     => ['table' => 'priorities',    'label_col' => 'priority_name'],
        ];

        $result = [];

        foreach ($map as $column => $spec) {
            try {
                $rows = DB::select(
                    'SELECT id, '.$spec['label_col'].' AS label FROM '.$spec['table'].' ORDER BY '.$spec['label_col']
                );
                $result[$column] = $rows;
            } catch (\Throwable) {
                // Table doesn't exist in this host-app context — skip silently.
            }
        }

        return $result;
    }
}
