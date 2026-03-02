<?php

declare(strict_types=1);

namespace Relova\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Relova\Models\RelovaConnection;
use Relova\Services\RelovaConnectionManager;

class QueryController extends Controller
{
    public function __construct(
        protected RelovaConnectionManager $connectionManager,
    ) {}

    /**
     * Execute a read-only query against a connection.
     */
    public function execute(Request $request, string $connectionUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'sql' => 'required|string|max:10000',
            'bindings' => 'nullable|array',
            'bindings.*' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $startTime = microtime(true);

            $rows = $this->connectionManager->query(
                $connection,
                $request->input('sql'),
                $request->input('bindings', [])
            );

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'data' => $rows,
                'meta' => [
                    'connection' => $connection->name,
                    'row_count' => count($rows),
                    'execution_time_ms' => $executionTime,
                ],
            ]);
        } catch (\Relova\Exceptions\ReadOnlyViolationException $e) {
            return response()->json([
                'error' => 'Read-only violation',
                'message' => $e->getMessage(),
            ], 403);
        } catch (\Relova\Exceptions\QueryException $e) {
            return response()->json([
                'error' => 'Query failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Execute a simplified select query with structured parameters.
     */
    public function select(Request $request, string $connectionUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'table' => 'required|string|max:255',
            'columns' => 'nullable|array',
            'columns.*' => 'string|max:255',
            'where' => 'nullable|array',
            'limit' => 'nullable|integer|min:1|max:'.config('relova.max_rows_per_query', 10000),
            'offset' => 'nullable|integer|min:0',
            'order_by' => 'nullable|string|max:255',
            'order_dir' => 'nullable|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $table = $request->input('table');
            $columns = $request->input('columns', ['*']);
            $where = $request->input('where', []);
            $limit = $request->input('limit', config('relova.default_page_size', 100));
            $offset = $request->input('offset', 0);
            $orderBy = $request->input('order_by');
            $orderDir = $request->input('order_dir', 'asc');

            $driver = $this->connectionManager->connect($connection);

            // Build structured query
            $cols = implode(', ', $columns === ['*'] ? ['*'] : $columns);
            $sql = "SELECT {$cols} FROM {$table}";
            $bindings = [];

            if (! empty($where)) {
                $clauses = [];
                foreach ($where as $key => $value) {
                    $clauses[] = "{$key} = ?";
                    $bindings[] = $value;
                }
                $sql .= ' WHERE '.implode(' AND ', $clauses);
            }

            if ($orderBy) {
                $sql .= " ORDER BY {$orderBy} {$orderDir}";
            }

            $sql .= " LIMIT {$limit} OFFSET {$offset}";

            $startTime = microtime(true);
            $rows = $this->connectionManager->query($connection, $sql, $bindings);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'data' => $rows,
                'meta' => [
                    'table' => $table,
                    'row_count' => count($rows),
                    'limit' => $limit,
                    'offset' => $offset,
                    'execution_time_ms' => $executionTime,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Query failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
