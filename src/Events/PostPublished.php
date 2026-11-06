<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Events;

use KaiHempel\Threads\Publishing\PublishResult;

final readonly class PostPublished
{
    public function __construct(
        public PublishResult $result,
        public string $userId,
    ) {}
}
