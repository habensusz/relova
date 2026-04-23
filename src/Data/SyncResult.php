<?php

declare(strict_types=1);

namespace Relova\Data;

/**
 * Immutable result returned by ShadowSyncService::syncMapping().
 */
readonly class SyncResult
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $skipped,
        public int $errors,
        public string $message = '',
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors > 0;
    }

    public function total(): int
    {
        return $this->created + $this->updated + $this->skipped + $this->errors;
    }
}
