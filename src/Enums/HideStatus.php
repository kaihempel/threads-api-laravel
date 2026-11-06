<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Enums;

/**
 * hide_status of a reply.
 */
enum HideStatus: string
{
    case NotHushed = 'NOT_HUSHED';
    case Unhushed = 'UNHUSHED';
    case Hidden = 'HIDDEN';
    case Covered = 'COVERED';
    case Blocked = 'BLOCKED';
    case Restricted = 'RESTRICTED';
    case Unknown = 'UNKNOWN';

    public static function fromApi(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(strtoupper($value)) ?? self::Unknown;
    }
}
