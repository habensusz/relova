<?php

declare(strict_types=1);

namespace Relova\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event fired after a Relova mapping sync completes.
 *
 * Livewire components listening on the tenant's private channel use this
 * to refresh their virtual-entity data without polling.
 *
 * Channel: relova.{tenantId}
 * Payload: { module_key: "machines" }
 */
class RelovaSyncCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $moduleKey,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('relova.'.$this->tenantId);
    }

    public function broadcastWith(): array
    {
        return ['module_key' => $this->moduleKey];
    }
}
