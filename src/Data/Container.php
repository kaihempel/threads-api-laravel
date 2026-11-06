<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Data;

use KaiHempel\Threads\Enums\ContainerError;
use KaiHempel\Threads\Enums\ContainerStatus;

/**
 * A media container (result of POST /{user-id}/threads or GET /{container-id}?fields=status).
 */
final readonly class Container
{
    public function __construct(
        public string $id,
        public ?ContainerStatus $status = null,
        public ?ContainerError $error = null,
        public ?string $errorMessage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $errorMessage = Concerns::string($data['error_message'] ?? null);
        $status = Concerns::string($data['status'] ?? null);

        return new self(
            id: (string) ($data['id'] ?? ''),
            status: $status !== null ? (ContainerStatus::tryFrom(strtoupper($status)) ?? ContainerStatus::Error) : null,
            error: ContainerError::fromApi($errorMessage),
            errorMessage: $errorMessage,
        );
    }

    public function isReady(): bool
    {
        return $this->status === ContainerStatus::Finished;
    }
}
