<?php

declare(strict_types=1);

namespace Relova\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;
use Relova\Models\RelovaConnection;
use Relova\Models\RelovaFieldMapping;

/**
 * Relova Connector dashboard — landing page for all Relova features.
 */
#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    public int $totalConnections = 0;

    public int $activeConnections = 0;

    public int $totalMappings = 0;

    public int $healthyConnections = 0;

    /** @var array<int, array<string, mixed>> */
    public array $mappings = [];

    public function mount(): void
    {
        $this->loadStats();
    }

    private function loadStats(): void
    {
        $this->totalConnections = RelovaConnection::query()->count();
        $this->activeConnections = RelovaConnection::query()->where('enabled', true)->count();
        $this->totalMappings = RelovaFieldMapping::query()->count();
        $this->healthyConnections = RelovaConnection::query()
            ->where('enabled', true)
            ->where('health_status', 'healthy')
            ->count();

        $this->mappings = RelovaFieldMapping::query()
            ->with('connection:id,uid,name')
            ->orderBy('name')
            ->get(['id', 'uid', 'connection_id', 'name', 'target_module', 'source_table', 'enabled'])
            ->toArray();
    }

    public function render(): \Illuminate\View\View
    {
        return view('relova::livewire.dashboard');
    }
}
