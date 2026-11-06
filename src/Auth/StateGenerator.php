<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Auth;

use Illuminate\Support\Str;

/**
 * Generates CSRF "state" values for the authorization redirect.
 * Storing and comparing the state (session) is the job of the application.
 */
final class StateGenerator
{
    public static function generate(int $length = 40): string
    {
        return Str::random(max(16, $length));
    }

    public static function matches(?string $expected, ?string $actual): bool
    {
        return is_string($expected) && is_string($actual) && $expected !== '' && hash_equals($expected, $actual);
    }
}
