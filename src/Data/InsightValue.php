<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Data;

use Carbon\CarbonImmutable;

final readonly class InsightValue
{
    public function __construct(
        public int $value,
        public ?CarbonImmutable $endTime = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            value: Concerns::int($data['value'] ?? null) ?? 0,
            endTime: Concerns::date($data['end_time'] ?? null),
        );
    }
}
