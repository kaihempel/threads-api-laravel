<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Collection of insight metrics keyed by metric name.
 *
 * @implements IteratorAggregate<string, Insight>
 */
final readonly class Insights implements Countable, IteratorAggregate
{
    /**
     * @param  array<string, Insight>  $metrics
     */
    public function __construct(public array $metrics = []) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(array $response): self
    {
        $metrics = [];

        foreach (Concerns::array($response['data'] ?? null) as $item) {
            $insight = Insight::fromArray(Concerns::array($item));
            $metrics[$insight->name] = $insight;
        }

        return new self($metrics);
    }

    public function get(string $name): ?Insight
    {
        return $this->metrics[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->metrics[$name]);
    }

    /**
     * Total of a metric, null if the metric is not part of the response.
     */
    public function value(string $name): ?int
    {
        return $this->get($name)?->total();
    }

    public function isEmpty(): bool
    {
        return $this->metrics === [];
    }

    public function count(): int
    {
        return count($this->metrics);
    }

    /**
     * @return Traversable<string, Insight>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->metrics);
    }
}
