<?php

declare(strict_types=1);

namespace Relova\Livewire;

use Livewire\Component;
use Relova\Models\RelovaConnection;
use Relova\Models\RelovaEntityReference;
use Relova\Services\EntityReferenceService;

/**
 * Livewire asset picker component — searches remote sources live as the user
 * types, presents results, and on selection creates/retrieves the virtual
 * entity reference. The parent form then holds a clean local foreign key.
 */
class AssetPicker extends Component
{
    public ?string $connectionUid = null;

    public string $sourceTable = '';

    public string $searchColumn = '';

    public string $primaryColumn = 'id';

    public array $displayColumns = [];

    // Search state
    public string $searchTerm = '';

    public array $searchResults = [];

    public bool $searching = false;

    // Selected reference
    public ?int $selectedReferenceId = null;

    public ?string $selectedDisplay = null;

    public array $selectedSnapshot = [];

    // Config
    public int $maxResults = 20;

    public ?string $errorMessage = null;

    /**
     * Mount with connection and table config.
     */
    public function mount(
        ?string $connectionUid = null,
        string $sourceTable = '',
        string $searchColumn = '',
        string $primaryColumn = 'id',
        array $displayColumns = [],
        ?int $selectedReferenceId = null,
    ): void {
        $this->connectionUid = $connectionUid;
        $this->sourceTable = $sourceTable;
        $this->searchColumn = $searchColumn;
        $this->primaryColumn = $primaryColumn;
        $this->displayColumns = $displayColumns;
        $this->selectedReferenceId = $selectedReferenceId;

        if ($selectedReferenceId) {
            $this->loadExistingReference($selectedReferenceId);
        }
    }

    /**
     * Load an already-selected reference for display.
     */
    protected function loadExistingReference(int $referenceId): void
    {
        $reference = RelovaEntityReference::find($referenceId);

        if ($reference) {
            $this->selectedDisplay = $reference->getDisplayLabel();
            $this->selectedSnapshot = $reference->display_snapshot ?? [];
        }
    }

    /**
     * Live search as user types — queries remote source.
     */
    public function updatedSearchTerm(): void
    {
        if (strlen($this->searchTerm) < 2) {
            $this->searchResults = [];

            return;
        }

        $this->search();
    }

    /**
     * Execute the remote search.
     */
    public function search(): void
    {
        if (! $this->connectionUid || ! $this->sourceTable || ! $this->searchColumn) {
            $this->errorMessage = __('relova::relova.picker_not_configured');

            return;
        }

        try {
            $connection = RelovaConnection::where('uid', $this->connectionUid)->firstOrFail();
            $referenceService = app(EntityReferenceService::class);

            $this->searchResults = $referenceService->searchRemote(
                connection: $connection,
                table: $this->sourceTable,
                searchColumn: $this->searchColumn,
                searchTerm: $this->searchTerm,
                displayColumns: array_merge([$this->primaryColumn], $this->displayColumns),
                limit: $this->maxResults,
            );

            $this->errorMessage = null;
        } catch (\Exception $e) {
            $this->searchResults = [];
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Select a result — creates or retrieves the entity reference.
     */
    public function selectResult(string $primaryValue): void
    {
        if (! $this->connectionUid) {
            return;
        }

        try {
            $connection = RelovaConnection::where('uid', $this->connectionUid)->firstOrFail();
            $referenceService = app(EntityReferenceService::class);

            // Find the row data from search results for snapshot
            $rowData = collect($this->searchResults)
                ->firstWhere($this->primaryColumn, $primaryValue) ?? [];

            $reference = $referenceService->resolve(
                connection: $connection,
                table: $this->sourceTable,
                primaryColumn: $this->primaryColumn,
                primaryValue: $primaryValue,
                snapshotColumns: $this->displayColumns,
            );

            // Update snapshot with full result data if we have it
            if (! empty($rowData)) {
                $reference->refreshSnapshot($rowData);
            }

            $this->selectedReferenceId = $reference->id;
            $this->selectedDisplay = $reference->getDisplayLabel();
            $this->selectedSnapshot = $reference->display_snapshot ?? [];
            $this->searchResults = [];
            $this->searchTerm = '';

            // Dispatch event for parent component to capture
            $this->dispatch('relova-entity-selected', [
                'referenceId' => $reference->id,
                'referenceUid' => $reference->uid,
                'displayLabel' => $this->selectedDisplay,
                'snapshot' => $this->selectedSnapshot,
                'remoteTable' => $this->sourceTable,
                'remotePrimaryValue' => $primaryValue,
            ]);
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Clear the selection.
     */
    public function clearSelection(): void
    {
        $this->selectedReferenceId = null;
        $this->selectedDisplay = null;
        $this->selectedSnapshot = [];
        $this->searchTerm = '';
        $this->searchResults = [];

        $this->dispatch('relova-entity-cleared');
    }

    public function render(): \Illuminate\View\View
    {
        return view('relova::livewire.asset-picker');
    }
}
