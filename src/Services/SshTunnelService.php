<?php

declare(strict_types=1);

namespace Relova\Services;

use Relova\Exceptions\ConnectionException;
use Relova\Models\RelovaConnection;

/**
 * Manages SSH tunnel lifecycle for Relova connections.
 *
 * Opens a local port-forward to the remote database host through an SSH bastion/jump host.
 * The tunnel is established on demand, used for a single query/operation, then torn down.
 *
 * Key-based auth is recommended and works cross-platform (Linux, macOS, Windows with OpenSSH).
 * Password-based auth requires `sshpass` to be installed on the server (Linux only).
 *
 * @example
 *   $session = $sshTunnel->establish($connection);
 *   try {
 *       $row = $pdo->query('SELECT 1');
 *   } finally {
 *       $sshTunnel->teardown($session);
 *   }
 */
class SshTunnelService
{
    private const READY_TIMEOUT_SECONDS = 15;

    private const READY_CHECK_INTERVAL_MS = 150;

    private const TEMP_KEY_PREFIX = 'relova_ssh_key_';

    /**
     * Establish an SSH port-forward tunnel and return a session handle.
     *
     * @param  RelovaConnection  $connection  Connection with SSH config populated
     * @return array{process: resource, pipes: array, localPort: int, tempKeyPath: ?string}
     *
     * @throws ConnectionException If the tunnel cannot be started or does not become ready
     */
    public function establish(RelovaConnection $connection): array
    {
        $localPort = $this->findFreePort();
        $sshConfig = $connection->toSshConfig();
        $targetHost = $connection->host ?? '127.0.0.1';
        $targetPort = (int) ($connection->port ?? 5432);

        $tempKeyPath = null;
        $identityArgs = '';

        if ($sshConfig['auth_method'] === 'key') {
            $privateKey = $sshConfig['private_key'] ?? null;

            if (empty($privateKey)) {
                throw new ConnectionException(
                    message: 'SSH tunnel is configured for key authentication but no private key was provided.',
                    driverName: 'ssh',
                    host: $sshConfig['host'],
                );
            }

            $tempKeyPath = $this->writeTempKey($privateKey);
            $identityArgs = '-i '.escapeshellarg($tempKeyPath);

            if (! empty($sshConfig['passphrase'])) {
                // sshpass can handle key passphrases on Linux
                if ($this->hasSshpass()) {
                    $identityArgs = sprintf(
                        'sshpass -p %s ssh %s',
                        escapeshellarg($sshConfig['passphrase']),
                        $identityArgs,
                    );
                }
                // On Windows / without sshpass, we proceed and SSH will fail if key is passphrase-protected
            }
        }

        $sshHost = escapeshellarg($sshConfig['host']);
        $sshUser = escapeshellarg($sshConfig['user']);
        $sshPort = (int) ($sshConfig['port'] ?? 22);

        if ($sshConfig['auth_method'] === 'password') {
            if (! $this->hasSshpass()) {
                throw new ConnectionException(
                    message: 'SSH password authentication requires `sshpass` to be installed on the server. '.
                        'Alternatively, use SSH key authentication.',
                    driverName: 'ssh',
                    host: $sshConfig['host'],
                );
            }

            $sshPassword = escapeshellarg($sshConfig['password'] ?? '');
            $cmd = sprintf(
                'sshpass -p %s ssh -N -L %d:%s:%d -l %s -p %d -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=no %s',
                $sshPassword,
                $localPort,
                $targetHost,
                $targetPort,
                $sshUser,
                $sshPort,
                $sshHost,
            );
        } else {
            $cmd = sprintf(
                'ssh -N -L %d:%s:%d -l %s -p %d -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes %s %s',
                $localPort,
                $targetHost,
                $targetPort,
                $sshUser,
                $sshPort,
                $identityArgs,
                $sshHost,
            );
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (! is_resource($process)) {
            if ($tempKeyPath) {
                @unlink($tempKeyPath);
            }

            throw new ConnectionException(
                message: 'Failed to start SSH tunnel process. Ensure `ssh` is installed and accessible in PATH.',
                driverName: 'ssh',
                host: $sshConfig['host'],
            );
        }

        // Close stdin — SSH does not need interactive input in BatchMode
        fclose($pipes[0]);
        unset($pipes[0]);

        try {
            $this->waitForLocalPort($localPort, $process);
        } catch (ConnectionException $e) {
            // Read stderr for a better error message
            $stderr = stream_get_contents($pipes[2] ?? null) ?: '';
            $this->cleanupProcess($process, $pipes, $tempKeyPath);

            throw new ConnectionException(
                message: $e->getMessage().($stderr ? ' SSH stderr: '.trim($stderr) : ''),
                driverName: 'ssh',
                host: $sshConfig['host'],
                previous: $e,
            );
        }

        return [
            'process' => $process,
            'pipes' => $pipes,
            'localPort' => $localPort,
            'tempKeyPath' => $tempKeyPath,
        ];
    }

    /**
     * Tear down an SSH tunnel session created by `establish()`.
     *
     * @param  array  $session  The array returned by `establish()`
     */
    public function teardown(array $session): void
    {
        $this->cleanupProcess(
            $session['process'] ?? null,
            $session['pipes'] ?? [],
            $session['tempKeyPath'] ?? null,
        );
    }

    /**
     * Find a free local TCP port by briefly binding to port 0.
     */
    private function findFreePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if (! $sock) {
            throw new \RuntimeException("Cannot allocate a free local port for SSH tunnel: {$errstr}");
        }

        $addr = stream_socket_get_name($sock, false);
        fclose($sock);

        // $addr is '127.0.0.1:PORT'
        return (int) substr($addr, strrpos($addr, ':') + 1);
    }

    /**
     * Poll the local port until it starts accepting connections or timeout.
     */
    private function waitForLocalPort(int $port, mixed $process): void
    {
        $deadline = microtime(true) + self::READY_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            // Check if the SSH process died early
            $status = proc_get_status($process);
            if (! $status['running']) {
                throw new ConnectionException(
                    message: 'SSH tunnel process exited prematurely before the tunnel was ready.',
                    driverName: 'ssh',
                    host: null,
                );
            }

            $sock = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 0.3);

            if ($sock) {
                fclose($sock);

                return;
            }

            usleep(self::READY_CHECK_INTERVAL_MS * 1000);
        }

        throw new ConnectionException(
            message: sprintf(
                'SSH tunnel did not become ready within %d seconds. Check your SSH credentials and host.',
                self::READY_TIMEOUT_SECONDS,
            ),
            driverName: 'ssh',
            host: null,
        );
    }

    /**
     * Write a private key to a secure temp file and return its path.
     * Normalises line endings to LF — required by OpenSSH on all platforms.
     */
    private function writeTempKey(string $keyContent): string
    {
        // Normalise CRLF → LF (Livewire/browsers may introduce \r\n)
        $keyContent = str_replace("\r\n", "\n", $keyContent);
        $keyContent = str_replace("\r", "\n", $keyContent);

        // Ensure the key ends with a newline
        $keyContent = rtrim($keyContent)."\n";

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.self::TEMP_KEY_PREFIX.bin2hex(random_bytes(8)).'.pem';

        // Open in binary mode ('wb') to prevent Windows EOL translation
        $fh = fopen($path, 'wb');
        fwrite($fh, $keyContent);
        fclose($fh);

        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($path, 0600);
        } else {
            // Windows OpenSSH requires the key file to be accessible only by the current user
            // Remove inherited permissions and grant full control to current user only
            $escapedPath = escapeshellarg($path);
            exec("icacls {$escapedPath} /inheritance:r /grant:r \"%USERNAME%\":F 2>nul");
        }

        return $path;
    }

    /**
     * Check whether `sshpass` is available on this system.
     */
    private function hasSshpass(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return false;
        }

        exec('which sshpass 2>/dev/null', $output, $code);

        return $code === 0;
    }

    /**
     * Terminate process and clean up pipes and temp key file.
     */
    private function cleanupProcess(mixed $process, array $pipes, ?string $tempKeyPath): void
    {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }

        if (is_resource($process)) {
            @proc_terminate($process);
            @proc_close($process);
        }

        if ($tempKeyPath && file_exists($tempKeyPath)) {
            @unlink($tempKeyPath);
        }
    }
}
