<?php

declare(strict_types=1);

namespace Relova\Exceptions;

use RuntimeException;

class SsrfException extends RuntimeException
{
    public function __construct(
        string $message = 'Connection target is blocked by SSRF protection policy',
        public readonly ?string $host = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
