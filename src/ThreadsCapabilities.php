<?php

declare(strict_types=1);

namespace KaiHempel\Threads;

/**
 * Central place for the platform limits. The application can derive its validation UI from it
 * without duplicating knowledge about the API.
 */
final readonly class ThreadsCapabilities
{
    public const int MAX_TEXT_LENGTH = 500;

    public const int MAX_LINKS = 5;

    public const int MAX_TEXT_ATTACHMENT_LENGTH = 10000;

    public const int MIN_CAROUSEL_ITEMS = 2;

    public const int MAX_CAROUSEL_ITEMS = 20;

    public const int MAX_ALT_TEXT_LENGTH = 1000;

    public const int MIN_POLL_OPTIONS = 2;

    public const int MAX_POLL_OPTIONS = 4;

    public const int MIN_POLL_OPTION_LENGTH = 1;

    public const int MAX_POLL_OPTION_LENGTH = 25;

    public const int MAX_TOPIC_TAG_LENGTH = 50;

    public const int MAX_SPOILERS = 10;

    public const int MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    public const int MIN_IMAGE_WIDTH = 320;

    public const string MAX_IMAGE_ASPECT_RATIO = '10:1';

    public const int MAX_VIDEO_SECONDS = 300;

    public const int MAX_VIDEO_BYTES = 1024 * 1024 * 1024;

    public const int POSTS_PER_DAY = 250;

    public const int REPLIES_PER_DAY = 1000;

    public const int DELETES_PER_DAY = 100;

    public const int LOCATION_SEARCHES_PER_DAY = 500;

    public const int CONTAINER_LIFETIME_SECONDS = 86400;

    /**
     * @param  list<string>  $postTypes
     * @param  list<string>  $imageFormats
     */
    public function __construct(
        public int $maxTextLength = self::MAX_TEXT_LENGTH,
        public int $maxLinks = self::MAX_LINKS,
        public int $maxTextAttachmentLength = self::MAX_TEXT_ATTACHMENT_LENGTH,
        public int $minCarouselItems = self::MIN_CAROUSEL_ITEMS,
        public int $maxCarouselItems = self::MAX_CAROUSEL_ITEMS,
        public int $maxAltTextLength = self::MAX_ALT_TEXT_LENGTH,
        public int $minPollOptions = self::MIN_POLL_OPTIONS,
        public int $maxPollOptions = self::MAX_POLL_OPTIONS,
        public int $maxPollOptionLength = self::MAX_POLL_OPTION_LENGTH,
        public int $maxTopicTags = 1,
        public int $maxImageBytes = self::MAX_IMAGE_BYTES,
        public int $minImageWidth = self::MIN_IMAGE_WIDTH,
        public array $imageFormats = ['image/jpeg', 'image/png'],
        public int $maxVideoSeconds = self::MAX_VIDEO_SECONDS,
        public int $maxVideoBytes = self::MAX_VIDEO_BYTES,
        public int $postsPerDay = self::POSTS_PER_DAY,
        public int $repliesPerDay = self::REPLIES_PER_DAY,
        public int $deletesPerDay = self::DELETES_PER_DAY,
        public bool $supportsStories = false,
        public bool $supportsMediaUpload = false,
        public array $postTypes = ['text', 'image', 'video', 'carousel', 'link', 'poll', 'quote', 'reply'],
    ) {}

    public static function all(): self
    {
        return new self;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
