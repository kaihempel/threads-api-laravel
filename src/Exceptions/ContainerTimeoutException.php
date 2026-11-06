<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Exceptions;

use KaiHempel\Threads\Enums\ContainerStatus;

/**
 * The container did not become ready within poll_max_seconds. Check the status later.
 */
class ContainerTimeoutException extends ThreadsException
{
    public function __construct(
        string $message,
        public readonly string $containerId,
        public readonly ?ContainerStatus $lastStatus = null,
        public readonly int $waitedSeconds = 0,
    ) {
        parent::__construct($message);
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
