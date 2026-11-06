<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Publishing;

final readonly class PublishResult
{
    /**
     * @param  string|null  $mediaId  The published media id. Null when the container was found to be
     *                                already PUBLISHED during recovery (the API does not return the media id then).
     * @param  bool  $recovered  True if the post had already been published (e.g. a lost response or a queue retry).
     */
    public function __construct(
        public string $containerId,
        public ?string $mediaId,
        public ?string $permalink = null,
        public bool $recovered = false,
    ) {}

    public function withPermalink(?string $permalink): self
    {
        return new self($this->containerId, $this->mediaId, $permalink, $this->recovered);
    }
}
