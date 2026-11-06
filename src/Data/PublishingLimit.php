<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Data;

/**
 * Result of GET /{user-id}/threads_publishing_limit. All windows are rolling 24h windows.
 */
final readonly class PublishingLimit
{
    public function __construct(
        public int $quotaUsage = 0,
        public int $quotaTotal = 250,
        public int $quotaDuration = 86400,
        public ?int $replyQuotaUsage = null,
        public ?int $replyQuotaTotal = null,
        public ?int $replyQuotaDuration = null,
        public ?int $deleteQuotaUsage = null,
        public ?int $deleteQuotaTotal = null,
        public ?int $deleteQuotaDuration = null,
        public ?int $locationSearchQuotaUsage = null,
        public ?int $locationSearchQuotaTotal = null,
        public ?int $locationSearchQuotaDuration = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Either the full response or the first "data" entry.
     */
    public static function fromArray(array $data): self
    {
        if (isset($data['data']) && is_array($data['data'])) {
            $data = Concerns::array($data['data'][0] ?? null);
        }

        $config = Concerns::array($data['config'] ?? null);
        $reply = Concerns::array($data['reply_config'] ?? null);
        $delete = Concerns::array($data['delete_config'] ?? null);
        $location = Concerns::array($data['location_search_config'] ?? null);

        return new self(
            quotaUsage: Concerns::int($data['quota_usage'] ?? null) ?? 0,
            quotaTotal: Concerns::int($config['quota_total'] ?? null) ?? 250,
            quotaDuration: Concerns::int($config['quota_duration'] ?? null) ?? 86400,
            replyQuotaUsage: Concerns::int($data['reply_quota_usage'] ?? null),
            replyQuotaTotal: Concerns::int($reply['quota_total'] ?? null),
            replyQuotaDuration: Concerns::int($reply['quota_duration'] ?? null),
            deleteQuotaUsage: Concerns::int($data['delete_quota_usage'] ?? null),
            deleteQuotaTotal: Concerns::int($delete['quota_total'] ?? null),
            deleteQuotaDuration: Concerns::int($delete['quota_duration'] ?? null),
            locationSearchQuotaUsage: Concerns::int($data['location_search_quota_usage'] ?? null),
            locationSearchQuotaTotal: Concerns::int($location['quota_total'] ?? null),
            locationSearchQuotaDuration: Concerns::int($location['quota_duration'] ?? null),
        );
    }

    public function remaining(): int
    {
        return max(0, $this->quotaTotal - $this->quotaUsage);
    }

    public function isExhausted(): bool
    {
        return $this->quotaUsage >= $this->quotaTotal;
    }

    public function replyRemaining(): ?int
    {
        return $this->replyQuotaTotal === null ? null : max(0, $this->replyQuotaTotal - (int) $this->replyQuotaUsage);
    }

    public function isReplyQuotaExhausted(): bool
    {
        return $this->replyQuotaTotal !== null && (int) $this->replyQuotaUsage >= $this->replyQuotaTotal;
    }

    public function deleteRemaining(): ?int
    {
        return $this->deleteQuotaTotal === null ? null : max(0, $this->deleteQuotaTotal - (int) $this->deleteQuotaUsage);
    }

    public function isDeleteQuotaExhausted(): bool
    {
        return $this->deleteQuotaTotal !== null && (int) $this->deleteQuotaUsage >= $this->deleteQuotaTotal;
    }
}
