<?php

declare(strict_types=1);

namespace Relova\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Relova\Models\RelovaConnection;
use Relova\Services\SchemaInspector;

/**
 * Warm the Redis schema cache for a connection (tables + columns).
 *
 * Schedule this hourly (or longer) per connection to avoid first-user
 * latency when opening the schema browser.
 */
class WarmSchemaCache implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public int $tries = 2;

    public function __construct(public string $connectionId) {}

    public function handle(SchemaInspector $inspector): void
    {
        $connection = RelovaConnection::find($this->connectionId);
        if ($connection === null || $connection->status !== 'active') {
            return;
        }

        $tables = $inspector->tables($connection);

        foreach ($tables as $table) {
            $name = $table['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $inspector->columns($connection, $name);
            }
        }
    }
}
