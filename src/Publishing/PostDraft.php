<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Publishing;

use KaiHempel\Threads\Enums\MediaType;
use KaiHempel\Threads\Enums\ReplyControl;

/**
 * Immutable snapshot of everything configured on a PostBuilder.
 */
final readonly class PostDraft
{
    /**
     * @param  list<CarouselItem>  $carousel
     * @param  list<string>  $pollOptions
     * @param  list<string>  $allowlistedCountryCodes
     * @param  list<array{offset: int, length: int}>  $spoilers
     * @param  list<string>  $conflicts  Mutually exclusive media that were both set.
     */
    public function __construct(
        public MediaType $mediaType = MediaType::Text,
        public ?string $text = null,
        public ?string $imageUrl = null,
        public ?string $videoUrl = null,
        public ?string $altText = null,
        public array $carousel = [],
        public ?ReplyControl $replyControl = null,
        public ?string $replyToId = null,
        public ?string $quotePostId = null,
        public ?string $linkAttachment = null,
        public array $pollOptions = [],
        public ?string $topicTag = null,
        public ?string $locationId = null,
        public array $allowlistedCountryCodes = [],
        public array $spoilers = [],
        public ?TextAttachment $textAttachment = null,
        public bool $autoPublish = false,
        public array $conflicts = [],
    ) {}

    public function isReply(): bool
    {
        return $this->replyToId !== null;
    }

    public function hasVideo(): bool
    {
        if ($this->mediaType === MediaType::Video) {
            return true;
        }

        foreach ($this->carousel as $item) {
            if ($item->mediaType === MediaType::Video) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parameters of the (parent) container. For carousels "children" is added by the pipeline.
     *
     * @return array<string, mixed>
     */
    public function toParams(): array
    {
        $params = [
            'media_type' => $this->mediaType,
            'text' => $this->text,
            'image_url' => $this->mediaType === MediaType::Image ? $this->imageUrl : null,
            'video_url' => $this->mediaType === MediaType::Video ? $this->videoUrl : null,
            'alt_text' => $this->mediaType->hasMedia() ? $this->altText : null,
            'reply_control' => $this->replyControl,
            'reply_to_id' => $this->replyToId,
            'quote_post_id' => $this->quotePostId,
            'link_attachment' => $this->linkAttachment,
            'topic_tag' => $this->topicTag,
            'location_id' => $this->locationId,
            'allowlisted_country_codes' => $this->allowlistedCountryCodes === [] ? null : $this->allowlistedCountryCodes,
            'auto_publish_text' => $this->autoPublish ? true : null,
        ];

        if ($this->pollOptions !== []) {
            $poll = [];
            foreach (array_values($this->pollOptions) as $index => $option) {
                $poll['option_'.chr(97 + $index)] = $option; // option_a ... option_d
            }
            $params['poll_attachment'] = $poll;
        }

        if ($this->spoilers !== []) {
            $params['text_entities'] = array_map(
                static fn (array $spoiler): array => ['entity_type' => 'SPOILER'] + $spoiler,
                $this->spoilers,
            );
        }

        if ($this->textAttachment !== null) {
            $params['text_attachment'] = $this->textAttachment->toArray();
        }

        return array_filter($params, static fn (mixed $value): bool => $value !== null);
    }
}
