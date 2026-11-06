<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Resources;

use Illuminate\Support\Collection;
use KaiHempel\Threads\Data\Concerns;
use KaiHempel\Threads\Data\Location;

/**
 * Location search (requires threads_location_tagging, 500 searches / 24h).
 */
final class LocationResource extends Resource
{
    public const array DEFAULT_FIELDS = ['id', 'name', 'address', 'city', 'country', 'latitude', 'longitude', 'postal_code'];

    /**
     * @param  list<string>|string|null  $fields
     * @return Collection<int, Location>
     */
    public function search(?string $query = null, ?float $latitude = null, ?float $longitude = null, array|string|null $fields = null): Collection
    {
        $response = $this->request->get('location_search', [
            'q' => $query,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'fields' => $this->fields($fields, self::DEFAULT_FIELDS),
        ]);

        return Collection::make(array_values(Concerns::array($response['data'] ?? null)))
            ->map(static fn (mixed $item): Location => Location::fromArray(Concerns::array($item)))
            ->values();
    }

    /**
     * @param  list<string>|string|null  $fields
     */
    public function get(string $locationId, array|string|null $fields = null): Location
    {
        return Location::fromArray($this->request->get($locationId, [
            'fields' => $this->fields($fields, self::DEFAULT_FIELDS),
        ]));
    }
}
