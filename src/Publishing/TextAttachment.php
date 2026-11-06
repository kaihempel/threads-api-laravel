<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Publishing;

/**
 * Long text attachment (up to 10.000 characters) for text posts.
 */
final readonly class TextAttachment
{
    /**
     * @param  list<array<string, mixed>>  $stylingInfo  Raw "text_with_styling_info" ranges.
     */
    public function __construct(
        public string $plaintext,
        public ?string $linkUrl = null,
        public array $stylingInfo = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'plaintext' => $this->plaintext,
            'link_attachment_url' => $this->linkUrl,
            'text_with_styling_info' => $this->stylingInfo === [] ? null : $this->stylingInfo,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
