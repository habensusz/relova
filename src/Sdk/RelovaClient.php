<?php

declare(strict_types=1);

namespace Relova\Sdk;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Relova SDK Client — wraps the Relova REST API for developer convenience.
 *
 * itenance uses this SDK internally. External customers building
 * their own solutions use the same SDK. The experience is identical.
 */
class RelovaClient
{
    protected string $baseUrl;

    protected string $apiKey;

    protected int $timeout;

    public function __construct(
        string $baseUrl,
        string $apiKey,
        int $timeout = 30,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
    }

    /**
     * Create from config (for internal use within the same Laravel app).
     */
    public static function fromConfig(): self
    {
        return new self(
            baseUrl: config('relova.api.base_url', url(config('relova.api.prefix', 'api/relova/v1'))),
            apiKey: config('relova.api.internal_key', ''),
            timeout: (int) config('relova.query_timeout', 30),
        );
    }

    // --- Connections ---

    public function listConnections(): array
    {
        return $this->http()->get("{$this->baseUrl}/connections")->json();
    }

    public function createConnection(array $data): array
    {
        return $this->http()->post("{$this->baseUrl}/connections", $data)->json();
    }

    public function getConnection(string $uid): array
    {
        return $this->http()->get("{$this->baseUrl}/connections/{$uid}")->json();
    }

    public function updateConnection(string $uid, array $data): array
    {
        return $this->http()->put("{$this->baseUrl}/connections/{$uid}", $data)->json();
    }

    public function deleteConnection(string $uid): bool
    {
        return $this->http()->delete("{$this->baseUrl}/connections/{$uid}")->successful();
    }

    public function testConnection(string $uid): array
    {
        return $this->http()->post("{$this->baseUrl}/connections/{$uid}/test")->json();
    }

    public function healthCheck(string $uid): array
    {
        return $this->http()->get("{$this->baseUrl}/connections/{$uid}/health")->json();
    }

    // --- Schema ---

    public function getTables(string $connectionUid): array
    {
        return $this->http()->get("{$this->baseUrl}/connections/{$connectionUid}/tables")->json();
    }

    public function getColumns(string $connectionUid, string $table): array
    {
        return $this->http()->get("{$this->baseUrl}/connections/{$connectionUid}/tables/{$table}/columns")->json();
    }

    public function previewTable(string $connectionUid, string $table, int $limit = 50, array $columns = []): array
    {
        return $this->http()->get("{$this->baseUrl}/connections/{$connectionUid}/tables/{$table}/preview", [
            'limit' => $limit,
            'columns' => $columns,
        ])->json();
    }

    public function flushSchemaCache(string $connectionUid): array
    {
        return $this->http()->post("{$this->baseUrl}/connections/{$connectionUid}/flush-cache")->json();
    }

    // --- Queries ---

    public function query(string $connectionUid, string $sql, array $bindings = []): array
    {
        return $this->http()->post("{$this->baseUrl}/connections/{$connectionUid}/query", [
            'sql' => $sql,
            'bindings' => $bindings,
        ])->json();
    }

    public function select(string $connectionUid, string $table, array $options = []): array
    {
        $payload = array_merge(['table' => $table], $options);

        return $this->http()->post("{$this->baseUrl}/connections/{$connectionUid}/select", $payload)->json();
    }

    // --- Entity References ---

    public function listReferences(string $connectionUid, int $perPage = 50): array
    {
        return $this->http()->get("{$this->baseUrl}/connections/{$connectionUid}/references", [
            'per_page' => $perPage,
        ])->json();
    }

    public function resolveReference(string $connectionUid, array $data): array
    {
        return $this->http()->post("{$this->baseUrl}/connections/{$connectionUid}/references/resolve", $data)->json();
    }

    public function getReference(string $connectionUid, string $referenceUid): array
    {
        return $this->http()->get("{$this->baseUrl}/connections/{$connectionUid}/references/{$referenceUid}")->json();
    }

    public function refreshReferenceSnapshot(string $connectionUid, string $referenceUid, array $snapshotColumns = []): array
    {
        return $this->http()->post("{$this->baseUrl}/connections/{$connectionUid}/references/{$referenceUid}/refresh", [
            'snapshot_columns' => $snapshotColumns,
        ])->json();
    }

    public function searchRemoteEntities(string $connectionUid, array $data): array
    {
        return $this->http()->post("{$this->baseUrl}/connections/{$connectionUid}/references/search", $data)->json();
    }

    // --- Field Mappings ---

    public function listMappings(string $connectionUid): array
    {
        return $this->http()->get("{$this->baseUrl}/connections/{$connectionUid}/mappings")->json();
    }

    public function createMapping(string $connectionUid, array $data): array
    {
        return $this->http()->post("{$this->baseUrl}/connections/{$connectionUid}/mappings", $data)->json();
    }

    public function getMapping(string $connectionUid, string $mappingUid): array
    {
        return $this->http()->get("{$this->baseUrl}/connections/{$connectionUid}/mappings/{$mappingUid}")->json();
    }

    public function updateMapping(string $connectionUid, string $mappingUid, array $data): array
    {
        return $this->http()->put("{$this->baseUrl}/connections/{$connectionUid}/mappings/{$mappingUid}", $data)->json();
    }

    public function deleteMapping(string $connectionUid, string $mappingUid): bool
    {
        return $this->http()->delete("{$this->baseUrl}/connections/{$connectionUid}/mappings/{$mappingUid}")->successful();
    }

    public function previewMapping(string $connectionUid, string $mappingUid, int $limit = 10): array
    {
        return $this->http()->get("{$this->baseUrl}/connections/{$connectionUid}/mappings/{$mappingUid}/preview", [
            'limit' => $limit,
        ])->json();
    }

    // --- Drivers ---

    public function getDrivers(): array
    {
        return $this->http()->get("{$this->baseUrl}/drivers")->json();
    }

    // --- HTTP Client ---

    protected function http(): PendingRequest
    {
        return Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson();
    }
}
