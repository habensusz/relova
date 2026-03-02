<?php

declare(strict_types=1);

namespace Relova\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Relova\Models\RelovaConnection;
use Relova\Models\RelovaFieldMapping;

class FieldMappingController extends Controller
{
    /**
     * List field mappings for a connection.
     */
    public function index(Request $request, string $connectionUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $mappings = RelovaFieldMapping::forConnection($connection->id)
            ->orderBy('target_module')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $mappings]);
    }

    /**
     * Create a new field mapping.
     */
    public function store(Request $request, string $connectionUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'target_module' => 'required|string|max:100',
            'source_table' => 'required|string|max:255',
            'column_mappings' => 'required|array|min:1',
            'column_mappings.*.remote_column' => 'required|string|max:255',
            'column_mappings.*.local_field' => 'required|string|max:255',
            'transformation_rules' => 'nullable|array',
            'query_mode' => 'nullable|in:virtual,snapshot,on_demand',
            'enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['connection_id'] = $connection->id;
        $data['tenant_id'] = $tenantId;

        $mapping = RelovaFieldMapping::create($data);

        return response()->json(['data' => $mapping], 201);
    }

    /**
     * Show a specific field mapping.
     */
    public function show(Request $request, string $connectionUid, string $mappingUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $mapping = RelovaFieldMapping::forConnection($connection->id)
            ->where('uid', $mappingUid)
            ->firstOrFail();

        return response()->json(['data' => $mapping]);
    }

    /**
     * Update a field mapping.
     */
    public function update(Request $request, string $connectionUid, string $mappingUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $mapping = RelovaFieldMapping::forConnection($connection->id)
            ->where('uid', $mappingUid)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'target_module' => 'sometimes|string|max:100',
            'source_table' => 'sometimes|string|max:255',
            'column_mappings' => 'sometimes|array|min:1',
            'column_mappings.*.remote_column' => 'required_with:column_mappings|string|max:255',
            'column_mappings.*.local_field' => 'required_with:column_mappings|string|max:255',
            'transformation_rules' => 'nullable|array',
            'query_mode' => 'nullable|in:virtual,snapshot,on_demand',
            'enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mapping->update($validator->validated());

        return response()->json(['data' => $mapping->fresh()]);
    }

    /**
     * Delete a field mapping.
     */
    public function destroy(Request $request, string $connectionUid, string $mappingUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $mapping = RelovaFieldMapping::forConnection($connection->id)
            ->where('uid', $mappingUid)
            ->firstOrFail();

        $mapping->delete();

        return response()->json(null, 204);
    }

    /**
     * Test a field mapping against live remote data (preview mapped output).
     */
    public function preview(Request $request, string $connectionUid, string $mappingUid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)
            ->where('uid', $connectionUid)
            ->firstOrFail();

        $mapping = RelovaFieldMapping::forConnection($connection->id)
            ->where('uid', $mappingUid)
            ->firstOrFail();

        $connectionManager = app(\Relova\Services\RelovaConnectionManager::class);
        $limit = min((int) $request->get('limit', 10), 100);

        $rows = $connectionManager->preview($connection, $mapping->source_table, [], $limit);

        $mappedRows = array_map(fn (array $row) => $mapping->applyToRow($row), $rows);

        return response()->json([
            'data' => [
                'raw' => $rows,
                'mapped' => $mappedRows,
            ],
            'meta' => [
                'mapping' => $mapping->name,
                'source_table' => $mapping->source_table,
                'target_module' => $mapping->target_module,
                'row_count' => count($rows),
            ],
        ]);
    }
}
