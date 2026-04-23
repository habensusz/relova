<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Relova\Concerns\EnforcesTenantIsolation;
use Relova\Security\CredentialEncryptor;

/**
 * A configured remote data source for a tenant.
 *
 * Stores:
 *   - Where the remote lives (driver, host, port, database).
 *   - Tenant-encrypted credentials and optional SSH tunnel info.
 *   - Driver-specific options.
 *   - Runtime health (status, last error, last health check).
 *
 * Never stores any remote row data.
 */
class RelovaConnection extends Model
{
    use EnforcesTenantIsolation;
    use HasUuids;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'connections';
    }

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'ssh_port' => 'integer',
            'ssh_enabled' => 'boolean',
            'cache_ttl' => 'integer',
            'options' => 'array',
            'last_checked_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'credentials_encrypted',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uid)) {
                $model->uid = Str::random(22);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    public function virtualEntityReferences(): HasMany
    {
        return $this->hasMany(VirtualEntityReference::class, 'connection_id');
    }

    public function moduleMappings(): HasMany
    {
        return $this->hasMany(ConnectorModuleMapping::class, 'connection_id');
    }

    /**
     * Decrypt and return the credential payload.
     *
     * @return array<string, mixed>
     */
    public function credentials(): array
    {
        if (empty($this->credentials_encrypted)) {
            return [];
        }

        return App::make(CredentialEncryptor::class)->decrypt(
            $this->credentials_encrypted,
            (string) $this->tenant_id,
        );
    }

    /**
     * Encrypt and store a credential payload.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function setCredentials(array $credentials): void
    {
        $this->credentials_encrypted = App::make(CredentialEncryptor::class)->encrypt(
            $credentials,
            (string) $this->tenant_id,
        );
    }

    /**
     * Pretty driver label via mutator-style accessor.
     */
    protected function driverLabel(): Attribute
    {
        return Attribute::get(fn () => match ($this->driver) {
            'mysql' => 'MySQL / MariaDB',
            'pgsql' => 'PostgreSQL',
            'sqlsrv' => 'SQL Server',
            'oracle' => 'Oracle',
            'sap_hana' => 'SAP HANA',
            'csv' => 'CSV File',
            'xlsx' => 'Excel (XLSX)',
            'rest_api' => 'REST API',
            default => ucfirst((string) $this->driver),
        });
    }

    /**
     * Build the SSH tunnel config array from the connection's SSH fields and encrypted credentials.
     *
     * @return array{host: string, port: int, user: string, private_key: ?string, passphrase: ?string, password: ?string, auth_method: string}
     */
    public function toSshConfig(): array
    {
        $credentials = $this->credentials();
        $options = (array) ($this->options ?? []);

        $privateKey = $credentials['ssh_private_key'] ?? null;
        $sshPassword = $credentials['ssh_password'] ?? ($credentials['password'] ?? null);

        return [
            'host' => (string) ($this->ssh_host ?? ''),
            'port' => (int) ($this->ssh_port ?? 22),
            'user' => (string) ($options['ssh_user'] ?? $credentials['ssh_user'] ?? ''),
            'private_key' => $privateKey ?: null,
            'passphrase' => $credentials['ssh_passphrase'] ?? null,
            'password' => $sshPassword,
            'auth_method' => ! empty($privateKey) ? 'key' : 'password',
        ];
    }
}
