<?php

declare(strict_types=1);

namespace Relova\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Relova\Exceptions\ConnectionException;
use Relova\Exceptions\QueryException;
use Relova\Exceptions\ReadOnlyViolationException;
use Relova\Exceptions\SsrfException;
use Relova\Models\RelovaConnection;
use Relova\Services\QueryExecutor;

/**
 * Thin façade over QueryExecutor. Accepts whitelist-style parameters only —
 * callers never supply raw SQL.
 */
class QueryController extends Controller
{
    public function __construct(
        private readonly QueryExecutor $executor,
    ) {}

    public function execute(Request $request, string $connectionUid): JsonResponse
    {
        $connection = $this->find($request, $connectionUid);

        $data = Validator::make($request->all(), [
            'table' => ['required', 'string', 'max:255'],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', 'max:255'],
            'conditions' => ['sometimes', 'array'],
            'conditions.*' => ['array', 'size:3'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ])->validate();

        try {
            $rows = [];
            $gen = $this->executor->executePassThrough(
                connection: $connection,
                table: $data['table'],
                conditions: $data['conditions'] ?? [],
                columns: $data['columns'] ?? ['*'],
                limit: $data['limit'] ?? 100,
                offset: $data['offset'] ?? 0,
            );
            foreach ($gen as $row) {
                $rows[] = $row;
            }

            return response()->json(['data' => $rows, 'meta' => ['count' => count($rows)]]);
        } catch (SsrfException|ConnectionException|QueryException|ReadOnlyViolationException $e) {
            return $this->errorResponse($e);
        }
    }

    public function search(Request $request, string $connectionUid): JsonResponse
    {
        $connection = $this->find($request, $connectionUid);

        $data = Validator::make($request->all(), [
            'table' => ['required', 'string', 'max:255'],
            'search_column' => ['required', 'string', 'max:255'],
            'search_term' => ['required', 'string', 'max:255'],
            'display_columns' => ['sometimes', 'array'],
            'display_columns.*' => ['string', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ])->validate();

        try {
            $rows = [];
            $gen = $this->executor->search(
                connection: $connection,
                table: $data['table'],
                searchColumn: $data['search_column'],
                searchTerm: $data['search_term'],
                displayColumns: $data['display_columns'] ?? [],
                limit: $data['limit'] ?? 20,
            );
            foreach ($gen as $row) {
                $rows[] = $row;
            }

            return response()->json(['data' => $rows, 'meta' => ['count' => count($rows)]]);
        } catch (SsrfException|ConnectionException|QueryException|ReadOnlyViolationException $e) {
            return $this->errorResponse($e);
        }
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
