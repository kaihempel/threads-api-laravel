<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Data;

final readonly class Location
{
    public function __construct(
        public string $id,
        public ?string $name = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $country = null,
        public ?string $postalCode = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: Concerns::string($data['name'] ?? null),
            address: Concerns::string($data['address'] ?? null),
            city: Concerns::string($data['city'] ?? null),
            country: Concerns::string($data['country'] ?? null),
            postalCode: Concerns::string($data['postal_code'] ?? null),
            latitude: Concerns::float($data['latitude'] ?? null),
            longitude: Concerns::float($data['longitude'] ?? null),
        );
    }
}
