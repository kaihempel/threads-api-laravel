<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Enums;

/**
 * Processing status of a media container.
 */
enum ContainerStatus: string
{
    case InProgress = 'IN_PROGRESS';
    case Finished = 'FINISHED';
    case Published = 'PUBLISHED';
    case Error = 'ERROR';
    case Expired = 'EXPIRED';

    /**
     * ERROR and EXPIRED cannot recover; a new container is required.
     */
    public function isFailed(): bool
    {
        return $this === self::Error || $this === self::Expired;
    }

    public function isPublishable(): bool
    {
        return $this === self::Finished;
    }

    public function isTerminal(): bool
    {
        return $this !== self::InProgress && $this !== self::Finished;
    }
}
