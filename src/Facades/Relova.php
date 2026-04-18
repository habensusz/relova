<?php

declare(strict_types=1);

namespace Relova\Facades;

use Illuminate\Support\Facades\Facade;
use Relova\Sdk\RelovaClient;

/**
 * @method static array getDrivers()
 * @method static array listConnections()
 * @method static array getConnection(string $uid)
 * @method static array createConnection(array $data)
 * @method static array updateConnection(string $uid, array $data)
 * @method static bool deleteConnection(string $uid)
 * @method static array testConnection(string $uid)
 * @method static array getTables(string $connectionUid)
 * @method static array getColumns(string $connectionUid, string $table)
 * @method static array preview(string $connectionUid, string $table, int $limit = 25, array $columns = [])
 * @method static array query(string $connectionUid, string $table, array $columns = ['*'], array $conditions = [], int $limit = 100, int $offset = 0)
 * @method static array search(string $connectionUid, string $table, string $searchColumn, string $searchTerm, array $displayColumns = [], int $limit = 20)
 * @method static \Generator browse(string $connectionUid, string $table, array $columns = ['*'], array $conditions = [], int $pageSize = 250)
 * @method static array selectEntity(string $connectionUid, string $remoteTable, string $remotePkColumn, string $remotePkValue, array $displayFields = [])
 * @method static array getDisplayData(string $connectionUid, string $referenceUid, array $displayFields = [])
 * @method static array refreshReference(string $connectionUid, string $referenceUid)
 *
 * @see RelovaClient
 */
class Relova extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RelovaClient::class;
    }
}
