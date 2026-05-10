<?php

declare(strict_types=1);

namespace Relova\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Relova\Models\ConnectorModuleMapping;
use Relova\Models\RelovaConnection;
use Relova\Models\VirtualEntityReference;

/**
 * Relova landing page for the host application.
 *
 * Shows tenant-scoped counts of connections, mappings, references and
 * snapshot-freshness state. All data comes from local Relova tables â€”
 * no remote calls are made on render.
 */
#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    public string $tenantId = '';

    /** The active premises ID taken from the authenticated user. Null = no premises filter. */
    public ?int $premisesId = null;

    public int $totalConnections = 0;

    public int $activeConnections = 0;

    public int $healthyConnections = 0;

    public int $erroringConnections = 0;

    public int $totalMappings = 0;

    public int $totalReferences = 0;

    public int $freshSnapshots = 0;

    public int $staleSnapshots = 0;

    public int $unavailableSnapshots = 0;

    /** @var array<int, array<string, mixed>> */
    public array $connections = [];

    public function mount(): void
    {
        // Prefer the Relova container binding (set by middleware on central domain)
        // over stancl's tenant() helper (only available on tenant subdomains).
        if (app()->bound('relova.current_tenant')) {
            $this->tenantId = (string) app('relova.current_tenant');
        } elseif (function_exists('tenant') && tenant()) {
            $this->tenantId = (string) tenant('id');
        }

        $this->premisesId = Auth::user()?->premises_id;

        $this->loadStats();
    }

    private function loadStats(): void
    {
        // Mappings scoped to the active premises (or global ones).
        $premisesMappingQuery = ConnectorModuleMapping::query()
            ->where('tenant_id', $this->tenantId)
            ->when($this->premisesId !== null, fn ($q) => $q->where(function ($inner) {
                $inner->where('premises_id', $this->premisesId)->orWhereNull('premises_id');
            }));

        $this->totalMappings = (clone $premisesMappingQuery)->count();

        // Connections scoped directly by their own premises_id (or global ones with NULL).
        $connectionQuery = RelovaConnection::query()
            ->where('tenant_id', $this->tenantId)
            ->when($this->premisesId !== null, fn ($q) => $q->where(function ($inner) {
                $inner->where('premises_id', $this->premisesId)->orWhereNull('premises_id');
            }));

        $this->totalConnections = (clone $connectionQuery)->count();
        $this->activeConnections = (clone $connectionQuery)->where('status', 'active')->count();
        $this->healthyConnections = $this->activeConnections;
        $this->erroringConnections = (clone $connectionQuery)
            ->whereIn('status', ['error', 'unreachable'])
            ->count();

        // Virtual references scoped via their mapping to the active premises.
        $mappingIds = (clone $premisesMappingQuery)->pluck('id')->all();

        $referenceQuery = VirtualEntityReference::query()
            ->where('tenant_id', $this->tenantId)
            ->when(! empty($mappingIds), fn ($q) => $q->whereIn('mapping_id', $mappingIds));

        $this->totalReferences = (clone $referenceQuery)->count();
        $this->freshSnapshots = (clone $referenceQuery)->where('snapshot_status', 'fresh')->count();
        $this->staleSnapshots = (clone $referenceQuery)->where('snapshot_status', 'stale')->count();
        $this->unavailableSnapshots = (clone $referenceQuery)->where('snapshot_status', 'unavailable')->count();

        $this->connections = (clone $connectionQuery)
            ->orderBy('name')
            ->get(['id', 'uid', 'name', 'driver', 'host', 'database', 'status', 'last_checked_at', 'last_error'])
            ->map(fn ($c) => [
                'uid' => $c->uid,
                'name' => $c->name,
                'driver' => $c->driver,
                'host' => $c->host,
                'database' => $c->database,
                'status' => $c->status,
                'last_checked_at' => optional($c->last_checked_at)->diffForHumans(),
                'last_error' => $c->last_error,
            ])
            ->all();
    }

    public function render()
    {
        return view('relova::livewire.dashboard');
    }
}
