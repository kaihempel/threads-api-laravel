<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Publishing\Sleepers;

use Illuminate\Support\Sleep;
use KaiHempel\Threads\Publishing\Sleeper;

final class RealSleeper implements Sleeper
{
    public function sleep(int $seconds): void
    {
        if ($seconds > 0) {
            Sleep::for($seconds)->seconds();
        }
    }
}
