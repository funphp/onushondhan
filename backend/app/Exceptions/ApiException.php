<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain error with a stable machine-readable code, rendered into the API envelope.
 */
class ApiException extends RuntimeException
{
    /** @param array<string, mixed> $errors */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 422,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function forbidden(string $message = 'You are not allowed to perform this action.', string $code = 'forbidden'): self
    {
        return new self($message, $code, 403);
    }

    public static function conflict(string $message, string $code = 'conflict'): self
    {
        return new self($message, $code, 409);
    }

    public static function invalidState(string $message, string $code = 'invalid_state'): self
    {
        return new self($message, $code, 422);
    }
}
