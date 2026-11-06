<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Exceptions;

use Throwable;

/**
 * Generic API error (5xx, network problems, unknown error codes, invalid parameters).
 */
class ApiException extends ThreadsException
{
    public function __construct(
        string $message = '',
        ?ApiError $error = null,
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $error, $previous);
    }

    public static function network(string $message, ?Throwable $previous = null): self
    {
        return new self($message, new ApiError(message: $message, isTransient: true), true, $previous);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function isNetworkError(): bool
    {
        return $this->error->httpStatus === 0;
    }
}
