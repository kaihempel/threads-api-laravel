<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Publishing;

/**
 * Abstraction of waiting, so the publish pipeline can be tested without real delays.
 */
interface Sleeper
{
    public function sleep(int $seconds): void;
}
