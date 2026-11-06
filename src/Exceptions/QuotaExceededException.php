<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Exceptions;

use Carbon\CarbonImmutable;
use KaiHempel\Threads\Data\PublishingLimit;
use Throwable;

/**
 * The rolling 24h quota (posts, replies, deletes ...) is exhausted.
 *
 * The window is rolling, so $resetEstimate is only an upper bound estimate.
 */
class QuotaExceededException extends ThreadsException
{
    public function __construct(
        string $message = '',
        ?ApiError $error = null,
        public readonly ?PublishingLimit $limit = null,
        public readonly ?CarbonImmutable $resetEstimate = null,
        public readonly string $quota = 'posts',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $error, $previous);
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
