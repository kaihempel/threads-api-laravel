<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Data;

use Carbon\CarbonImmutable;
use KaiHempel\Threads\Enums\PostMediaType;

/**
 * A published Threads media object.
 */
final readonly class Post
{
    /**
     * @param  list<Post>  $children
     */
    public function __construct(
        public string $id,
        public ?PostMediaType $mediaType = null,
        public ?string $text = null,
        public ?string $permalink = null,
        public ?CarbonImmutable $timestamp = null,
        public ?string $username = null,
        public ?string $shortcode = null,
        public ?string $mediaUrl = null,
        public ?string $thumbnailUrl = null,
        public ?string $altText = null,
        public ?string $linkAttachmentUrl = null,
        public ?string $topicTag = null,
        public ?bool $isQuotePost = null,
        public ?string $quotedPostId = null,
        public ?string $repostedPostId = null,
        public ?string $mediaProductType = null,
        public array $children = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $children = array_map(
            static fn (mixed $child): self => self::fromArray(Concerns::array($child)),
            array_values(Concerns::array(Concerns::array($data['children'] ?? null)['data'] ?? null)),
        );

        return new self(
            id: (string) ($data['id'] ?? ''),
            mediaType: PostMediaType::fromApi(Concerns::string($data['media_type'] ?? null)),
            text: Concerns::string($data['text'] ?? null),
            permalink: Concerns::string($data['permalink'] ?? null),
            timestamp: Concerns::date($data['timestamp'] ?? null),
            username: Concerns::string($data['username'] ?? null),
            shortcode: Concerns::string($data['shortcode'] ?? null),
            mediaUrl: Concerns::string($data['media_url'] ?? null),
            thumbnailUrl: Concerns::string($data['thumbnail_url'] ?? null),
            altText: Concerns::string($data['alt_text'] ?? null),
            linkAttachmentUrl: Concerns::string($data['link_attachment_url'] ?? null),
            topicTag: Concerns::string($data['topic_tag'] ?? null),
            isQuotePost: Concerns::bool($data['is_quote_post'] ?? null),
            quotedPostId: Concerns::string(Concerns::array($data['quoted_post'] ?? null)['id'] ?? null),
            repostedPostId: Concerns::string(Concerns::array($data['reposted_post'] ?? null)['id'] ?? null),
            mediaProductType: Concerns::string($data['media_product_type'] ?? null),
            children: $children,
        );
    }
}
