<?php

declare(strict_types=1);

namespace Relova\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Relova\Exceptions\ConnectionException;
use Relova\Exceptions\SsrfException;
use Relova\Jobs\HealthCheckConnector;
use Relova\Models\RelovaConnection;
use Relova\Services\ConnectionRegistry;
use Relova\Services\DriverRegistry;

class ConnectionController extends Controller
{
    public function __construct(
        private readonly ConnectionRegistry $registry,
        private readonly DriverRegistry $drivers,
    ) {}

    public function drivers(): JsonResponse
    {
        $list = [];
        foreach ($this->drivers->getDriverInfo() as $key => $info) {
            $list[] = ['key' => $key] + $info;
        }

        return response()->json(['data' => $list]);
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $connections = RelovaConnection::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get()
            ->map(fn (RelovaConnection $c) => $this->serialize($c));

        return response()->json(['data' => $connections]);
    }

    public function show(Request $request, string $uid): JsonResponse
    {
        $connection = $this->find($request, $uid);

        return response()->json(['data' => $this->serialize($connection)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        /** @var RelovaConnection $connection */
        $connection = RelovaConnection::query()->make([
            'tenant_id' => $this->tenantId($request),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'driver' => $data['driver'],
            'host' => $data['host'] ?? null,
            'port' => $data['port'] ?? null,
            'database' => $data['database'] ?? null,
            'options' => $data['options'] ?? [],
            'ssh_enabled' => (bool) ($data['ssh_enabled'] ?? false),
            'ssh_host' => $data['ssh_host'] ?? null,
            'ssh_port' => $data['ssh_port'] ?? 22,
            'status' => 'active',
        ]);
        $connection->setCredentials($data['credentials'] ?? []);
        $connection->save();

        return response()->json(['data' => $this->serialize($connection)], 201);
    }

    public function update(Request $request, string $uid): JsonResponse
    {
        $connection = $this->find($request, $uid);
        $data = $this->validatePayload($request, updating: true);

        if (array_key_exists('name', $data)) {
            $connection->name = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $connection->description = $data['description'];
        }
        foreach (['host', 'port', 'database', 'ssh_host', 'ssh_port'] as $col) {
            if (array_key_exists($col, $data)) {
                $connection->{$col} = $data[$col];
            }
        }
        if (array_key_exists('options', $data)) {
            $connection->options = $data['options'];
        }
        if (array_key_exists('ssh_enabled', $data)) {
            $connection->ssh_enabled = (bool) $data['ssh_enabled'];
        }
        if (array_key_exists('credentials', $data) && is_array($data['credentials'])) {
            $connection->setCredentials($data['credentials']);
        }
        $connection->save();

        return response()->json(['data' => $this->serialize($connection)]);
    }

    public function destroy(Request $request, string $uid): JsonResponse
    {
        $connection = $this->find($request, $uid);
        $connection->delete();

        return response()->json(['data' => ['uid' => $uid, 'deleted' => true]]);
    }

    public function test(Request $request, string $uid): JsonResponse
    {
        $connection = $this->find($request, $uid);

        try {
            $this->registry->assertHostAllowed($connection);
            $driver = $this->drivers->resolve($connection->driver);

            $ok = $this->registry->withTunnel($connection, function (array $config) use ($driver) {
                return $driver->testConnection($config);
            });

            $ok ? $this->registry->markHealthy($connection) : $this->registry->markError($connection, 'Connection test returned false');

            return response()->json(['data' => ['ok' => $ok, 'status' => $connection->status]]);
        } catch (SsrfException|ConnectionException $e) {
            $this->registry->markError($connection, $e->getMessage(), $e instanceof SsrfException ? 'unreachable' : 'error');

            return response()->json(['data' => ['ok' => false, 'error' => $e->getMessage()]], 200);
        }
    }

    public function healthCheck(Request $request, string $uid): JsonResponse
    {
        $connection = $this->find($request, $uid);
        HealthCheckConnector::dispatch($connection->getKey());

        return response()->json(['data' => ['queued' => true]]);
    }

    private function find(Request $request, string $uid): RelovaConnection
    {
        return RelovaConnection::query()
            ->where('tenant_id', $this->tenantId($request))
            ->where('uid', $uid)
            ->firstOrFail();
    }

    private function tenantId(Request $request): string
    {
        return (string) $request->attributes->get('relova_tenant_id');
    }

    private function validatePayload(Request $request, bool $updating = false): array
    {
        $rules = [
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'driver' => [$updating ? 'sometimes' : 'required', 'string', 'max:32'],
            'host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'port' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
            'database' => ['sometimes', 'nullable', 'string', 'max:255'],
            'credentials' => ['sometimes', 'array'],
            'options' => ['sometimes', 'array'],
            'ssh_enabled' => ['sometimes', 'boolean'],
            'ssh_host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ssh_port' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
        ];

        return Validator::make($request->all(), $rules)->validate();
    }

    private function serialize(RelovaConnection $connection): array
    {
        return [
            'uid' => $connection->uid,
            'name' => $connection->name,
            'driver' => $connection->driver,
            'driver_label' => $connection->driverLabel,
            'options' => $connection->options ?? [],
            'ssh_enabled' => (bool) $connection->ssh_enabled,
            'status' => $connection->status,
            'last_checked_at' => optional($connection->last_checked_at)->toIso8601String(),
            'created_at' => optional($connection->created_at)->toIso8601String(),
            'updated_at' => optional($connection->updated_at)->toIso8601String(),
        ];
    }
}
