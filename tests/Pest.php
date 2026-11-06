<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use KaiHempel\Threads\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit', 'Live');

/**
 * Path of a recorded request without host, e.g. "v1.0/me/threads".
 */
function requestPath(Request $request): string
{
    return ltrim((string) parse_url($request->url(), PHP_URL_PATH), '/');
}

/**
 * All recorded requests as "METHOD path" strings (in order).
 *
 * @return list<string>
 */
function recordedCalls(): array
{
    return Http::recorded()
        ->map(fn (array $pair): string => $pair[0]->method().' '.requestPath($pair[0]))
        ->values()
        ->all();
}
