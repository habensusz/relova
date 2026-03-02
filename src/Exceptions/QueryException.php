<?php

declare(strict_types=1);

namespace Relova\Exceptions;

use RuntimeException;

class QueryException extends RuntimeException
{
    public function __construct(
        string $message = 'Query execution failed on remote source',
        public readonly ?string $sql = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
