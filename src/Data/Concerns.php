<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Data;

use Carbon\CarbonImmutable;

/**
 * Small helpers to map loosely typed API payloads onto typed DTO properties.
 *
 * @internal
 */
final class Concerns
{
    public static function string(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    public static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public static function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    public static function bool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public static function date(mixed $value): ?CarbonImmutable
    {
        if (is_int($value)) {
            return CarbonImmutable::createFromTimestamp($value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
