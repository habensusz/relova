<?php

declare(strict_types=1);

namespace Relova\Security;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Per-tenant credential encryption.
 *
 * Each tenant gets a unique 32-byte encryption key derived from a master
 * key and the tenant identifier via HKDF-SHA256. If the master key is
 * rotated, every tenant's credentials must be re-encrypted. If a tenant
 * is deleted, their derived key is no longer recoverable.
 *
 * No raw credential is ever stored on disk or in logs.
 */
class CredentialEncryptor
{
    public function __construct(
        private string $masterKey,
        private string $cipher = 'AES-256-CBC',
    ) {
        if ($this->masterKey === '') {
            throw new RuntimeException(
                'Relova credential encryptor requires a non-empty master key. '
                .'Set RELOVA_ENCRYPTION_KEY or fall back to APP_KEY.'
            );
        }
    }

    /**
     * Encrypt a credential payload for a specific tenant.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function encrypt(array $credentials, string $tenantId): string
    {
        return $this->encrypterFor($tenantId)->encrypt(json_encode($credentials, JSON_THROW_ON_ERROR));
    }

    /**
     * Decrypt a previously encrypted credential payload.
     *
     * @return array<string, mixed>
     */
    public function decrypt(string $payload, string $tenantId): array
    {
        $decoded = json_decode(
            $this->encrypterFor($tenantId)->decrypt($payload),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (! is_array($decoded)) {
            throw new RuntimeException('Credential payload decoded into an unexpected shape.');
        }

        return $decoded;
    }

    private function encrypterFor(string $tenantId): Encrypter
    {
        $rawMaster = $this->rawMasterKey();
        $derived = hash_hkdf('sha256', $rawMaster, 32, 'relova-tenant-credentials', $tenantId);

        return new Encrypter($derived, $this->cipher);
    }

    private function rawMasterKey(): string
    {
        $key = $this->masterKey;
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }
}
