<?php

declare(strict_types=1);

namespace Relova\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Relova\Models\VirtualEntityReference;
use Relova\Services\SnapshotManager;

/**
 * Refresh a single VirtualEntityReference's display snapshot from the live source.
 *
 * Dispatched reactively when a reference is accessed and its snapshot is stale.
 * Not scheduled — snapshots are pulled on demand.
 */
class RefreshSnapshot implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    /**
     * @param  array<int, string>  $displayFields
     */
    public function __construct(
        public string $referenceId,
        public array $displayFields = [],
    ) {}

    public function handle(SnapshotManager $snapshots): void
    {
        $reference = VirtualEntityReference::find($this->referenceId);
        if ($reference === null) {
            return;
        }

        $snapshots->refresh($reference, $this->displayFields);
    }
}
