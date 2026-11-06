<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Testing;

use KaiHempel\Threads\Publishing\Sleeper;

/**
 * Records sleeps instead of waiting. Bind it in tests: $this->app->instance(Sleeper::class, new FakeSleeper).
 */
final class FakeSleeper implements Sleeper
{
    /** @var list<int> */
    public array $sleeps = [];

    public function sleep(int $seconds): void
    {
        $this->sleeps[] = $seconds;
    }

    public function total(): int
    {
        return array_sum($this->sleeps);
    }
}
