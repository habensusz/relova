<?php

declare(strict_types=1);

namespace Relova\Exceptions;

use RuntimeException;

class TimeoutException extends RuntimeException
{
    public function __construct(
        string $message = 'Remote operation exceeded the configured timeout',
        public readonly ?string $operation = null,
        public readonly ?int $timeoutSeconds = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
