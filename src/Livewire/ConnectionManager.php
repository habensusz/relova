<?php

declare(strict_types=1);

namespace Relova\Livewire;

use Livewire\Component;
use Relova\Models\RelovaConnection;
use Relova\Services\RelovaConnectionManager;

/**
 * Livewire component for managing Relova connections within itenance.
 * CRUD interface for creating, editing, testing, and monitoring connections.
 */
class ConnectionManager extends Component
{
    public array $connections = [];

    public bool $showForm = false;

    public bool $editing = false;

    public ?string $editingUid = null;

    // Form fields
    public string $name = '';

    public string $description = '';

    public string $driver_type = 'pgsql';

    public string $host = '';

    public ?int $port = 5432;

    public string $database_name = '';

    public string $schema_name = '';

    public string $username = '';

    public string $password = '';

    public int $cache_ttl = 300;

    public bool $enabled = false;

    // SSH Tunnel fields
    public bool $ssh_enabled = false;

    public string $ssh_host = '';

    public int $ssh_port = 22;

    public string $ssh_user = 'forge';

    public string $ssh_auth_method = 'key';

    public string $ssh_password = '';

    public string $ssh_private_key = '';

    public string $ssh_passphrase = '';

    // State
    public ?string $testResult = null;

    public ?string $testMessage = null;

    // File driver fields
    public string $delimiter = ',';

    // File browser
    public bool $showFileBrowser = false;

    public string $fileBrowserPath = '';

    public array $fileBrowserEntries = [];

    public ?string $fileBrowserError = null;

    protected function isFileDriver(): bool
    {
        return in_array($this->driver_type, ['csv', 'xlsx'], true);
    }

    protected function rules(): array
    {
        $fileDriver = $this->isFileDriver();

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'driver_type' => 'required|string',
            'host' => 'required|string|max:1024',
            'port' => $fileDriver ? 'nullable|integer' : 'nullable|integer|min:1|max:65535',
            'database_name' => $fileDriver ? 'nullable|string|max:255' : 'required|string|max:255',
            'schema_name' => 'nullable|string|max:255',
            'username' => $fileDriver ? 'nullable|string|max:255' : 'required|string|max:255',
            'password' => $fileDriver ? 'nullable|string' : ($this->editing ? 'nullable|string|max:255' : 'required|string|max:255'),
            'cache_ttl' => 'integer|min:0|max:86400',
            'enabled' => 'boolean',
            'ssh_enabled' => 'boolean',
            'ssh_host' => 'nullable|string|max:255',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'ssh_user' => 'nullable|string|max:255',
            'ssh_auth_method' => 'nullable|in:password,key',
            'ssh_password' => 'nullable|string',
            'ssh_private_key' => 'nullable|string',
            'ssh_passphrase' => 'nullable|string',
            'delimiter' => 'nullable|string|max:3',
        ];
    }

    public function mount(): void
    {
        $this->loadConnections();
    }

    public function loadConnections(): void
    {
        $this->connections = RelovaConnection::orderBy('name')
            ->get()
            ->toArray();
    }

    public function openCreateForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
        $this->editing = false;
    }

    public function openEditForm(string $uid): void
    {
        $connection = RelovaConnection::where('uid', $uid)->firstOrFail();

        $this->editingUid = $uid;
        $this->editing = true;
        $this->showForm = true;

        $this->name = $connection->name;
        $this->description = $connection->description ?? '';
        $this->driver_type = $connection->driver_type;
        $this->host = $connection->host ?? '';
        $this->port = $connection->port;
        $this->database_name = $connection->database_name ?? '';
        $this->schema_name = $connection->schema_name ?? '';
        $this->username = $connection->username ?? '';
        $this->password = '';
        $this->cache_ttl = $connection->cache_ttl ?? 300;
        $this->enabled = $connection->enabled;
        $this->delimiter = (string) ($connection->config_meta['delimiter'] ?? ',');

        // SSH tunnel
        $this->ssh_enabled = (bool) $connection->ssh_enabled;
        $this->ssh_host = $connection->ssh_host ?? '';
        $this->ssh_port = $connection->ssh_port ?? 22;
        $this->ssh_user = $connection->ssh_user ?? 'forge';
        $this->ssh_auth_method = $connection->ssh_auth_method ?? 'key';
        $this->ssh_password = '';
        $this->ssh_private_key = '';
        $this->ssh_passphrase = '';
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'driver_type' => $this->driver_type,
            'host' => $this->host,
            'port' => $this->isFileDriver() ? null : $this->port,
            'database_name' => $this->isFileDriver() ? null : ($this->database_name ?: null),
            'schema_name' => $this->isFileDriver() ? null : ($this->schema_name ?: null),
            'username' => $this->isFileDriver() ? null : ($this->username ?: null),
            'cache_ttl' => $this->cache_ttl,
            'query_mode' => 'snapshot',
            'enabled' => $this->enabled,
            'ssh_enabled' => $this->isFileDriver() ? false : $this->ssh_enabled,
            'ssh_host' => $this->ssh_host ?: null,
            'ssh_port' => $this->ssh_port ?? 22,
            'ssh_user' => $this->ssh_user ?: null,
            'ssh_auth_method' => $this->ssh_auth_method ?: 'key',
            'config_meta' => $this->driver_type === 'csv'
                ? ['delimiter' => $this->delimiter ?: ',']
                : null,
        ];

        if ($this->editing && $this->editingUid) {
            $connection = RelovaConnection::where('uid', $this->editingUid)->firstOrFail();
            $connection->update($data);

            if ($this->password) {
                $connection->password = $this->password;
                $connection->save();
            }

            if ($this->ssh_private_key) {
                $connection->sshPrivateKey = $this->ssh_private_key;
                $connection->save();
            }

            if ($this->ssh_password) {
                $connection->sshPassword = $this->ssh_password;
                $connection->save();
            }

            if ($this->ssh_passphrase) {
                $connection->sshPassphrase = $this->ssh_passphrase;
                $connection->save();
            }
        } else {
            $connection = RelovaConnection::create($data);
            $connection->password = $this->password;

            if ($this->ssh_private_key) {
                $connection->sshPrivateKey = $this->ssh_private_key;
            }

            if ($this->ssh_password) {
                $connection->sshPassword = $this->ssh_password;
            }

            if ($this->ssh_passphrase) {
                $connection->sshPassphrase = $this->ssh_passphrase;
            }

            $connection->save();
        }

        $this->showForm = false;
        $this->loadConnections();
        $this->dispatch('notify', message: __('relova.connection_saved'));
    }

    public function testConnectionFromForm(): void
    {
        $connection = new RelovaConnection([
            'driver_type' => $this->driver_type,
            'host' => $this->host,
            'port' => $this->port,
            'database_name' => $this->database_name,
            'schema_name' => $this->schema_name,
            'username' => $this->username,
            'ssh_enabled' => $this->ssh_enabled,
            'ssh_host' => $this->ssh_host ?: null,
            'ssh_port' => $this->ssh_port ?? 22,
            'ssh_user' => $this->ssh_user ?: null,
            'ssh_auth_method' => $this->ssh_auth_method ?: 'key',
        ]);

        $connection->password = $this->password;

        // When editing, fall back to stored password / SSH credentials if not re-entered
        if ($this->editing && $this->editingUid) {
            $existing = RelovaConnection::where('uid', $this->editingUid)->first();

            if ($existing) {
                if (! $this->password) {
                    $connection->password = $existing->password;
                }

                if (! $this->ssh_private_key) {
                    $connection->sshPrivateKey = $existing->sshPrivateKey;
                } else {
                    $connection->sshPrivateKey = $this->ssh_private_key;
                }

                if (! $this->ssh_password) {
                    $connection->sshPassword = $existing->sshPassword;
                } else {
                    $connection->sshPassword = $this->ssh_password;
                }

                $connection->sshPassphrase = $this->ssh_passphrase ?: $existing->sshPassphrase;
            }
        } else {
            if ($this->ssh_private_key) {
                $connection->sshPrivateKey = $this->ssh_private_key;
            }

            if ($this->ssh_password) {
                $connection->sshPassword = $this->ssh_password;
            }

            if ($this->ssh_passphrase) {
                $connection->sshPassphrase = $this->ssh_passphrase;
            }
        }

        try {
            $manager = app(RelovaConnectionManager::class);
            $manager->testUnsaved($connection);

            $this->testResult = 'success';
            $this->testMessage = __('relova.test_success');
        } catch (\Exception $e) {
            $this->testResult = 'error';
            $this->testMessage = $e->getMessage();
        }
    }

    public function testExistingConnection(string $uid): void
    {
        $connection = RelovaConnection::where('uid', $uid)->firstOrFail();
        $manager = app(RelovaConnectionManager::class);

        $result = $manager->test($connection);

        $this->loadConnections();

        $this->dispatch('notify', message: $result
            ? __('relova.test_success')
            : __('relova.test_failed')
        );
    }

    public function deleteConnection(string $uid): void
    {
        RelovaConnection::where('uid', $uid)->delete();
        $this->loadConnections();
        $this->dispatch('notify', message: __('relova.connection_deleted'));
    }

    public function toggleConnection(string $uid): void
    {
        $connection = RelovaConnection::where('uid', $uid)->firstOrFail();
        $connection->update(['enabled' => ! $connection->enabled]);
        $this->loadConnections();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->editingUid = null;
        $this->editing = false;
        $this->name = '';
        $this->description = '';
        $this->driver_type = 'pgsql';
        $this->host = '';
        $this->port = 5432;
        $this->database_name = '';
        $this->schema_name = '';
        $this->username = '';
        $this->password = '';
        $this->cache_ttl = 300;
        $this->enabled = false;
        $this->ssh_enabled = false;
        $this->ssh_host = '';
        $this->ssh_port = 22;
        $this->ssh_user = 'forge';
        $this->ssh_auth_method = 'key';
        $this->ssh_password = '';
        $this->ssh_private_key = '';
        $this->ssh_passphrase = '';
        $this->testResult = null;
        $this->testMessage = null;
        $this->showFileBrowser = false;
        $this->fileBrowserPath = '';
        $this->fileBrowserEntries = [];
        $this->fileBrowserError = null;
        $this->delimiter = ',';
    }

    public function openFileBrowser(): void
    {
        $start = $this->host && is_dir(dirname($this->host))
            ? dirname($this->host)
            : (is_dir($this->host) ? $this->host : DIRECTORY_SEPARATOR);

        $this->fileBrowserNavigate($start);
        $this->showFileBrowser = true;
    }

    public function fileBrowserNavigate(string $path): void
    {
        $this->fileBrowserError = null;
        $real = realpath($path);

        if ($real === false || ! is_dir($real)) {
            $this->fileBrowserError = __('relova.browser_invalid_dir');

            return;
        }

        $this->fileBrowserPath = $real;
        $entries = [];

        $parent = dirname($real);
        if ($parent !== $real) {
            $entries[] = ['name' => '..', 'path' => $parent, 'type' => 'dir'];
        }

        $items = @scandir($real);
        if ($items === false) {
            $this->fileBrowserError = __('relova.browser_permission_denied');
            $this->fileBrowserEntries = $entries;

            return;
        }

        $extensions = $this->driver_type === 'csv' ? ['csv'] : ['xlsx', 'xls'];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $real . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $entries[] = ['name' => $item, 'path' => $full, 'type' => 'dir'];
            } elseif (is_file($full)) {
                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                if (in_array($ext, $extensions, true)) {
                    $entries[] = ['name' => $item, 'path' => $full, 'type' => 'file'];
                }
            }
        }

        usort($entries, fn ($a, $b) => $a['type'] === $b['type']
            ? strnatcasecmp($a['name'], $b['name'])
            : ($a['type'] === 'dir' ? -1 : 1)
        );

        $this->fileBrowserEntries = $entries;
    }

    public function fileBrowserSelect(string $path): void
    {
        $this->host = $path;
        $this->showFileBrowser = false;
    }

    public function render(): \Illuminate\View\View
    {
        return view('relova::livewire.connection-manager');
    }
}
