<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Data;

/**
 * A single insights metric. Either a time series ($values) or a total value ($totalValue).
 */
final readonly class Insight
{
    /**
     * @param  list<InsightValue>  $values
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $name,
        public ?string $period = null,
        public array $values = [],
        public ?int $totalValue = null,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $id = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $values = array_map(
            static fn (mixed $value): InsightValue => InsightValue::fromArray(Concerns::array($value)),
            array_values(Concerns::array($data['values'] ?? null)),
        );

        return new self(
            name: (string) ($data['name'] ?? ''),
            period: Concerns::string($data['period'] ?? null),
            values: $values,
            totalValue: Concerns::int(Concerns::array($data['total_value'] ?? null)['value'] ?? null),
            title: Concerns::string($data['title'] ?? null),
            description: Concerns::string($data['description'] ?? null),
            id: Concerns::string($data['id'] ?? null),
            raw: $data,
        );
    }

    public function isTimeSeries(): bool
    {
        return $this->totalValue === null && count($this->values) > 0 && $this->values[0]->endTime !== null;
    }

    /**
     * Total value: total_value if present, otherwise the sum of all values.
     */
    public function total(): int
    {
        if ($this->totalValue !== null) {
            return $this->totalValue;
        }

        return array_sum(array_map(static fn (InsightValue $v): int => $v->value, $this->values));
    }
}
