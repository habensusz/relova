<?php

declare(strict_types=1);

namespace Relova\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Relova\Models\RelovaConnection;
use Relova\Models\RelovaEntityReference;
use Relova\Services\EntityReferenceService;

class EntityReferenceController extends Controller
{
    public function __construct(
        protected EntityReferenceService $entityReferenceService,
    ) {}

    /**
     * List entity references for a connection.
     */
    public function index(Request $request, string $connectionUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $references = RelovaEntityReference::forConnection($connection->id)
            ->orderBy('remote_table')
            ->orderBy('remote_primary_value')
            ->paginate($request->input('per_page', 50));

        return response()->json($references);
    }

    /**
     * Resolve (find or create) an entity reference.
     */
    public function resolve(Request $request, string $connectionUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'remote_table' => 'required|string|max:255',
            'remote_primary_column' => 'required|string|max:255',
            'remote_primary_value' => 'required|string|max:255',
            'snapshot_columns' => 'nullable|array',
            'snapshot_columns.*' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reference = $this->entityReferenceService->resolve(
            connection: $connection,
            table: $request->input('remote_table'),
            primaryColumn: $request->input('remote_primary_column'),
            primaryValue: $request->input('remote_primary_value'),
            snapshotColumns: $request->input('snapshot_columns', []),
        );

        return response()->json(['data' => $reference], 201);
    }

    /**
     * Show a specific entity reference.
     */
    public function show(Request $request, string $connectionUid, string $referenceUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $reference = RelovaEntityReference::forConnection($connection->id)
            ->where('uid', $referenceUid)
            ->firstOrFail();

        return response()->json(['data' => $reference]);
    }

    /**
     * Refresh a specific entity reference snapshot.
     */
    public function refreshSnapshot(Request $request, string $connectionUid, string $referenceUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $reference = RelovaEntityReference::forConnection($connection->id)
            ->where('uid', $referenceUid)
            ->firstOrFail();

        $snapshotColumns = $request->input('snapshot_columns', []);

        $this->entityReferenceService->refreshSnapshot($reference, $snapshotColumns);

        return response()->json(['data' => $reference->fresh()]);
    }

    /**
     * Search remote entities live (for asset picker).
     */
    public function search(Request $request, string $connectionUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'table' => 'required|string|max:255',
            'search_column' => 'required|string|max:255',
            'search_term' => 'required|string|max:255',
            'display_columns' => 'nullable|array',
            'display_columns.*' => 'string|max:255',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $results = $this->entityReferenceService->searchRemote(
            connection: $connection,
            table: $request->input('table'),
            searchColumn: $request->input('search_column'),
            searchTerm: $request->input('search_term'),
            displayColumns: $request->input('display_columns', []),
            limit: $request->input('limit', 20),
        );

        return response()->json([
            'data' => $results,
            'meta' => [
                'search_term' => $request->input('search_term'),
                'result_count' => count($results),
            ],
        ]);
    }
}
