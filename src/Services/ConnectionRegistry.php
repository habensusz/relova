<?php

declare(strict_types=1);

namespace Relova\Services;

use Relova\Models\RelovaConnection;
use Relova\Security\CredentialEncryptor;
use Relova\Security\SsrfGuard;

/**
 * Central authority for Relova connection configuration.
 *
 * - Builds the driver config array from a connection (decrypting credentials).
 * - Manages SSH tunnel lifecycle via SshTunnelService when ssh_enabled is set.
 * - Exposes host validation via SsrfGuard before any remote call is attempted.
 * - Provides health-state update helpers.
 *
 * Does not itself execute queries — those go through QueryExecutor.
 */
class ConnectionRegistry
{
    public function __construct(
        private CredentialEncryptor $encryptor,
        private SsrfGuard $ssrfGuard,
        private SshTunnelService $sshTunnel,
    ) {}

    /**
     * Build the driver config array for a connection.
     * Credentials are decrypted on-demand and never stored on the connection model.
     *
     * @return array<string, mixed>
     */
    public function buildConfig(RelovaConnection $connection): array
    {
        $credentials = $connection->credentials_encrypted
            ? $this->encryptor->decrypt($connection->credentials_encrypted, (string) $connection->tenant_id)
            : [];

        return array_merge([
            'driver' => $connection->driver,
            'host' => $connection->host,
            'port' => $connection->port,
            'database' => $connection->database,
            'timeout' => (int) config('relova.connection_timeout', 10),
        ], $credentials, $connection->options ?? []);
    }

    /**
     * Validate that a connection's host is reachable per SSRF policy.
     * Throws SsrfException when blocked.
     */
    public function assertHostAllowed(RelovaConnection $connection): void
    {
        $host = $connection->ssh_enabled
            ? (string) $connection->ssh_host
            : (string) $connection->host;

        if ($host === '') {
            return;
        }

        $this->ssrfGuard->validate($host);
    }

    /**
     * Execute a callback with an SSH tunnel when the connection requires it.
     *
     * If ssh_enabled is false, the callback is invoked directly with the standard config.
     * If ssh_enabled is true, an SSH tunnel is established first, the config is rewritten
     * to point at 127.0.0.1:localPort, and the tunnel is torn down after the callback.
     *
     * @template T
     *
     * @param  callable(array<string, mixed>): T  $callback  Receives the driver config array
     * @return T
     */
    public function withTunnel(RelovaConnection $connection, callable $callback): mixed
    {
        if (! $connection->ssh_enabled) {
            return $callback($this->buildConfig($connection));
        }

        $session = $this->sshTunnel->establish($connection);

        try {
            $config = $this->buildConfig($connection);
            // Redirect to the local tunnel port instead of the remote host
            $config['host'] = '127.0.0.1';
            $config['port'] = $session['localPort'];

            return $callback($config);
        } finally {
            $this->sshTunnel->teardown($session);
        }
    }

    /**
     * Mark a connection as healthy after a successful remote round-trip.
     */
    public function markHealthy(RelovaConnection $connection): void
    {
        $connection->forceFill([
            'status' => 'active',
            'last_checked_at' => now(),
            'last_error' => null,
        ])->save();
    }

    /**
     * Mark a connection as erroring with the supplied message.
     */
    public function markError(RelovaConnection $connection, string $error, string $status = 'error'): void
    {
        $connection->forceFill([
            'status' => $status,
            'last_checked_at' => now(),
            'last_error' => $error,
        ])->save();
    }
}
