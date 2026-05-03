<?php

declare(strict_types=1);

namespace Relova\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Relova\Models\TriggerRule;
use Relova\Models\VirtualEntityReference;

/**
 * Fired when a TriggerRule matches a synced VirtualEntityReference snapshot.
 *
 * Host applications listen for this and decide what to do — e.g. mainkeeperx2
 * creates a work order via WorkOrderGenerationService with source='relova'.
 *
 * The package never imports the host's models or services; the host's listener
 * imports the package's event. This keeps Relova reusable.
 */
class RelovaTriggerMatched
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string,mixed>  $snapshot  Current row snapshot at trigger time.
     */
    public function __construct(
        public VirtualEntityReference $reference,
        public TriggerRule $rule,
        public array $snapshot,
    ) {}
}
