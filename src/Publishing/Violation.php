<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Publishing;

/**
 * A single validation error: field + machine readable rule code + translated message.
 */
final readonly class Violation
{
    /**
     * @param  array<string, scalar>  $parameters
     */
    public function __construct(
        public string $field,
        public string $rule,
        public string $message,
        public array $parameters = [],
    ) {}
}
