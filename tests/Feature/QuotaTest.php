<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use KaiHempel\Threads\Exceptions\QuotaExceededException;
use KaiHempel\Threads\Facades\Threads;
use KaiHempel\Threads\Testing\FakeThreads;
use KaiHempel\Threads\ThreadsManager;

it('reads the publishing limit', function () {
    FakeThreads::make()->fake();

    $limit = Threads::for('TOKEN')->quota()->publishing();

    expect($limit->quotaUsage)->toBe(4)->and($limit->remaining())->toBe(246);

    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/me/threads_publishing_limit'
        && $r['fields'] === 'quota_usage,config,reply_quota_usage,reply_config,delete_quota_usage,delete_config,location_search_quota_usage,location_search_config');
});

it('blocks publishing locally when the quota is exhausted', function () {
    CarbonImmutable::setTestNow('2026-11-06 12:00:00');
    FakeThreads::make()->on('GET', '*/threads_publishing_limit', FakeThreads::response('publishing_limit_exhausted'))->fake();

    try {
        Threads::for('TOKEN')->post()->text('Zu viel')->publish();
        $this->fail('Expected exception');
    } catch (QuotaExceededException $e) {
        expect($e->quota)->toBe('posts')
            ->and($e->limit->quotaUsage)->toBe(250)
            ->and($e->resetEstimate->toDateTimeString())->toBe('2026-11-07 12:00:00')
            ->and($e->isRetryable())->toBeTrue()
            ->and($e->getMessage())->toContain('250 posts');
    } finally {
        CarbonImmutable::setTestNow();
    }

    expect(recordedCalls())->toBe(['GET v1.0/me/threads_publishing_limit']);
});

it('checks the reply quota for replies', function () {
    FakeThreads::make()->on('GET', '*/threads_publishing_limit', FakeThreads::response('publishing_limit', [
        'data' => [['reply_quota_usage' => 1000]],
    ]))->fake();

    expect(fn () => Threads::for('TOKEN')->post()->text('Antwort')->replyTo('R1')->publish())
        ->toThrow(QuotaExceededException::class, '1000 replies');

    // normal posts are still possible
    expect(Threads::for('TOKEN')->post()->text('Post')->publish()->mediaId)->toBe('18050000000000001');
});

it('checks the quota in the two step publish as well', function () {
    FakeThreads::make()->on('GET', '*/threads_publishing_limit', FakeThreads::response('publishing_limit_exhausted'))->fake();

    expect(fn () => Threads::for('TOKEN')->publishing()->publish('C1'))->toThrow(QuotaExceededException::class);
    expect(fn () => Threads::for('TOKEN')->publishing()->publish('C1', isReply: true))->toThrow(QuotaExceededException::class, 'replies');
    Http::assertNotSent(fn (Request $r) => str_ends_with(requestPath($r), 'threads_publish'));

    expect(Threads::for('TOKEN')->publishing()->publish('C1', enforceQuota: false)->mediaId)->toBe('18050000000000001');
});

it('can disable the quota check', function () {
    config()->set('threads.publishing.enforce_quota', false);
    app()->forgetInstance(ThreadsManager::class);
    FakeThreads::make()->on('GET', '*/threads_publishing_limit', FakeThreads::response('publishing_limit_exhausted'))->fake();

    expect(Threads::for('TOKEN')->post()->text('Hallo')->publish()->mediaId)->toBe('18050000000000001');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'threads_publishing_limit'));
});
