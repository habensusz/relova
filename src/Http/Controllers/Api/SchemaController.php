<?php

declare(strict_types=1);

namespace Relova\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Relova\Exceptions\ConnectionException;
use Relova\Exceptions\SsrfException;
use Relova\Models\RelovaConnection;
use Relova\Services\ConnectionRegistry;
use Relova\Services\DriverRegistry;
use Relova\Services\SchemaInspector;

class SchemaController extends Controller
{
    public function __construct(
        private readonly SchemaInspector $inspector,
        private readonly ConnectionRegistry $connections,
        private readonly DriverRegistry $drivers,
    ) {}

    public function tables(Request $request, string $connectionUid): JsonResponse
    {
        $connection = $this->find($request, $connectionUid);

        try {
            return response()->json(['data' => $this->inspector->tables($connection)]);
        } catch (SsrfException|ConnectionException $e) {
            return $this->errorResponse($e);
        }
    }

    public function columns(Request $request, string $connectionUid, string $table): JsonResponse
    {
        $connection = $this->find($request, $connectionUid);

        try {
            return response()->json(['data' => $this->inspector->columns($connection, $table)]);
        } catch (SsrfException|ConnectionException $e) {
            return $this->errorResponse($e);
        }
    }

    public function preview(Request $request, string $connectionUid, string $table): JsonResponse
    {
        $connection = $this->find($request, $connectionUid);
        $limit = min((int) $request->query('limit', 25), 100);
        $columns = array_filter(explode(',', (string) $request->query('columns', '')));

        try {
            $this->connections->assertHostAllowed($connection);
            $driver = $this->drivers->resolve($connection->driver);
            $sql = $driver->buildPreviewQuery($table, $columns, $limit);
            $config = $this->connections->buildConfig($connection);

            $rows = [];
            foreach ($driver->query($config, $sql) as $row) {
                $rows[] = $row;
            }

            return response()->json(['data' => $rows, 'meta' => ['count' => count($rows), 'limit' => $limit]]);
        } catch (SsrfException|ConnectionException $e) {
            return $this->errorResponse($e);
        }
    }

    public function flushCache(Request $request, string $connectionUid): JsonResponse
    {
        $connection = $this->find($request, $connectionUid);
        $this->inspector->invalidate($connection);

        return response()->json(['data' => ['flushed' => true]]);
    }

    private function find(Request $request, string $uid): RelovaConnection
    {
        return RelovaConnection::query()
            ->where('tenant_id', (string) $request->attributes->get('relova_tenant_id'))
            ->where('uid', $uid)
            ->firstOrFail();
    }

    private function errorResponse(\Throwable $e): JsonResponse
    {
        return response()->json([
            'error' => class_basename($e),
            'message' => $e->getMessage(),
        ], 502);
    }
}
