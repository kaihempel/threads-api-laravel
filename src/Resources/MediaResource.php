<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Resources;

use DateTimeInterface;
use KaiHempel\Threads\Data\Page;
use KaiHempel\Threads\Data\Post;

/**
 * Reading own posts (GET /{user-id}/threads) and single media objects.
 */
final class MediaResource extends Resource
{
    public const array DEFAULT_FIELDS = [
        'id', 'media_product_type', 'media_type', 'media_url', 'permalink', 'username', 'text',
        'timestamp', 'shortcode', 'thumbnail_url', 'is_quote_post', 'alt_text', 'link_attachment_url', 'topic_tag',
    ];

    /**
     * @param  list<string>|string|null  $fields
     * @return Page<Post>
     */
    public function list(
        array|string|null $fields = null,
        ?int $limit = null,
        DateTimeInterface|int|null $since = null,
        DateTimeInterface|int|null $until = null,
        ?string $before = null,
        ?string $after = null,
    ): Page {
        $response = $this->request->get("{$this->userId}/threads", [
            'fields' => $this->fields($fields, self::DEFAULT_FIELDS),
            'limit' => $limit,
            'since' => $since,
            'until' => $until,
            'before' => $before,
            'after' => $after,
        ]);

        return Page::fromResponse($response, Post::fromArray(...));
    }

    /**
     * @param  list<string>|string|null  $fields
     */
    public function get(string $mediaId, array|string|null $fields = null): Post
    {
        return Post::fromArray($this->request->get($mediaId, [
            'fields' => $this->fields($fields, [...self::DEFAULT_FIELDS, 'children{id,media_type,media_url,alt_text}']),
        ]));
    }
}
