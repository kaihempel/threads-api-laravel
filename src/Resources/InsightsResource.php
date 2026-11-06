<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Resources;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use KaiHempel\Threads\Data\Insights;
use KaiHempel\Threads\Exceptions\ValidationException;
use KaiHempel\Threads\Publishing\Violation;

/**
 * Insights (threads_manage_insights).
 */
final class InsightsResource extends Resource
{
    public const array MEDIA_METRICS = ['views', 'likes', 'replies', 'reposts', 'quotes', 'shares'];

    public const array ACCOUNT_METRICS = ['views', 'likes', 'replies', 'reposts', 'quotes', 'clicks', 'followers_count'];

    /** since/until may not be earlier than 2024-04-13 (UTC). */
    public const int EARLIEST_TIMESTAMP = 1712966400;

    /**
     * Lifetime metrics of a single post. Reposts of other users (REPOST_FACADE) return no data.
     *
     * @param  list<string>|null  $metrics
     */
    public function media(string $mediaId, ?array $metrics = null): Insights
    {
        return Insights::fromResponse($this->request->get("$mediaId/insights", [
            'metric' => implode(',', $metrics ?? self::MEDIA_METRICS),
        ]));
    }

    /**
     * Account level metrics. followers_count (and demographics) do not support since/until.
     *
     * @param  list<string>|null  $metrics
     */
    public function account(
        ?array $metrics = null,
        DateTimeInterface|int|null $since = null,
        DateTimeInterface|int|null $until = null,
        ?string $breakdown = null,
    ): Insights {
        $since = $this->timestamp('since', $since);
        $until = $this->timestamp('until', $until);

        return Insights::fromResponse($this->request->get("{$this->userId}/threads_insights", [
            'metric' => implode(',', $metrics ?? self::ACCOUNT_METRICS),
            'since' => $since,
            'until' => $until,
            'breakdown' => $breakdown,
        ]));
    }

    private function timestamp(string $field, DateTimeInterface|int|null $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $timestamp = $value instanceof DateTimeInterface ? $value->getTimestamp() : $value;

        if ($timestamp < self::EARLIEST_TIMESTAMP) {
            $earliest = CarbonImmutable::createFromTimestampUTC(self::EARLIEST_TIMESTAMP)->toDateString();

            throw ValidationException::withViolations([
                new Violation($field, 'insights_range', "Insights are not available before $earliest."),
            ]);
        }

        return $timestamp;
    }
}
