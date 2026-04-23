<?php

declare(strict_types=1);

namespace Relova\Exceptions;

use RuntimeException;

/**
 * Thrown when a customer-managed encryption key (BYOK) cannot decrypt a
 * cached payload — typically because the key has been rotated, revoked,
 * or the supplied key does not match the original encryption key.
 */
class CustomerKeyDecryptionException extends RuntimeException {}
