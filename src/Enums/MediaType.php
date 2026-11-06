<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Enums;

/**
 * media_type values used when creating a container.
 */
enum MediaType: string
{
    case Text = 'TEXT';
    case Image = 'IMAGE';
    case Video = 'VIDEO';
    case Carousel = 'CAROUSEL';

    public function hasMedia(): bool
    {
        return $this === self::Image || $this === self::Video;
    }
}
