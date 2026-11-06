<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Data;

final readonly class Profile
{
    public function __construct(
        public string $id,
        public ?string $username = null,
        public ?string $name = null,
        public ?string $profilePictureUrl = null,
        public ?string $biography = null,
        public ?bool $isVerified = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            username: Concerns::string($data['username'] ?? null),
            name: Concerns::string($data['name'] ?? null),
            profilePictureUrl: Concerns::string($data['threads_profile_picture_url'] ?? null),
            biography: Concerns::string($data['threads_biography'] ?? null),
            isVerified: Concerns::bool($data['is_verified'] ?? null),
        );
    }
}
