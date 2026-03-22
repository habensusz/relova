<?php

declare(strict_types=1);

namespace Relova\Exceptions;

use RuntimeException;

class CustomFieldValidationException extends RuntimeException
{
    /**
     * @param  array<string, string>  $errors  Field name => error message
     */
    public function __construct(
        string $message = 'Custom field validation failed',
        public readonly array $errors = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
