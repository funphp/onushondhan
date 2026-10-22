<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain error with a stable machine-readable code, rendered into the API envelope.
 */
class ApiException extends RuntimeException
{
    /** @var array<string, mixed> */
    public readonly array $errors;

    /** @param array<string, mixed> $errors */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 422,
        array $errors = [],
        array $replace = [],
    ) {
        // Messages are translation keys (English source text); see lang/bn.json.
        parent::__construct(__($message, $replace));
        $this->errors = array_map(fn ($msgs) => array_map(fn ($m) => is_string($m) ? __($m) : $m, (array) $msgs), $errors);
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
