<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Enums;

/**
 * media_type values returned when reading posts.
 */
enum PostMediaType: string
{
    case TextPost = 'TEXT_POST';
    case Image = 'IMAGE';
    case Video = 'VIDEO';
    case CarouselAlbum = 'CAROUSEL_ALBUM';
    case Audio = 'AUDIO';
    case RepostFacade = 'REPOST_FACADE';
    case Unknown = 'UNKNOWN';

    public static function fromApi(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(strtoupper($value)) ?? self::Unknown;
    }
}
