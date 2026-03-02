<?php

declare(strict_types=1);

namespace Relova\Exceptions;

use RuntimeException;

class ConnectionException extends RuntimeException
{
    public function __construct(
        string $message = 'Failed to establish connection to remote source',
        public readonly ?string $driverName = null,
        public readonly ?string $host = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
