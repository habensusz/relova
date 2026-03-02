<?php

declare(strict_types=1);

namespace Relova\Exceptions;

class ReadOnlyViolationException extends QueryException
{
    public function __construct(
        string $message = 'Write operations are not allowed through Relova connectors',
        ?string $sql = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $sql, $code, $previous);
    }
}
