<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception of the package. Every exception carries the (redacted) API error details.
 */
class ThreadsException extends RuntimeException
{
    public readonly ApiError $error;

    public function __construct(string $message = '', ?ApiError $error = null, ?Throwable $previous = null)
    {
        $this->error = $error ?? new ApiError(message: $message);

        parent::__construct($message, $this->error->code ?? 0, $previous);
    }

    /**
     * Whether retrying the same operation later can succeed.
     */
    public function isRetryable(): bool
    {
        return false;
    }

    public function httpStatus(): int
    {
        return $this->error->httpStatus;
    }

    public function errorCode(): ?int
    {
        return $this->error->code;
    }

    public function errorSubcode(): ?int
    {
        return $this->error->subcode;
    }

    public function errorType(): ?string
    {
        return $this->error->type;
    }

    public function fbtraceId(): ?string
    {
        return $this->error->fbtraceId;
    }

    public function rawBody(): ?string
    {
        return $this->error->rawBody;
    }
}
