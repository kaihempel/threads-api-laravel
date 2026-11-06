<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Publishing;

use KaiHempel\Threads\Data\Container;
use KaiHempel\Threads\Enums\ContainerStatus;
use KaiHempel\Threads\Enums\MediaType;
use KaiHempel\Threads\Enums\ReplyControl;
use KaiHempel\Threads\Resources\PublishingResource;
use KaiHempel\Threads\Support\ThreadsConfig;
use LogicException;

/**
 * Fluent API to create posts.
 *
 *  - publish():          synchronous pipeline (quota check, container(s), wait, publish)
 *  - createContainer():  first step only - publish later with $client->publishing()->publish($id)
 */
final class PostBuilder
{
    private ?string $text = null;

    private ?string $imageUrl = null;

    private ?string $videoUrl = null;

    private ?string $altText = null;

    /** @var list<CarouselItem> */
    private array $carousel = [];

    private bool $isCarousel = false;

    private ?ReplyControl $replyControl = null;

    private ?string $replyToId = null;

    private ?string $quotePostId = null;

    private ?string $linkAttachment = null;

    /** @var list<string> */
    private array $pollOptions = [];

    private ?string $topicTag = null;

    private ?string $locationId = null;

    /** @var list<string> */
    private array $countryCodes = [];

    /** @var list<array{offset: int, length: int}> */
    private array $spoilers = [];

    private ?TextAttachment $textAttachment = null;

    private bool $autoPublish = false;

    private bool $validate = true;

    public function __construct(
        private readonly PublishingResource $publishing,
        private readonly ContainerPoller $poller,
        private readonly PostValidator $validator,
        private readonly ThreadsConfig $config,
    ) {}

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function image(string $url, ?string $alt = null): self
    {
        $this->imageUrl = $url;
        $this->altText = $alt ?? $this->altText;

        return $this;
    }

    public function video(string $url, ?string $alt = null): self
    {
        $this->videoUrl = $url;
        $this->altText = $alt ?? $this->altText;

        return $this;
    }

    public function altText(string $altText): self
    {
        $this->altText = $altText;

        return $this;
    }

    /**
     * @param  list<CarouselItem>  $items
     */
    public function carousel(array $items): self
    {
        $this->isCarousel = true;
        $this->carousel = array_values($items);

        return $this;
    }

    public function addCarouselItem(CarouselItem $item): self
    {
        $this->isCarousel = true;
        $this->carousel[] = $item;

        return $this;
    }

    public function replyControl(ReplyControl $replyControl): self
    {
        $this->replyControl = $replyControl;

        return $this;
    }

    /**
     * Makes the post a reply (counts against the reply quota).
     */
    public function replyTo(string $mediaId): self
    {
        $this->replyToId = $mediaId;

        return $this;
    }

    public function quote(string $mediaId): self
    {
        $this->quotePostId = $mediaId;

        return $this;
    }

    /**
     * Link preview card (text posts only).
     */
    public function link(string $url): self
    {
        $this->linkAttachment = $url;

        return $this;
    }

    /**
     * Poll with 2-4 options (text posts only).
     */
    public function poll(string ...$options): self
    {
        $this->pollOptions = array_values($options);

        return $this;
    }

    public function topicTag(string $tag): self
    {
        $this->topicTag = ltrim(trim($tag), '#');

        return $this;
    }

    public function location(string $locationId): self
    {
        $this->locationId = $locationId;

        return $this;
    }

    /**
     * Restrict the post to the given countries (ISO 3166-1 alpha-2).
     */
    public function onlyInCountries(string ...$countryCodes): self
    {
        $this->countryCodes = array_values(array_map(static fn (string $code): string => strtoupper(trim($code)), $countryCodes));

        return $this;
    }

    /**
     * Mark a range of the text as spoiler (offset/length in characters, max. 10 per post).
     */
    public function spoiler(int $offset, int $length): self
    {
        $this->spoilers[] = ['offset' => $offset, 'length' => $length];

        return $this;
    }

    /**
     * Long text attachment (up to 10.000 characters, text posts only).
     *
     * @param  list<array<string, mixed>>  $stylingInfo
     */
    public function textAttachment(string|TextAttachment $plaintext, ?string $linkUrl = null, array $stylingInfo = []): self
    {
        $this->textAttachment = $plaintext instanceof TextAttachment ? $plaintext : new TextAttachment($plaintext, $linkUrl, $stylingInfo);

        return $this;
    }

    /**
     * Publish text posts directly on container creation (auto_publish_text=true).
     */
    public function autoPublish(bool $autoPublish = true): self
    {
        $this->autoPublish = $autoPublish;

        return $this;
    }

    /**
     * Skip the local validation (Meta limits may change faster than this package).
     */
    public function withoutValidation(): self
    {
        $this->validate = false;

        return $this;
    }

    public function toDraft(): PostDraft
    {
        $conflicts = [];
        $media = array_keys(array_filter([
            'carousel' => $this->isCarousel,
            'image' => $this->imageUrl !== null,
            'video' => $this->videoUrl !== null,
        ]));

        if (count($media) > 1) {
            $conflicts[] = implode(', ', $media);
        }

        $mediaType = match (true) {
            $this->isCarousel => MediaType::Carousel,
            $this->imageUrl !== null => MediaType::Image,
            $this->videoUrl !== null => MediaType::Video,
            default => MediaType::Text,
        };

        return new PostDraft(
            mediaType: $mediaType,
            text: $this->text,
            imageUrl: $this->imageUrl,
            videoUrl: $this->videoUrl,
            altText: $this->altText,
            carousel: $this->carousel,
            replyControl: $this->replyControl,
            replyToId: $this->replyToId,
            quotePostId: $this->quotePostId,
            linkAttachment: $this->linkAttachment,
            pollOptions: $this->pollOptions,
            topicTag: $this->topicTag,
            locationId: $this->locationId,
            allowlistedCountryCodes: $this->countryCodes,
            spoilers: $this->spoilers,
            textAttachment: $this->textAttachment,
            autoPublish: $this->autoPublish,
            conflicts: $conflicts,
        );
    }

    /**
     * @return list<Violation>
     */
    public function violations(): array
    {
        return $this->validator->violations($this->toDraft());
    }

    /**
     * Step 1 of the two step flow: creates the container (for carousels: children first, then the parent).
     * Publish it later with $client->publishing()->publish($container->id).
     *
     * Note: video carousel items may need processing time before the parent can be published;
     * publishing()->publish() reports this with a ContainerNotReadyException.
     */
    public function createContainer(): Container
    {
        $draft = $this->draft();

        if ($draft->autoPublish) {
            throw new LogicException('Auto published text posts have no separate publish step. Use publish() instead.');
        }

        return $this->createContainerFor($draft, waitForChildren: false);
    }

    /**
     * Synchronous pipeline: quota check, container(s), waiting for processing, idempotent publish.
     */
    public function publish(): PublishResult
    {
        $draft = $this->draft();

        if ($this->config->enforceQuota) {
            $this->publishing->assertQuota($draft->isReply());
        }

        if ($draft->autoPublish) {
            return $this->publishing->autoPublished($this->publishing->createContainer($draft->toParams()));
        }

        $container = $this->createContainerFor($draft, waitForChildren: $draft->hasVideo());
        $ready = $this->poller->waitUntilReady($container->id, initialWait: $draft->mediaType !== MediaType::Text);

        if ($ready->status === ContainerStatus::Published) {
            return $this->publishing->publish($container->id, enforceQuota: false, isReply: $draft->isReply());
        }

        return $this->publishing->publish($container->id, enforceQuota: false, isReply: $draft->isReply(), checkStatus: false);
    }

    private function draft(): PostDraft
    {
        $draft = $this->toDraft();

        if ($this->validate) {
            $this->validator->validate($draft);
        }

        return $draft;
    }

    private function createContainerFor(PostDraft $draft, bool $waitForChildren): Container
    {
        $params = $draft->toParams();

        if ($draft->mediaType === MediaType::Carousel) {
            $children = [];

            foreach ($draft->carousel as $item) {
                $children[] = $this->publishing->createCarouselItem($item)->id;
            }

            if ($waitForChildren) {
                foreach ($children as $index => $childId) {
                    $this->poller->waitUntilReady($childId, initialWait: $index === 0);
                }
            }

            $params['children'] = $children;
        }

        return $this->publishing->createContainer($params);
    }
}
