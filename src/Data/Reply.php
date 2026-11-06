<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Data;

use Carbon\CarbonImmutable;
use KaiHempel\Threads\Enums\HideStatus;
use KaiHempel\Threads\Enums\PostMediaType;
use KaiHempel\Threads\Enums\ReplyApprovalStatus;

final readonly class Reply
{
    public function __construct(
        public string $id,
        public ?string $text = null,
        public ?string $username = null,
        public ?string $permalink = null,
        public ?CarbonImmutable $timestamp = null,
        public ?PostMediaType $mediaType = null,
        public ?string $mediaUrl = null,
        public ?string $shortcode = null,
        public ?bool $hasReplies = null,
        public ?bool $isReply = null,
        public ?bool $isReplyOwnedByMe = null,
        public ?string $rootPostId = null,
        public ?string $repliedToId = null,
        public ?HideStatus $hideStatus = null,
        public ?ReplyApprovalStatus $approvalStatus = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $approval = Concerns::string($data['reply_approval_status'] ?? null);

        return new self(
            id: (string) ($data['id'] ?? ''),
            text: Concerns::string($data['text'] ?? null),
            username: Concerns::string($data['username'] ?? null),
            permalink: Concerns::string($data['permalink'] ?? null),
            timestamp: Concerns::date($data['timestamp'] ?? null),
            mediaType: PostMediaType::fromApi(Concerns::string($data['media_type'] ?? null)),
            mediaUrl: Concerns::string($data['media_url'] ?? null),
            shortcode: Concerns::string($data['shortcode'] ?? null),
            hasReplies: Concerns::bool($data['has_replies'] ?? null),
            isReply: Concerns::bool($data['is_reply'] ?? null),
            isReplyOwnedByMe: Concerns::bool($data['is_reply_owned_by_me'] ?? null),
            rootPostId: Concerns::string(Concerns::array($data['root_post'] ?? null)['id'] ?? null),
            repliedToId: Concerns::string(Concerns::array($data['replied_to'] ?? null)['id'] ?? null),
            hideStatus: HideStatus::fromApi(Concerns::string($data['hide_status'] ?? null)),
            approvalStatus: $approval !== null ? ReplyApprovalStatus::tryFrom(strtolower($approval)) : null,
        );
    }

    public function isHidden(): bool
    {
        return $this->hideStatus === HideStatus::Hidden;
    }
}
