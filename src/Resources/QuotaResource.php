<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Resources;

use KaiHempel\Threads\Data\PublishingLimit;

final class QuotaResource extends Resource
{
    public const array FIELDS = [
        'quota_usage', 'config',
        'reply_quota_usage', 'reply_config',
        'delete_quota_usage', 'delete_config',
        'location_search_quota_usage', 'location_search_config',
    ];

    /**
     * GET /{user-id}/threads_publishing_limit
     */
    public function publishing(): PublishingLimit
    {
        return PublishingLimit::fromArray($this->request->get("{$this->userId}/threads_publishing_limit", [
            'fields' => implode(',', self::FIELDS),
        ]));
    }
}
