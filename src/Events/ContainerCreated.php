<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Events;

use KaiHempel\Threads\Data\Container;
use KaiHempel\Threads\Enums\MediaType;

final readonly class ContainerCreated
{
    public function __construct(
        public Container $container,
        public string $userId,
        public ?MediaType $mediaType = null,
        public bool $isCarouselItem = false,
    ) {}
}
