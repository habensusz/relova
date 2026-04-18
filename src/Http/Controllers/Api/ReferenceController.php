<?php

declare(strict_types=1);

namespace Relova\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Relova\Jobs\RefreshSnapshot;
use Relova\Models\RelovaConnection;
use Relova\Models\VirtualEntityReference;
use Relova\Services\QueryExecutor;
use Relova\Services\ReferenceResolver;
use Relova\Services\SnapshotManager;

class ReferenceController extends Controller
{
    public function __construct(
        private readonly ReferenceResolver $resolver,
        private readonly SnapshotManager $snapshots,
        private readonly QueryExecutor $executor,
    ) {}

    public function index(Request $request, string $connectionUid): JsonResponse
    {
        $connection = $this->find($request, $connectionUid);

        $refs = VirtualEntityReference::query()
            ->where('tenant_id', $connection->tenant_id)
            ->where('connection_id', $connection->id)
            ->when($request->query('table'), fn ($q, $t) => $q->where('remote_table', $t))
            ->orderByDesc('id')
            ->limit(min((int) $request->query('limit', 50), 500))
            ->get()
            ->map(fn (VirtualEntityReference $r) => $this->serialize($r));

        return response()->json(['data' => $refs]);
    }

    public function resolve(Request $request, string $connectionUid): JsonResponse
    {
        $connection = $this->find($request, $connectionUid);

        $data = Validator::make($request->all(), [
            'remote_table' => ['required', 'string', 'max:255'],
            'remote_pk_column' => ['required', 'string', 'max:255'],
            'remote_pk_value' => ['required', 'string', 'max:255'],
            'display_fields' => ['sometimes', 'array'],
            'display_fields.*' => ['string', 'max:255'],
        ])->validate();

        $displayFields = $data['display_fields'] ?? [];

        $row = $this->executor->fetchOne(
            connection: $connection,
            table: $data['remote_table'],
            pkColumn: $data['remote_pk_column'],
            pkValue: $data['remote_pk_value'],
            columns: $displayFields === [] ? ['*'] : $displayFields,
        );

        if ($row === null) {
            return response()->json(['error' => 'NotFound', 'message' => 'Remote entity not found.'], 404);
        }

        $reference = $this->resolver->resolveOrCreate(
            tenantId: (string) $connection->tenant_id,
            connection: $connection,
            remoteTable: $data['remote_table'],
            remotePkColumn: $data['remote_pk_column'],
            remotePkValue: (string) $data['remote_pk_value'],
            displayFields: $displayFields,
            displayData: $row,
        );

        return response()->json(['data' => $this->serialize($reference)], 201);
    }

    public function search(Request $request, string $connectionUid): JsonResponse
    {
        $connection = $this->find($request, $connectionUid);

        $data = Validator::make($request->all(), [
            'table' => ['required', 'string', 'max:255'],
            'search_column' => ['required', 'string', 'max:255'],
            'search_term' => ['required', 'string', 'max:255'],
            'display_columns' => ['sometimes', 'array'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ])->validate();

        $rows = [];
        foreach ($this->executor->search(
            connection: $connection,
            table: $data['table'],
            searchColumn: $data['search_column'],
            searchTerm: $data['search_term'],
            displayColumns: $data['display_columns'] ?? [],
            limit: $data['limit'] ?? 20,
        ) as $row) {
            $rows[] = $row;
        }

        return response()->json(['data' => $rows, 'meta' => ['count' => count($rows)]]);
    }

    public function show(Request $request, string $connectionUid, string $referenceUid): JsonResponse
    {
        $reference = $this->findReference($request, $connectionUid, $referenceUid);

        $displayFields = array_filter(explode(',', (string) $request->query('display_fields', '')));
        $result = $this->snapshots->resolve($reference, $displayFields);

        return response()->json([
            'data' => $this->serialize($reference) + ['resolved' => $result],
        ]);
    }

    public function refresh(Request $request, string $connectionUid, string $referenceUid): JsonResponse
    {
        $reference = $this->findReference($request, $connectionUid, $referenceUid);
        RefreshSnapshot::dispatch($reference->getKey());

        return response()->json(['data' => ['queued' => true]]);
    }

    private function find(Request $request, string $uid): RelovaConnection
    {
        return RelovaConnection::query()
            ->where('tenant_id', (string) $request->attributes->get('relova_tenant_id'))
            ->where('uid', $uid)
            ->firstOrFail();
    }

    private function findReference(Request $request, string $connectionUid, string $referenceUid): VirtualEntityReference
    {
        $connection = $this->find($request, $connectionUid);

        return VirtualEntityReference::query()
            ->where('tenant_id', $connection->tenant_id)
            ->where('connection_id', $connection->id)
            ->where('uid', $referenceUid)
            ->firstOrFail();
    }

    private function serialize(VirtualEntityReference $r): array
    {
        return [
            'uid' => $r->uid,
            'connection_id' => $r->connection_id,
            'remote_table' => $r->remote_table,
            'remote_pk_column' => $r->remote_pk_column,
            'remote_pk_value' => $r->remote_pk_value,
            'display_snapshot' => $r->display_snapshot ?? [],
            'snapshot_status' => $r->snapshot_status,
            'snapshot_taken_at' => optional($r->snapshot_taken_at)->toIso8601String(),
        ];
    }
}
