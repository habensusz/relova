<?php

declare(strict_types=1);

namespace Relova\Drivers;

use Relova\Contracts\ConnectorDriver;
use Relova\Exceptions\ConnectionException;

/**
 * Stub for the REST/OData API connector driver.
 *
 * Spec §Phase 4 — not yet implemented. Registered so the contract is visible
 * in DriverRegistry and config UIs, but every operation throws.
 */
class ApiDriver implements ConnectorDriver
{
    public function getDriverName(): string
    {
        return 'api';
    }

    public function getDisplayName(): string
    {
        return 'REST / OData API';
    }

    public function getDefaultPort(): int
    {
        return 443;
    }

    public function getConfigSchema(): array
    {
        return [
            'base_url' => ['type' => 'string', 'label' => 'Base URL', 'required' => true, 'default' => 'https://'],
            'auth_type' => ['type' => 'string', 'label' => 'Auth type', 'required' => true, 'default' => 'bearer'],
            'auth_token' => ['type' => 'password', 'label' => 'Auth token', 'required' => false, 'default' => ''],
        ];
    }

    public function testConnection(array $config): bool
    {
        throw new ConnectionException(
            message: 'ApiDriver is not yet implemented (spec Phase 4 — pending).',
            driverName: $this->getDriverName(),
        );
    }

    public function getTables(array $config): array
    {
        throw new \RuntimeException('ApiDriver::getTables() is not yet implemented.');
    }

    public function getColumns(array $config, string $table): array
    {
        throw new \RuntimeException('ApiDriver::getColumns() is not yet implemented.');
    }

    public function query(array $config, string $sql, array $bindings = [], array $options = []): \Generator
    {
        throw new \RuntimeException('ApiDriver::query() is not yet implemented.');
        yield; // unreachable; satisfies generator return type
    }

    public function buildPreviewQuery(string $table, array $columns = [], int $limit = 100): string
    {
        throw new \RuntimeException('ApiDriver::buildPreviewQuery() is not yet implemented.');
    }
}
