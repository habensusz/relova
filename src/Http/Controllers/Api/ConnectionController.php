<?php

declare(strict_types=1);

namespace Relova\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Relova\Models\RelovaConnection;
use Relova\Services\DriverRegistry;
use Relova\Services\RelovaConnectionManager;

class ConnectionController extends Controller
{
    public function __construct(
        protected RelovaConnectionManager $connectionManager,
        protected DriverRegistry $driverRegistry,
    ) {}

    /**
     * List all connections for the authenticated tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connections = RelovaConnection::forTenant($tenantId)
            ->select(['id', 'uid', 'name', 'description', 'enabled', 'driver_type', 'host', 'port', 'database_name', 'schema_name', 'health_status', 'health_message', 'last_health_check_at', 'cache_ttl', 'query_mode', 'created_at', 'updated_at'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $connections,
            'meta' => [
                'total' => $connections->count(),
                'available_drivers' => array_keys($this->driverRegistry->getRegistered()),
            ],
        ]);
    }

    /**
     * Create a new connection.
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'driver_type' => 'required|string|in:'.implode(',', array_keys($this->driverRegistry->getRegistered())),
            'host' => 'required|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'database_name' => 'required|string|max:255',
            'schema_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'cache_ttl' => 'nullable|integer|min:0|max:86400',
            'query_mode' => 'nullable|in:virtual,snapshot,on_demand',
            'enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $password = $data['password'];
        unset($data['password']);

        $data['tenant_id'] = $tenantId;

        $connection = RelovaConnection::create($data);
        $connection->password = $password;
        $connection->save();

        return response()->json(['data' => $connection->makeHidden('encrypted_password')], 201);
    }

    /**
     * Show a specific connection.
     */
    public function show(Request $request, string $uid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)->where('uid', $uid)->firstOrFail();

        return response()->json(['data' => $connection->makeHidden('encrypted_password')]);
    }

    /**
     * Update a connection.
     */
    public function update(Request $request, string $uid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)->where('uid', $uid)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'host' => 'sometimes|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'database_name' => 'sometimes|string|max:255',
            'schema_name' => 'nullable|string|max:255',
            'username' => 'sometimes|string|max:255',
            'password' => 'nullable|string|max:255',
            'cache_ttl' => 'nullable|integer|min:0|max:86400',
            'query_mode' => 'nullable|in:virtual,snapshot,on_demand',
            'enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (isset($data['password'])) {
            $connection->password = $data['password'];
            unset($data['password']);
        }

        $connection->update($data);

        return response()->json(['data' => $connection->fresh()->makeHidden('encrypted_password')]);
    }

    /**
     * Delete a connection.
     */
    public function destroy(Request $request, string $uid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)->where('uid', $uid)->firstOrFail();
        $connection->delete();

        return response()->json(null, 204);
    }

    /**
     * Test connection connectivity.
     */
    public function test(Request $request, string $uid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)->where('uid', $uid)->firstOrFail();

        $result = $this->connectionManager->test($connection);

        return response()->json([
            'data' => [
                'success' => $result,
                'health_status' => $connection->fresh()->health_status,
                'tested_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Run health check on a connection.
     */
    public function healthCheck(Request $request, string $uid): JsonResponse
    {
        $tenantId = $request->attributes->get('relova_tenant_id');

        $connection = RelovaConnection::forTenant($tenantId)->where('uid', $uid)->firstOrFail();

        $result = $this->connectionManager->healthCheck($connection);

        return response()->json(['data' => $result]);
    }

    /**
     * Get available driver types.
     */
    public function drivers(): JsonResponse
    {
        return response()->json([
            'data' => $this->driverRegistry->getDriverInfo(),
        ]);
    }
}
