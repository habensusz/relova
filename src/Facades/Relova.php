<?php

declare(strict_types=1);

namespace Relova\Facades;

use Illuminate\Support\Facades\Facade;
use Relova\Services\RelovaConnectionManager;

/**
 * @method static \Relova\Contracts\ConnectorDriver connect(\Relova\Models\RelovaConnection $connection)
 * @method static bool test(\Relova\Models\RelovaConnection $connection)
 * @method static array getTables(\Relova\Models\RelovaConnection $connection)
 * @method static array getColumns(\Relova\Models\RelovaConnection $connection, string $table)
 * @method static array query(\Relova\Models\RelovaConnection $connection, string $sql, array $bindings = [])
 * @method static void flushCache(\Relova\Models\RelovaConnection $connection)
 * @method static array healthCheck(\Relova\Models\RelovaConnection $connection)
 * @method static array preview(\Relova\Models\RelovaConnection $connection, string $table, array $columns = [], int $limit = 100)
 *
 * @see \Relova\Services\RelovaConnectionManager
 */
class Relova extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RelovaConnectionManager::class;
    }
}
