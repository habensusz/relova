<?php

declare(strict_types=1);

namespace Relova\Livewire;

use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Relova\Exceptions\ConnectionException;
use Relova\Models\ConnectorModuleMapping;
use Relova\Models\RelovaConnection;
use Relova\Models\VirtualEntityReference;
use Relova\Security\CredentialEncryptor;
use Relova\Services\ConnectionRegistry;
use Relova\Services\DriverRegistry;
use Relova\Services\SchemaInspector;
use Relova\Services\SyncEngine;
use Throwable;

/**
 * CRUD UI for Relova connections.
 *
 * Lists tenant-scoped connections, opens a slide-out form to create or edit,
 * tests connectivity through the configured driver, and toggles soft-delete.
 *
 * Credentials are encrypted via CredentialEncryptor before persistence and
 * are never read back into the form (only the connection metadata is shown
 * on edit; credentials must be re-entered to change them).
 */
#[Layout('components.layouts.app')]
class ConnectionManager extends Component
{
    public string $tenantId = '';

    public string $search = '';

    public bool $showForm = false;

    public bool $editing = false;

    public ?string $editingUid = null;

    /** @var array<string, string> */
    public array $availableDrivers = [];

    public string $name = '';

    public string $description = '';

    public string $driver = 'pgsql';

    public string $host = '';

    public ?int $port = null;

    public string $database = '';

    public string $schema = '';

    public string $username = '';

    public string $password = '';

    /** @var array<string, mixed> */
    public array $options = [];

    public string $optionsJson = '{}';

    public bool $sshEnabled = false;

    public string $sshHost = '';

    public int $sshPort = 22;

    public string $sshUser = '';

    public string $sshPrivateKey = '';

    public string $sshPassphrase = '';

    public ?int $cacheTtl = null;

    public ?string $testResult = null;

    public ?string $testError = null;

    public function mount(DriverRegistry $drivers): void
    {
        $this->tenantId = (string) (function_exists('tenant') && tenant() ? tenant('id') : '');
        $this->availableDrivers = collect($drivers->getRegistered())
            ->mapWithKeys(fn ($_, $key) => [$key => Str::headline($key)])
            ->all();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(string $uid): void
    {
        $connection = RelovaConnection::query()
            ->where('tenant_id', $this->tenantId)
            ->where('uid', $uid)
            ->firstOrFail();

        $this->resetForm();

        $this->editing = true;
        $this->editingUid = $connection->uid;
        $this->name = (string) $connection->name;
        $this->description = (string) ($connection->description ?? '');
        $this->driver = (string) $connection->driver;
        $this->host = (string) ($connection->host ?? '');
        $this->port = $connection->port;
        $this->database = (string) ($connection->database ?? '');
        $opts = (array) ($connection->options ?? []);
        $this->sshUser = (string) ($opts['ssh_user'] ?? '');
        unset($opts['ssh_user']);
        $this->schema = (string) ($opts['schema'] ?? '');
        unset($opts['schema']);
        $this->options = $opts;
        $this->optionsJson = json_encode($opts, JSON_PRETTY_PRINT) ?: '{}';
        $this->sshEnabled = (bool) $connection->ssh_enabled;
        $this->sshHost = (string) ($connection->ssh_host ?? '');
        $this->sshPort = (int) ($connection->ssh_port ?? 22);
        $this->cacheTtl = $connection->cache_ttl;

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    public function save(CredentialEncryptor $encryptor, SchemaInspector $inspector, SyncEngine $syncEngine): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'driver' => 'required|string',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'database' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:1024',
            'schema' => 'nullable|string|max:255',
            'optionsJson' => 'nullable|json',
            'cacheTtl' => 'nullable|integer|min:0',
            'sshEnabled' => 'boolean',
            'sshHost' => 'nullable|string|max:255',
            'sshPort' => 'nullable|integer|min:1|max:65535',
            'sshUser' => 'nullable|string|max:255',
            'sshPrivateKey' => 'nullable|string',
            'sshPassphrase' => 'nullable|string|max:1024',
        ]);

        $options = json_decode($this->optionsJson ?: '{}', true) ?: [];
        if ($this->schema !== '') {
            $options['schema'] = $this->schema;
        } else {
            unset($options['schema']);
        }
        if ($this->sshEnabled && $this->sshUser !== '') {
            $options['ssh_user'] = $this->sshUser;
        } else {
            unset($options['ssh_user']);
        }

        $payload = [
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'driver' => $this->driver,
            'host' => $this->host ?: null,
            'port' => $this->port,
            'database' => $this->database ?: null,
            'options' => $options,
            'ssh_enabled' => $this->sshEnabled,
            'ssh_host' => $this->sshHost ?: null,
            'ssh_port' => $this->sshPort ?: 22,
            'cache_ttl' => $this->cacheTtl,
        ];

        if ($this->editing && $this->editingUid) {
            $connection = RelovaConnection::query()
                ->where('tenant_id', $this->tenantId)
                ->where('uid', $this->editingUid)
                ->firstOrFail();

            // When editing, merge with existing credentials so that leaving a field
            // blank does not wipe a previously stored value (e.g. SSH private key).
            if ($this->username !== '' || $this->password !== '' || $this->sshPrivateKey !== '' || $this->sshPassphrase !== '') {
                $existing = $connection->credentials();
                $payload['credentials_encrypted'] = $encryptor->encrypt(
                    [
                        'username' => $this->username !== '' ? $this->username : ($existing['username'] ?? ''),
                        'password' => $this->password !== '' ? $this->password : ($existing['password'] ?? ''),
                        'ssh_private_key' => $this->sshPrivateKey !== '' ? $this->sshPrivateKey : ($existing['ssh_private_key'] ?? ''),
                        'ssh_passphrase' => $this->sshPassphrase !== '' ? $this->sshPassphrase : ($existing['ssh_passphrase'] ?? ''),
                    ],
                    $this->tenantId,
                );
            }

            $connection->update($payload);

            // Invalidate schema cache, purge stale VirtualEntityReference rows,
            // and trigger a fresh sync for all active mappings on this connection
            // so stale remote data does not persist after host/schema changes.
            $fresh = $connection->fresh();
            $inspector->invalidate($fresh);

            $mappings = ConnectorModuleMapping::query()
                ->where('connection_id', $connection->id)
                ->get();

            foreach ($mappings as $mapping) {
                VirtualEntityReference::where('mapping_id', $mapping->id)->delete();
                $syncEngine->invalidate($mapping);

                if ($mapping->active && $mapping->sync_behavior !== 'on_demand') {
                    dispatch(fn () => $syncEngine->forceSync($mapping))->afterResponse();
                }
            }
        } else {
            $payload['credentials_encrypted'] = $encryptor->encrypt(
                [
                    'username' => $this->username,
                    'password' => $this->password,
                    'ssh_private_key' => $this->sshPrivateKey,
                    'ssh_passphrase' => $this->sshPassphrase,
                ],
                $this->tenantId,
            );

            RelovaConnection::query()->create($payload);
        }

        $this->dispatch('relova-connection-saved');
        $this->closeForm();
    }

    public function test(string $uid, ConnectionRegistry $registry, DriverRegistry $drivers): void
    {
        $this->testResult = null;
        $this->testError = null;

        $connection = RelovaConnection::query()
            ->where('tenant_id', $this->tenantId)
            ->where('uid', $uid)
            ->firstOrFail();

        try {
            $registry->assertHostAllowed($connection);
            $driver = $drivers->resolve($connection->driver);

            $registry->withTunnel($connection, function (array $config) use ($driver) {
                $driver->testConnection($config);
            });

            $registry->markHealthy($connection);
            $this->testResult = $uid;
        } catch (ConnectionException $e) {
            $registry->markError($connection, $e->getMessage(), 'unreachable');
            $this->testError = $uid.': '.$e->getMessage();
        } catch (Throwable $e) {
            $registry->markError($connection, $e->getMessage());
            $this->testError = $uid.': '.$e->getMessage();
        }
    }

    public function delete(string $uid): void
    {
        RelovaConnection::query()
            ->where('tenant_id', $this->tenantId)
            ->where('uid', $uid)
            ->firstOrFail()
            ->delete();
    }

    private function resetForm(): void
    {
        $this->editing = false;
        $this->editingUid = null;
        $this->name = '';
        $this->description = '';
        $this->driver = 'pgsql';
        $this->host = '';
        $this->port = null;
        $this->database = '';
        $this->schema = '';
        $this->username = '';
        $this->password = '';
        $this->options = [];
        $this->optionsJson = '{}';
        $this->sshEnabled = false;
        $this->sshHost = '';
        $this->sshPort = 22;
        $this->sshUser = '';
        $this->sshPrivateKey = '';
        $this->sshPassphrase = '';
        $this->cacheTtl = null;
        $this->testResult = null;
        $this->testError = null;
        $this->resetErrorBag();
    }

    public function render()
    {
        $connections = RelovaConnection::query()
            ->where('tenant_id', $this->tenantId)
            ->when($this->search !== '', fn ($q) => $q->where(function ($q) {
                $q->where('name', 'ilike', '%'.$this->search.'%')
                    ->orWhere('host', 'ilike', '%'.$this->search.'%')
                    ->orWhere('database', 'ilike', '%'.$this->search.'%');
            }))
            ->orderBy('name')
            ->get();

        return view('relova::livewire.connection-manager', [
            'connections' => $connections,
        ]);
    }
}
