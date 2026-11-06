<?php

declare(strict_types=1);

use KaiHempel\Threads\Facades\Threads;

/*
 * Optional live tests against the real Threads API (Threads tester account required).
 *
 *   THREADS_LIVE_TOKEN=... vendor/bin/pest --group=live
 */

beforeEach(function () {
    if (! getenv('THREADS_LIVE_TOKEN')) {
        $this->markTestSkipped('THREADS_LIVE_TOKEN is not set.');
    }
});

it('loads the profile of the live token', function () {
    $profile = Threads::for((string) getenv('THREADS_LIVE_TOKEN'))->profile()->me();

    expect($profile->id)->not->toBe('');
})->group('live');

it('loads the publishing limit of the live token', function () {
    $limit = Threads::for((string) getenv('THREADS_LIVE_TOKEN'))->quota()->publishing();

    expect($limit->quotaTotal)->toBeGreaterThan(0);
})->group('live');
