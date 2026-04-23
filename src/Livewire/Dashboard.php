<?php

declare(strict_types=1);

namespace Relova\Livewire;

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
        $this->tenantId = (string) (function_exists('tenant') && tenant() ? tenant('id') : '');
        $this->loadStats();
    }

    private function loadStats(): void
    {
        $connectionQuery = RelovaConnection::query()->where('tenant_id', $this->tenantId);

        $this->totalConnections = (clone $connectionQuery)->count();
        $this->activeConnections = (clone $connectionQuery)->where('status', 'active')->count();
        $this->healthyConnections = $this->activeConnections;
        $this->erroringConnections = (clone $connectionQuery)
            ->whereIn('status', ['error', 'unreachable'])
            ->count();

        $this->totalMappings = ConnectorModuleMapping::query()
            ->where('tenant_id', $this->tenantId)
            ->count();

        $referenceQuery = VirtualEntityReference::query()->where('tenant_id', $this->tenantId);

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
