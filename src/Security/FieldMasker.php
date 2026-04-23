<?php

declare(strict_types=1);

namespace Relova\Security;

/**
 * Masks sensitive field values for snapshots and audit metadata.
 *
 * Used to ensure that:
 *   - Display snapshots persisted to virtual_entity_references never include
 *     credential-like fields even if the remote system exposes them.
 *   - Audit log query_metadata never echoes back a column value, even
 *     accidentally — column names are kept, values are replaced with the
 *     placeholder "***".
 *
 * Field detection is name-based using a configurable set of substring matchers
 * (case-insensitive). The default set covers the common credential family.
 */
final class FieldMasker
{
    private const DEFAULT_SENSITIVE_FRAGMENTS = [
        'password',
        'passwd',
        'pwd',
        'secret',
        'token',
        'api_key',
        'apikey',
        'private_key',
        'auth',
        'credential',
        'ssn',
        'social_security',
        'tax_id',
        'pin',
        'cvv',
        'card_number',
    ];

    private const PLACEHOLDER = '***';

    /** @var array<int, string> */
    private array $sensitiveFragments;

    /**
     * @param  array<int, string>|null  $additionalFragments
     */
    public function __construct(?array $additionalFragments = null)
    {
        $extra = $additionalFragments
            ?? (array) config('relova.sensitive_field_fragments', []);

        $this->sensitiveFragments = array_unique(array_merge(
            self::DEFAULT_SENSITIVE_FRAGMENTS,
            array_map('strtolower', $extra)
        ));
    }

    /**
     * Returns the row with sensitive field values replaced by "***".
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function maskRow(array $row): array
    {
        $masked = [];
        foreach ($row as $name => $value) {
            $masked[$name] = $this->isSensitive((string) $name) ? self::PLACEHOLDER : $value;
        }

        return $masked;
    }

    /**
     * Strip all values, returning a metadata-only structure suitable for audit
     * logs. Each column entry is an associative array describing the column
     * (name + type) without revealing data.
     *
     * @param  array<string, mixed>  $row
     * @return array<int, array{column: string, type: string}>
     */
    public function metadataOnly(array $row): array
    {
        $out = [];
        foreach ($row as $name => $value) {
            $out[] = [
                'column' => (string) $name,
                'type' => get_debug_type($value),
            ];
        }

        return $out;
    }

    public function isSensitive(string $fieldName): bool
    {
        $lower = strtolower($fieldName);

        foreach ($this->sensitiveFragments as $fragment) {
            if (str_contains($lower, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
