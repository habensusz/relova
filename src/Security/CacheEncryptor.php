<?php

declare(strict_types=1);

namespace Relova\Security;

use RuntimeException;
use SodiumException;

/**
 * Per-tenant authenticated encryption for cached row data (Zone A).
 *
 * Uses libsodium AES-256-GCM with per-tenant keys derived via HKDF
 * (sodium_crypto_kdf_derive_from_key) from a master key held only in
 * environment configuration. The tenant_id is bound to ciphertext as
 * Additional Authenticated Data (AAD), so a payload encrypted for
 * tenant A cannot be decrypted with tenant B's derived key.
 *
 * Tenant-derived keys are cached in this object's private array for the
 * request lifecycle (typically <1ms per derivation, sub-microsecond on
 * subsequent calls). Keys are NEVER persisted to Redis or the database.
 */
final class CacheEncryptor
{
    private const KDF_CONTEXT = 'RelovaCx';

    private const KDF_SUBKEY_ID = 1;

    /** @var array<string, string> Tenant_id => derived key (32 bytes). */
    private array $tenantKeyCache = [];

    private readonly string $masterKey;

    public function __construct()
    {
        $key = (string) config('relova.master_key', '');

        if ($key === '') {
            throw new RuntimeException(
                'RELOVA_MASTER_KEY is not set. Generate one with: '
                .'php -r "echo base64_encode(random_bytes(32));"'
            );
        }

        $decoded = base64_decode($key, true);

        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_KDF_KEYBYTES) {
            throw new RuntimeException(
                'RELOVA_MASTER_KEY must be a base64-encoded '
                .SODIUM_CRYPTO_KDF_KEYBYTES.'-byte key.'
            );
        }

        $this->masterKey = $decoded;
    }

    /**
     * Encrypt arbitrary serializable data for a tenant.
     * Returns base64(nonce || ciphertext) suitable for storage in Redis.
     */
    public function encrypt(mixed $data, string $tenantId): string
    {
        $key = $this->deriveTenantKey($tenantId);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);
        $plaintext = json_encode($data, JSON_THROW_ON_ERROR);

        $ciphertext = sodium_crypto_aead_aes256gcm_encrypt(
            $plaintext,
            $tenantId,
            $nonce,
            $key
        );

        return base64_encode($nonce.$ciphertext);
    }

    /**
     * Decrypt a payload produced by encrypt() for the same tenant.
     * Returns null if the ciphertext is malformed or fails authentication
     * (wrong tenant, tampered, or wrong key).
     */
    public function decrypt(string $payload, string $tenantId): mixed
    {
        $raw = base64_decode($payload, true);

        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES + SODIUM_CRYPTO_AEAD_AES256GCM_ABYTES) {
            return null;
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);
        $ciphertext = substr($raw, SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);

        try {
            $plaintext = sodium_crypto_aead_aes256gcm_decrypt(
                $ciphertext,
                $tenantId,
                $nonce,
                $this->deriveTenantKey($tenantId)
            );
        } catch (SodiumException) {
            return null;
        }

        if ($plaintext === false) {
            return null;
        }

        try {
            return json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * Decrypt many payloads for the same tenant in one pass.
     * Derives the tenant key exactly once. Returns an array in the same
     * order as the input, with `null` for any entry that failed to decrypt.
     *
     * @param  array<int, string>  $payloads
     * @return array<int, mixed>
     */
    public function decryptMany(array $payloads, string $tenantId): array
    {
        // Warm the key cache once.
        $this->deriveTenantKey($tenantId);

        return array_map(
            fn (string $p): mixed => $this->decrypt($p, $tenantId),
            $payloads
        );
    }

    /**
     * Derive (and cache for the request) the per-tenant 32-byte key.
     */
    private function deriveTenantKey(string $tenantId): string
    {
        if (isset($this->tenantKeyCache[$tenantId])) {
            return $this->tenantKeyCache[$tenantId];
        }

        // sodium_crypto_kdf_derive_from_key requires an 8-byte context and
        // a deterministic subkey id. We mix the tenant_id into the subkey
        // id by hashing it down to a 64-bit integer so each tenant gets a
        // distinct subkey from the same master key + context.
        $tenantSubkeyId = self::KDF_SUBKEY_ID + (int) hexdec(substr(hash('sha256', $tenantId), 0, 12));

        $key = sodium_crypto_kdf_derive_from_key(
            SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES,
            $tenantSubkeyId,
            self::KDF_CONTEXT,
            $this->masterKey
        );

        $this->tenantKeyCache[$tenantId] = $key;

        return $key;
    }
}
