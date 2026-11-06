<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Exceptions;

use Throwable;

/**
 * Rate limiting / throttling by the API. Retry delayed (see $retryAfter if available).
 */
class RateLimitException extends ThreadsException
{
    public function __construct(
        string $message = '',
        ?ApiError $error = null,
        public readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $error, $previous);
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
