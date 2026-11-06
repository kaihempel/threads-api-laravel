<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Exceptions;

/**
 * publish() was called for a container that is still IN_PROGRESS. Retry later.
 */
class ContainerNotReadyException extends ThreadsException
{
    public function __construct(string $message, public readonly string $containerId)
    {
        parent::__construct($message);
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
