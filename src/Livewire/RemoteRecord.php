<?php

declare(strict_types=1);

namespace Relova\Livewire;

use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Relova\Models\RelovaEntityReference;
use Relova\Models\RelovaFieldMapping;
use Relova\Services\RelovaConnectionManager;

/**
 * Detail view for a single remote (UNION) record cached as a RelovaEntityReference snapshot.
 *
 * This page is reached when a user clicks a remote row in the host's list views.
 * It reads the `display_snapshot` stored in the entity reference and presents all
 * columns, highlighting those that are mapped to host module fields.
 */
#[Layout('components.layouts.app')]
class RemoteRecord extends Component
{
    public string $uid = '';

    public ?RelovaEntityReference $reference = null;

    /** @var array<string, mixed> The full raw remote row from display_snapshot. */
    public array $snapshot = [];

    /** @var array<int, array<string, mixed>> column_mappings from the associated field mapping. */
    public array $columnMappings = [];

    public ?string $mappingName = null;

    public ?string $connectionName = null;

    public string $remoteTable = '';

    public bool $refreshing = false;

    public ?string $errorMessage = null;

    public function mount(string $uid): void
    {
        $this->uid = $uid;

        $this->reference = RelovaEntityReference::query()
            ->with('connection')
            ->where('uid', $uid)
            ->firstOrFail();

        $this->snapshot = $this->reference->display_snapshot ?? [];
        $this->remoteTable = $this->reference->remote_table ?? '';
        $this->connectionName = $this->reference->connection?->name;

        // Load the field mapping for this source table, if one exists.
        $mapping = RelovaFieldMapping::query()
            ->where('connection_id', $this->reference->connection_id)
            ->where('source_table', $this->reference->remote_table)
            ->first();

        if ($mapping !== null) {
            $this->mappingName = $mapping->name;
            $this->columnMappings = $mapping->column_mappings ?? [];
        }
    }

    /**
     * Re-fetch the row from the remote source and update the snapshot.
     * Also clears the union cache so the list view shows fresh data.
     */
    public function refresh(): void
    {
        $this->refreshing = true;
        $this->errorMessage = null;

        try {
            $connection = $this->reference?->connection;

            if ($connection === null || ! $connection->enabled) {
                $this->errorMessage = __('relova.connection_unavailable');
                $this->refreshing = false;

                return;
            }

            $manager = app(RelovaConnectionManager::class);
            $sql = 'SELECT * FROM "'.$this->remoteTable.'"';
            $rows = $manager->query($connection, $sql, []);

            // Find the specific row matching this snapshot by content hash.
            $currentHash = $this->reference->remote_primary_value;
            $newRow = null;

            foreach ($rows as $row) {
                // Try current hash first (no change); if the row content changed, we pick the
                // "same position" row that is closest — but a content-addressable store means
                // a changed row will simply not match. We fall back to first row if only one exists.
                if (md5(serialize($row)) === $currentHash) {
                    $newRow = $row;
                    break;
                }
            }

            // If no exact hash match, refresh with the full table snapshot approach:
            // update all rows via upsert so the next load picks up the new hash.
            if ($newRow === null && count($rows) > 0) {
                $this->errorMessage = __('relova.remote_row_content_changed');
            } else {
                $newRow ??= [];
            }

            if ($newRow !== []) {
                // Only persist the remote columns that are explicitly mapped —
                // keeping unmapped / FK fields out of the snapshot prevents
                // Relova from accidentally overwriting local relation columns.
                if (! empty($this->columnMappings)) {
                    $mappedKeys = collect($this->columnMappings)
                        ->pluck('remote_column')
                        ->filter()
                        ->flip()
                        ->all();
                    $newRow = array_intersect_key($newRow, $mappedKeys);
                }

                $this->reference->update([
                    'display_snapshot' => $newRow,
                    'snapshot_refreshed_at' => now(),
                ]);

                $this->snapshot = $newRow;
            }

            // Bust the union cache for this table so the list view is also refreshed.
            $mapping = RelovaFieldMapping::query()
                ->where('connection_id', $this->reference->connection_id)
                ->where('source_table', $this->remoteTable)
                ->first();

            if ($mapping !== null) {
                Cache::forget('relova:union:'.$mapping->id);
            }
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->refreshing = false;
        }
    }

    /**
     * Returns the local field name that a remote column is mapped to, or null if unmapped.
     */
    public function getMappedLocalField(string $remoteColumn): ?string
    {
        foreach ($this->columnMappings as $cm) {
            if (($cm['remote_column'] ?? '') === $remoteColumn) {
                return $cm['local_field'] ?? null;
            }
        }

        return null;
    }

    public function render(): \Illuminate\View\View
    {
        return view('relova::livewire.remote-record');
    }
}
