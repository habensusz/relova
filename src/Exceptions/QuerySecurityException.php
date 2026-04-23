<?php

declare(strict_types=1);

namespace Relova\Exceptions;

use RuntimeException;

/**
 * Thrown when an internally-generated SQL string fails QuerySanitizer validation.
 *
 * This is a defence-in-depth signal that something inside Relova is producing
 * SQL that is not strictly read-only — never raised on user input, since
 * Relova never accepts raw SQL from outside.
 */
class QuerySecurityException extends RuntimeException {}
