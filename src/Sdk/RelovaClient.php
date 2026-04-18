<?php

declare(strict_types=1);

namespace Relova\Sdk;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Relova SDK — thin, versionless HTTP client for the Relova REST API.
 *
 * Host apps (including itenance) consume Relova exclusively through this
 * client or by calling the REST API directly. No Eloquent relationship is
 * ever shared across the boundary.
 *
 * Every method that returns row data does so by explicit request; nothing
 * is implicitly fetched or cached.
 */
class RelovaClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $apiKey,
        protected int $timeout = 30,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Build a client from the host app's config.
     */
    public static function fromConfig(): self
    {
        return new self(
            baseUrl: (string) config('relova.api.base_url', url((string) config('relova.api.prefix', 'api/relova/v1'))),
            apiKey: (string) config('relova.api.internal_key', ''),
            timeout: (int) config('relova.query_timeout', 30),
        );
    }

    // -------------------------------------------------------------------------
    // Drivers
    // -------------------------------------------------------------------------

    public function getDrivers(): array
    {
        return $this->http()->get("{$this->baseUrl}/drivers")->json();
    }

    // -------------------------------------------------------------------------
    // Connections
    // -------------------------------------------------------------------------

    public function listConnections(): array
    {
        return $this->http()->get("{$this->baseUrl}/connections")->json();
    }

    public function getConnection(string $uid): array
    {
        return $this->http()->get("{$this->baseUrl}/connections/{$uid}")->json();
    }

    public function createConnection(array $data): array
    {
        return $this->http()->post("{$this->baseUrl}/connections", $data)->json();
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

    // -------------------------------------------------------------------------
    // Schema (metadata only)
    // -------------------------------------------------------------------------

    public function getTables(string $connectionUid): array
    {
        return $this->http()->get("{$this->baseUrl}/connections/{$connectionUid}/tables")->json();
    }

    public function getColumns(string $connectionUid, string $table): array
    {
        return $this->http()
            ->get("{$this->baseUrl}/connections/{$connectionUid}/tables/{$table}/columns")
            ->json();
    }

    public function preview(string $connectionUid, string $table, int $limit = 25, array $columns = []): array
    {
        return $this->http()
            ->get("{$this->baseUrl}/connections/{$connectionUid}/tables/{$table}/preview", [
                'limit' => $limit,
                'columns' => implode(',', $columns),
            ])->json();
    }

    public function flushSchemaCache(string $connectionUid): array
    {
        return $this->http()->post("{$this->baseUrl}/connections/{$connectionUid}/flush-cache")->json();
    }

    // -------------------------------------------------------------------------
    // Pass-through queries (whitelisted parameters only)
    // -------------------------------------------------------------------------

    /**
     * Execute a read-only SELECT over whitelisted parameters.
     *
     * @param  array<int, string>  $columns
     * @param  array<int, array{0: string, 1: string, 2: mixed}>  $conditions
     */
    public function query(
        string $connectionUid,
        string $table,
        array $columns = ['*'],
        array $conditions = [],
        int $limit = 100,
        int $offset = 0,
    ): array {
        return $this->http()
            ->post("{$this->baseUrl}/connections/{$connectionUid}/query", [
                'table' => $table,
                'columns' => $columns,
                'conditions' => $conditions,
                'limit' => $limit,
                'offset' => $offset,
            ])->json();
    }

    /**
     * Typeahead-style LIKE search over a single column.
     *
     * @param  array<int, string>  $displayColumns
     */
    public function search(
        string $connectionUid,
        string $table,
        string $searchColumn,
        string $searchTerm,
        array $displayColumns = [],
        int $limit = 20,
    ): array {
        return $this->http()
            ->post("{$this->baseUrl}/connections/{$connectionUid}/search", [
                'table' => $table,
                'search_column' => $searchColumn,
                'search_term' => $searchTerm,
                'display_columns' => $displayColumns,
                'limit' => $limit,
            ])->json();
    }

    /**
     * Stream results by paging through the pass-through query.
     * Yields one row at a time without buffering the whole set.
     *
     * @param  array<int, string>  $columns
     * @param  array<int, array{0: string, 1: string, 2: mixed}>  $conditions
     * @return \Generator<int, array<string, mixed>>
     */
    public function browse(
        string $connectionUid,
        string $table,
        array $columns = ['*'],
        array $conditions = [],
        int $pageSize = 250,
    ): \Generator {
        $offset = 0;
        while (true) {
            $response = $this->query($connectionUid, $table, $columns, $conditions, $pageSize, $offset);
            $rows = $response['data'] ?? [];
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                yield $row;
            }
            if (count($rows) < $pageSize) {
                break;
            }
            $offset += $pageSize;
        }
    }

    // -------------------------------------------------------------------------
    // Virtual entity references
    // -------------------------------------------------------------------------

    public function listReferences(string $connectionUid, ?string $table = null, int $limit = 50): array
    {
        return $this->http()
            ->get("{$this->baseUrl}/connections/{$connectionUid}/references", array_filter([
                'table' => $table,
                'limit' => $limit,
            ]))->json();
    }

    /**
     * Find-or-create the virtual reference for a specific remote row.
     * Host apps persist the returned reference uid as their FK.
     *
     * @param  array<int, string>  $displayFields
     */
    public function selectEntity(
        string $connectionUid,
        string $remoteTable,
        string $remotePkColumn,
        string $remotePkValue,
        array $displayFields = [],
    ): array {
        return $this->http()
            ->post("{$this->baseUrl}/connections/{$connectionUid}/references/resolve", [
                'remote_table' => $remoteTable,
                'remote_pk_column' => $remotePkColumn,
                'remote_pk_value' => $remotePkValue,
                'display_fields' => $displayFields,
            ])->json();
    }

    /**
     * Resolve display data for an existing reference (fresh snapshot, live
     * fetch if stale, stale snapshot if remote is unreachable).
     *
     * @param  array<int, string>  $displayFields
     */
    public function getDisplayData(string $connectionUid, string $referenceUid, array $displayFields = []): array
    {
        return $this->http()
            ->get("{$this->baseUrl}/connections/{$connectionUid}/references/{$referenceUid}", [
                'display_fields' => implode(',', $displayFields),
            ])->json();
    }

    public function refreshReference(string $connectionUid, string $referenceUid): array
    {
        return $this->http()
            ->post("{$this->baseUrl}/connections/{$connectionUid}/references/{$referenceUid}/refresh")
            ->json();
    }

    // -------------------------------------------------------------------------
    // Module mappings
    // -------------------------------------------------------------------------

    public function listMappings(string $connectionUid): array
    {
        return $this->http()->get("{$this->baseUrl}/connections/{$connectionUid}/mappings")->json();
    }

    public function getMapping(string $connectionUid, string $mappingUid): array
    {
        return $this->http()->get("{$this->baseUrl}/connections/{$connectionUid}/mappings/{$mappingUid}")->json();
    }

    public function createMapping(string $connectionUid, array $data): array
    {
        return $this->http()->post("{$this->baseUrl}/connections/{$connectionUid}/mappings", $data)->json();
    }

    public function updateMapping(string $connectionUid, string $mappingUid, array $data): array
    {
        return $this->http()->put("{$this->baseUrl}/connections/{$connectionUid}/mappings/{$mappingUid}", $data)->json();
    }

    public function deleteMapping(string $connectionUid, string $mappingUid): bool
    {
        return $this->http()->delete("{$this->baseUrl}/connections/{$connectionUid}/mappings/{$mappingUid}")->successful();
    }

    // -------------------------------------------------------------------------
    // HTTP
    // -------------------------------------------------------------------------

    protected function http(): PendingRequest
    {
        return Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson();
    }
}
