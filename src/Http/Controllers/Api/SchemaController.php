<?php

declare(strict_types=1);

namespace Relova\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Relova\Models\RelovaConnection;
use Relova\Services\RelovaConnectionManager;

class SchemaController extends Controller
{
    public function __construct(
        protected RelovaConnectionManager $connectionManager,
    ) {}

    /**
     * List tables for a connection.
     */
    public function tables(Request $request, string $connectionUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $tables = $this->connectionManager->getTables($connection);

        return response()->json([
            'data' => $tables,
            'meta' => [
                'connection' => $connection->name,
                'driver' => $connection->driver_type,
                'total' => count($tables),
            ],
        ]);
    }

    /**
     * List columns for a specific table.
     */
    public function columns(Request $request, string $connectionUid, string $table): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $columns = $this->connectionManager->getColumns($connection, $table);

        return response()->json([
            'data' => $columns,
            'meta' => [
                'connection' => $connection->name,
                'table' => $table,
                'total' => count($columns),
            ],
        ]);
    }

    /**
     * Preview data from a remote table.
     */
    public function preview(Request $request, string $connectionUid, string $table): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $limit = min((int) $request->get('limit', 50), (int) config('relova.max_rows_per_query', 10000));
        $columns = $request->get('columns', []);

        $rows = $this->connectionManager->preview($connection, $table, $columns, $limit);

        return response()->json([
            'data' => $rows,
            'meta' => [
                'connection' => $connection->name,
                'table' => $table,
                'row_count' => count($rows),
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * Flush cached schema for a connection.
     */
    public function flushCache(Request $request, string $connectionUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $this->connectionManager->flushCache($connection);

        return response()->json([
            'message' => 'Schema cache flushed successfully',
        ]);
    }
}
