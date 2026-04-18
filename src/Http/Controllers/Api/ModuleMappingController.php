<?php

declare(strict_types=1);

namespace Relova\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Relova\Models\ConnectorModuleMapping;
use Relova\Models\RelovaConnection;

class ModuleMappingController extends Controller
{
    public function index(Request $request, string $connectionUid): JsonResponse
    {
        $connection = $this->find($request, $connectionUid);

        $mappings = ConnectorModuleMapping::query()
            ->where('tenant_id', $connection->tenant_id)
            ->where('connection_id', $connection->id)
            ->orderBy('module_key')
            ->get()
            ->map(fn (ConnectorModuleMapping $m) => $this->serialize($m));

        return response()->json(['data' => $mappings]);
    }

    public function show(Request $request, string $connectionUid, string $mappingUid): JsonResponse
    {
        $mapping = $this->findMapping($request, $connectionUid, $mappingUid);

        return response()->json(['data' => $this->serialize($mapping)]);
    }

    public function store(Request $request, string $connectionUid): JsonResponse
    {
        $connection = $this->find($request, $connectionUid);
        $data = $this->validatePayload($request);

        $mapping = ConnectorModuleMapping::query()->create($data + [
            'tenant_id' => $connection->tenant_id,
            'connection_id' => $connection->id,
        ]);

        return response()->json(['data' => $this->serialize($mapping)], 201);
    }

    public function update(Request $request, string $connectionUid, string $mappingUid): JsonResponse
    {
        $mapping = $this->findMapping($request, $connectionUid, $mappingUid);
        $data = $this->validatePayload($request, updating: true);
        $mapping->fill($data)->save();

        return response()->json(['data' => $this->serialize($mapping)]);
    }

    public function destroy(Request $request, string $connectionUid, string $mappingUid): JsonResponse
    {
        $mapping = $this->findMapping($request, $connectionUid, $mappingUid);
        $mapping->delete();

        return response()->json(['data' => ['uid' => $mappingUid, 'deleted' => true]]);
    }

    private function find(Request $request, string $uid): RelovaConnection
    {
        return RelovaConnection::query()
            ->where('tenant_id', (string) $request->attributes->get('relova_tenant_id'))
            ->where('uid', $uid)
            ->firstOrFail();
    }

    private function findMapping(Request $request, string $connectionUid, string $mappingUid): ConnectorModuleMapping
    {
        $connection = $this->find($request, $connectionUid);

        return ConnectorModuleMapping::query()
            ->where('tenant_id', $connection->tenant_id)
            ->where('connection_id', $connection->id)
            ->where('uid', $mappingUid)
            ->firstOrFail();
    }

    private function validatePayload(Request $request, bool $updating = false): array
    {
        $need = $updating ? 'sometimes' : 'required';

        return Validator::make($request->all(), [
            'module_key' => [$need, 'string', 'max:64'],
            'remote_table' => [$need, 'string', 'max:255'],
            'field_mappings' => [$need, 'array'],
            'display_fields' => [$need, 'array'],
            'filters' => ['sometimes', 'array'],
            'sync_behavior' => ['sometimes', 'in:virtual,snapshot_cache,on_demand'],
            'cache_ttl_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'active' => ['sometimes', 'boolean'],
        ])->validate();
    }

    private function serialize(ConnectorModuleMapping $m): array
    {
        return [
            'uid' => $m->uid,
            'connection_id' => $m->connection_id,
            'module_key' => $m->module_key,
            'remote_table' => $m->remote_table,
            'field_mappings' => $m->field_mappings ?? [],
            'display_fields' => $m->display_fields ?? [],
            'filters' => $m->filters ?? [],
            'sync_behavior' => $m->sync_behavior,
            'cache_ttl_minutes' => $m->cache_ttl_minutes,
            'active' => (bool) $m->active,
        ];
    }
}
