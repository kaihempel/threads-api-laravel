<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Events;

use KaiHempel\Threads\Auth\Data\TokenSet;

final readonly class TokenRefreshed
{
    public function __construct(
        public TokenSet $token,
        public ?TokenSet $previous = null,
    ) {}
}
