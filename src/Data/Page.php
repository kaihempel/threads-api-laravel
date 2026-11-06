<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Data;

use ArrayIterator;
use Countable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * A cursor paginated result.
 *
 * @template T
 *
 * @implements IteratorAggregate<int, T>
 */
final readonly class Page implements Countable, IteratorAggregate
{
    /**
     * @param  Collection<int, T>  $items
     */
    public function __construct(
        public Collection $items,
        public ?string $before = null,
        public ?string $after = null,
        public ?string $nextUrl = null,
        public ?string $previousUrl = null,
    ) {}

    /**
     * @template TItem
     *
     * @param  array<string, mixed>  $response
     * @param  callable(array<string, mixed>): TItem  $map
     * @return self<TItem>
     */
    public static function fromResponse(array $response, callable $map): self
    {
        $paging = Concerns::array($response['paging'] ?? null);
        $cursors = Concerns::array($paging['cursors'] ?? null);

        /** @var Collection<int, TItem> $items */
        $items = Collection::make(array_values(Concerns::array($response['data'] ?? null)))
            ->map(static fn (mixed $item) => $map(Concerns::array($item)))
            ->values();

        return new self(
            items: $items,
            before: Concerns::string($cursors['before'] ?? null),
            after: Concerns::string($cursors['after'] ?? null),
            nextUrl: Concerns::string($paging['next'] ?? null),
            previousUrl: Concerns::string($paging['previous'] ?? null),
        );
    }

    /**
     * Whether another page exists. Graph APIs only send "next" when there are more results.
     */
    public function hasMore(): bool
    {
        return $this->nextUrl !== null;
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function count(): int
    {
        return $this->items->count();
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items->all());
    }
}
