<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use KaiHempel\Threads\Enums\PostMediaType;
use KaiHempel\Threads\Enums\ReplyApprovalStatus;
use KaiHempel\Threads\Exceptions\ValidationException;
use KaiHempel\Threads\Facades\Threads;
use KaiHempel\Threads\Testing\FakeThreads;

beforeEach(fn () => FakeThreads::make()->fake());

it('loads the own profile', function () {
    $profile = Threads::for('TOKEN')->profile()->me();

    expect($profile->username)->toBe('threads_tester');
    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/me'
        && $r['fields'] === 'id,username,name,threads_profile_picture_url,threads_biography,is_verified'
        && $r['access_token'] === 'TOKEN');
});

it('loads a profile with custom fields', function () {
    Threads::for('TOKEN')->profile()->get('1234567890', ['id', 'username']);

    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/1234567890' && $r['fields'] === 'id,username');
});

it('lists own posts with pagination parameters', function () {
    $page = Threads::for('TOKEN', '1234567890')->media()->list(
        limit: 10,
        since: CarbonImmutable::parse('2026-11-01 00:00:00'),
        until: 1793750400,
        after: 'CURSOR',
    );

    expect($page)->toHaveCount(2)
        ->and($page->items[1]->mediaType)->toBe(PostMediaType::Image)
        ->and($page->after)->toBe('AFTER_CURSOR');

    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/1234567890/threads'
        && (string) $r['limit'] === '10'
        && (string) $r['since'] === (string) CarbonImmutable::parse('2026-11-01 00:00:00')->getTimestamp()
        && (string) $r['until'] === '1793750400'
        && $r['after'] === 'CURSOR'
        && ! isset($r['before'])
        && str_contains($r['fields'], 'permalink'));
});

it('loads a single post including children', function () {
    $post = Threads::for('TOKEN')->media()->get('18050000000000003');

    expect($post->children)->toHaveCount(2);
    Http::assertSent(fn (Request $r) => str_contains((string) $r['fields'], 'children{id,media_type,media_url,alt_text}'));
});

it('lists replies and the conversation', function () {
    $client = Threads::for('TOKEN');

    expect($client->replies()->list('M1', reverse: false)->items)->toHaveCount(2)
        ->and($client->replies()->conversation('M1', after: 'X')->items)->toHaveCount(2)
        ->and($client->replies()->mine(limit: 5)->items)->toHaveCount(2);

    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/M1/replies' && $r['reverse'] === 'false');
    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/M1/conversation' && $r['after'] === 'X');
    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/me/replies' && (string) $r['limit'] === '5');
});

it('hides and unhides replies', function () {
    $replies = Threads::for('TOKEN')->replies();

    expect($replies->hide('R1'))->toBeTrue()
        ->and($replies->unhide('R1'))->toBeTrue();

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && requestPath($r) === 'v1.0/R1/manage_reply' && $r['hide'] === 'true');
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && requestPath($r) === 'v1.0/R1/manage_reply' && $r['hide'] === 'false');
});

it('manages pending replies', function () {
    $replies = Threads::for('TOKEN')->replies();

    $pending = $replies->pending('M1', ReplyApprovalStatus::Ignored);

    expect($pending->items[0]->approvalStatus)->toBe(ReplyApprovalStatus::Pending)
        ->and($replies->approve('R1'))->toBeTrue()
        ->and($replies->ignore('R2'))->toBeTrue();

    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/M1/pending_replies'
        && $r['approval_status'] === 'ignored'
        && str_contains($r['fields'], 'reply_approval_status'));
    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/R1/manage_pending_reply' && $r['approve'] === 'true');
    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/R2/manage_pending_reply' && $r['approve'] === 'false');
});

it('loads media insights', function () {
    $insights = Threads::for('TOKEN')->insights()->media('18050000000000001');

    expect($insights->value('views'))->toBe(1200)
        ->and($insights->value('likes'))->toBe(42);

    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/18050000000000001/insights'
        && $r['metric'] === 'views,likes,replies,reposts,quotes,shares');
});

it('loads account insights with a time range', function () {
    $insights = Threads::for('TOKEN', '1234567890')->insights()->account(['views', 'clicks'], since: CarbonImmutable::parse('2026-10-01'), until: CarbonImmutable::parse('2026-11-01'));

    expect($insights->value('views'))->toBe(250);

    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/1234567890/threads_insights'
        && $r['metric'] === 'views,clicks'
        && (string) $r['since'] === (string) CarbonImmutable::parse('2026-10-01')->getTimestamp()
        && (string) $r['until'] === (string) CarbonImmutable::parse('2026-11-01')->getTimestamp());
});

it('loads account insights without time range and with breakdown', function () {
    Threads::for('TOKEN')->insights()->account(['follower_demographics'], breakdown: 'country');

    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/me/threads_insights'
        && $r['breakdown'] === 'country'
        && ! isset($r['since']));
});

it('rejects insight ranges before 2024-04-13', function () {
    Threads::for('TOKEN')->insights()->account(since: CarbonImmutable::parse('2024-04-12'));
})->throws(ValidationException::class, '2024-04-13');

it('searches locations', function () {
    $locations = Threads::for('TOKEN')->locations()->search('Berlin');

    expect($locations)->toHaveCount(1)
        ->and($locations->first()->name)->toBe('Berlin, Germany');

    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/location_search' && $r['q'] === 'Berlin' && ! isset($r['latitude']));
});

it('searches locations by coordinates and loads a location', function () {
    Threads::for('TOKEN')->locations()->search(latitude: 52.52, longitude: 13.405);
    Threads::for('TOKEN')->locations()->get('106078429431815', 'id,name');

    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/location_search' && (string) $r['latitude'] === '52.52' && (string) $r['longitude'] === '13.405');
    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/106078429431815' && $r['fields'] === 'id,name');
});

it('loads the permalink of a post', function () {
    expect(Threads::for('TOKEN')->publishing()->permalink('18050000000000001'))
        ->toBe('https://www.threads.com/@threads_tester/post/AbCdEfGhIjK');
});
