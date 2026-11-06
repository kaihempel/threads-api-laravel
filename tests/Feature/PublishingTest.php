<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use KaiHempel\Threads\Auth\Data\TokenSet;
use KaiHempel\Threads\Enums\ContainerError;
use KaiHempel\Threads\Enums\ContainerStatus;
use KaiHempel\Threads\Enums\MediaType;
use KaiHempel\Threads\Enums\ReplyControl;
use KaiHempel\Threads\Events\ContainerCreated;
use KaiHempel\Threads\Events\PostPublished;
use KaiHempel\Threads\Exceptions\ContainerFailedException;
use KaiHempel\Threads\Exceptions\ContainerTimeoutException;
use KaiHempel\Threads\Exceptions\ValidationException;
use KaiHempel\Threads\Facades\Threads;
use KaiHempel\Threads\Publishing\CarouselItem;
use KaiHempel\Threads\Testing\FakeThreads;
use KaiHempel\Threads\ThreadsManager;

/** @return list<Request> */
function containerRequests(): array
{
    return Http::recorded()
        ->map(fn ($pair) => $pair[0])
        ->filter(fn (Request $r) => $r->method() === 'POST' && str_ends_with(requestPath($r), '/threads'))
        ->values()
        ->all();
}

it('publishes a text post without the initial wait', function () {
    Event::fake([ContainerCreated::class, PostPublished::class]);
    FakeThreads::make()->fake();

    $result = Threads::for('TOKEN')->post()->text('Hallo Threads')->publish();

    expect($result->mediaId)->toBe('18050000000000001')
        ->and($result->containerId)->toBe('17890000000000001')
        ->and($result->permalink)->toBe('https://www.threads.com/@threads_tester/post/AbCdEfGhIjK')
        ->and($result->recovered)->toBeFalse()
        ->and($this->sleeper->sleeps)->toBe([]);

    expect(recordedCalls())->toBe([
        'GET v1.0/me/threads_publishing_limit',
        'POST v1.0/me/threads',
        'GET v1.0/17890000000000001',
        'POST v1.0/me/threads_publish',
        'GET v1.0/18050000000000001',
    ]);

    $create = containerRequests()[0];
    expect($create->data())->toBe(['media_type' => 'TEXT', 'text' => 'Hallo Threads', 'access_token' => 'TOKEN'])
        ->and($create->isForm())->toBeTrue();

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'threads_publish') && $r['creation_id'] === '17890000000000001');

    Event::assertDispatched(ContainerCreated::class, fn (ContainerCreated $e) => $e->mediaType === MediaType::Text && $e->userId === 'me');
    Event::assertDispatched(PostPublished::class, fn (PostPublished $e) => $e->result === $result);
});

it('publishes an image post with all options', function () {
    FakeThreads::make()->fake();

    $result = Threads::for(new TokenSet('TOKEN', '1234567890'))->post()
        ->text('Hallo Threads')
        ->image('https://cdn.example.com/a.jpg', alt: 'Ein Bild')
        ->replyControl(ReplyControl::AccountsYouFollow)
        ->topicTag('#laravel')
        ->location('106078429431815')
        ->onlyInCountries('de', 'at')
        ->publish();

    expect($result->mediaId)->toBe('18050000000000001')
        ->and($this->sleeper->sleeps)->toBe([30]);

    expect(containerRequests()[0]->data())->toBe([
        'media_type' => 'IMAGE',
        'text' => 'Hallo Threads',
        'image_url' => 'https://cdn.example.com/a.jpg',
        'alt_text' => 'Ein Bild',
        'reply_control' => 'accounts_you_follow',
        'topic_tag' => 'laravel',
        'location_id' => '106078429431815',
        'allowlisted_country_codes' => 'DE,AT',
        'access_token' => 'TOKEN',
    ]);

    expect(requestPath(containerRequests()[0]))->toBe('v1.0/1234567890/threads');
});

it('waits for video processing by polling the container status', function () {
    FakeThreads::make()
        ->on('GET', 'v1.0/17890000000000001', Http::sequence()
            ->push(FakeThreads::fixture('container_in_progress'))
            ->push(FakeThreads::fixture('container_in_progress'))
            ->push(FakeThreads::fixture('container_finished')))
        ->fake();

    $result = Threads::for('TOKEN')->post()->video('https://cdn.example.com/v.mp4')->text('Video')->publish();

    expect($result->mediaId)->toBe('18050000000000001')
        ->and($this->sleeper->sleeps)->toBe([30, 60, 60]);

    expect(containerRequests()[0]->data())->toMatchArray(['media_type' => 'VIDEO', 'video_url' => 'https://cdn.example.com/v.mp4']);
});

it('throws a timeout exception with the container id when processing takes too long', function () {
    FakeThreads::make()->on('GET', 'v1.0/17890000000000001', FakeThreads::response('container_in_progress'))->fake();

    try {
        Threads::for('TOKEN')->post()->video('https://cdn.example.com/v.mp4')->publish();
        $this->fail('Expected timeout');
    } catch (ContainerTimeoutException $e) {
        expect($e->containerId)->toBe('17890000000000001')
            ->and($e->lastStatus)->toBe(ContainerStatus::InProgress)
            ->and($e->waitedSeconds)->toBe(300)
            ->and($e->isRetryable())->toBeTrue();
    }

    expect($this->sleeper->sleeps)->toBe([30, 60, 60, 60, 60, 60]);
    Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), 'threads_publish'));
});

it('respects the configured poll settings', function () {
    config()->set('threads.publishing', ['initial_wait_seconds' => 5, 'poll_interval_seconds' => 20, 'poll_max_seconds' => 50, 'enforce_quota' => false]);
    app()->forgetInstance(ThreadsManager::class);
    FakeThreads::make()->on('GET', 'v1.0/17890000000000001', FakeThreads::response('container_in_progress'))->fake();

    expect(fn () => Threads::for('TOKEN')->post()->image('https://cdn.example.com/a.jpg')->publish())->toThrow(ContainerTimeoutException::class);
    expect($this->sleeper->sleeps)->toBe([5, 20, 20, 10]);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'threads_publishing_limit'));
});

it('throws a container failed exception for containers in status ERROR', function () {
    FakeThreads::make()->on('GET', 'v1.0/17890000000000001', FakeThreads::response('container_error'))->fake();

    try {
        Threads::for('TOKEN')->post()->video('https://cdn.example.com/v.mp4')->publish();
        $this->fail('Expected exception');
    } catch (ContainerFailedException $e) {
        expect($e->containerId)->toBe('17890000000000001')
            ->and($e->status)->toBe(ContainerStatus::Error)
            ->and($e->containerError)->toBe(ContainerError::FailedDownloadingVideo)
            ->and($e->getMessage())->toContain('FAILED_DOWNLOADING_VIDEO')
            ->and($e->isRetryable())->toBeFalse();
    }
});

it('publishes a carousel: children first, then the parent, then publish', function () {
    $ids = ['C1', 'C2', 'PARENT'];
    FakeThreads::make()
        ->on('POST', 'v1.0/me/threads', function () use (&$ids) {
            return Http::response(['id' => array_shift($ids)]);
        })
        ->fake();

    $result = Threads::for('TOKEN')->post()->carousel([
        CarouselItem::image('https://cdn.example.com/1.jpg', 'Eins'),
        CarouselItem::image('https://cdn.example.com/2.jpg'),
    ])->text('Release-Notes')->publish();

    expect($result->containerId)->toBe('PARENT');

    $creates = containerRequests();
    expect($creates)->toHaveCount(3)
        ->and($creates[0]->data())->toBe(['media_type' => 'IMAGE', 'is_carousel_item' => 'true', 'image_url' => 'https://cdn.example.com/1.jpg', 'alt_text' => 'Eins', 'access_token' => 'TOKEN'])
        ->and($creates[1]->data())->toBe(['media_type' => 'IMAGE', 'is_carousel_item' => 'true', 'image_url' => 'https://cdn.example.com/2.jpg', 'access_token' => 'TOKEN'])
        ->and($creates[2]->data())->toBe(['media_type' => 'CAROUSEL', 'text' => 'Release-Notes', 'children' => 'C1,C2', 'access_token' => 'TOKEN']);

    // image carousels do not wait for the children
    expect(recordedCalls())->toBe([
        'GET v1.0/me/threads_publishing_limit',
        'POST v1.0/me/threads',
        'POST v1.0/me/threads',
        'POST v1.0/me/threads',
        'GET v1.0/PARENT',
        'POST v1.0/me/threads_publish',
        'GET v1.0/18050000000000001',
    ])->and($this->sleeper->sleeps)->toBe([30]);
});

it('waits for video carousel items before creating the parent', function () {
    $ids = ['C1', 'C2', 'PARENT'];
    FakeThreads::make()
        ->on('POST', 'v1.0/me/threads', function () use (&$ids) {
            return Http::response(['id' => array_shift($ids)]);
        })
        ->fake();

    Threads::for('TOKEN')->post()->carousel([
        CarouselItem::image('https://cdn.example.com/1.jpg'),
        CarouselItem::video('https://cdn.example.com/2.mp4'),
    ])->publish();

    expect(recordedCalls())->toBe([
        'GET v1.0/me/threads_publishing_limit',
        'POST v1.0/me/threads',
        'POST v1.0/me/threads',
        'GET v1.0/C1',
        'GET v1.0/C2',
        'POST v1.0/me/threads',
        'GET v1.0/PARENT',
        'POST v1.0/me/threads_publish',
        'GET v1.0/18050000000000001',
    ]);

    expect(containerRequests()[1]->data())->toMatchArray(['media_type' => 'VIDEO', 'video_url' => 'https://cdn.example.com/2.mp4']);
});

it('publishes polls, links, quotes, replies, spoilers and text attachments', function () {
    FakeThreads::make()->fake();

    Threads::for('TOKEN')->post()
        ->text('Was meint ihr? https://example.com')
        ->poll('Ja', 'Nein', 'Vielleicht')
        ->link('https://example.com')
        ->quote('Q1')
        ->replyTo('R1')
        ->spoiler(0, 3)
        ->textAttachment('Ein sehr langer Text', 'https://example.com/more')
        ->publish();

    expect(containerRequests()[0]->data())->toBe([
        'media_type' => 'TEXT',
        'text' => 'Was meint ihr? https://example.com',
        'reply_to_id' => 'R1',
        'quote_post_id' => 'Q1',
        'link_attachment' => 'https://example.com',
        'poll_attachment' => '{"option_a":"Ja","option_b":"Nein","option_c":"Vielleicht"}',
        'text_entities' => '[{"entity_type":"SPOILER","offset":0,"length":3}]',
        'text_attachment' => '{"plaintext":"Ein sehr langer Text","link_attachment_url":"https://example.com/more"}',
        'access_token' => 'TOKEN',
    ]);
});

it('auto publishes text posts in a single call', function () {
    Event::fake([PostPublished::class]);
    FakeThreads::make()->on('POST', 'v1.0/me/threads', ['id' => 'MEDIA_ID'])->fake();

    $result = Threads::for('TOKEN')->post()->text('Sofort')->autoPublish()->publish();

    expect($result->mediaId)->toBe('MEDIA_ID')
        ->and(containerRequests()[0]['auto_publish_text'])->toBe('true')
        ->and(recordedCalls())->toBe([
            'GET v1.0/me/threads_publishing_limit',
            'POST v1.0/me/threads',
            'GET v1.0/MEDIA_ID',
        ]);

    Event::assertDispatched(PostPublished::class);
});

it('does not allow createContainer() for auto published posts', function () {
    Http::fake();

    Threads::for('TOKEN')->post()->text('x')->autoPublish()->createContainer();
})->throws(LogicException::class);

it('supports the two step flow for queue jobs', function () {
    FakeThreads::make()->fake();
    $client = Threads::for('TOKEN');

    $container = $client->post()->video('https://cdn.example.com/v.mp4')->text('Später')->createContainer();

    expect($container->id)->toBe('17890000000000001')
        ->and(recordedCalls())->toBe(['POST v1.0/me/threads'])
        ->and($this->sleeper->sleeps)->toBe([]);

    expect($client->publishing()->status($container->id))->toBe(ContainerStatus::Finished);

    $result = $client->publishing()->publish($container->id);

    expect($result->mediaId)->toBe('18050000000000001');
    Http::assertSent(fn (Request $r) => requestPath($r) === 'v1.0/17890000000000001' && $r['fields'] === 'id,status,error_message');
});

it('creates carousel containers in the two step flow without waiting', function () {
    $ids = ['C1', 'C2', 'PARENT'];
    FakeThreads::make()
        ->on('POST', 'v1.0/me/threads', function () use (&$ids) {
            return Http::response(['id' => array_shift($ids)]);
        })
        ->fake();

    $container = Threads::for('TOKEN')->post()
        ->addCarouselItem(CarouselItem::video('https://cdn.example.com/1.mp4'))
        ->addCarouselItem(CarouselItem::image('https://cdn.example.com/2.jpg'))
        ->createContainer();

    expect($container->id)->toBe('PARENT')
        ->and(recordedCalls())->toBe(['POST v1.0/me/threads', 'POST v1.0/me/threads', 'POST v1.0/me/threads']);
});

it('validates locally before calling the api', function () {
    Http::fake();

    expect(fn () => Threads::for('TOKEN')->post()->text(str_repeat('a', 501))->publish())->toThrow(ValidationException::class)
        ->and(fn () => Threads::for('TOKEN')->post()->image('https://x.de/a.jpg')->video('https://x.de/b.mp4')->createContainer())
        ->toThrow(ValidationException::class, 'Only one media type');

    Http::assertNothingSent();
});

it('exposes violations without throwing', function () {
    expect(Threads::for('TOKEN')->post()->carousel([])->violations()[0]->rule)->toBe('carousel_item_count')
        ->and(Threads::for('TOKEN')->post()->altText('x')->image('https://x.de/a.jpg')->toDraft()->altText)->toBe('x');
});

it('can skip the local validation', function () {
    FakeThreads::make()->fake();

    $result = Threads::for('TOKEN')->post()->text(str_repeat('a', 600))->withoutValidation()->publish();

    expect($result->mediaId)->toBe('18050000000000001');
});

it('maps remote validation errors', function () {
    FakeThreads::make()->on('POST', 'v1.0/me/threads', FakeThreads::response('error_link_limit', status: 400))->fake();

    try {
        Threads::for('TOKEN')->post()->text('links')->withoutValidation()->publish();
        $this->fail('Expected exception');
    } catch (ValidationException $e) {
        expect($e->rule())->toBe('link_limit')->and($e->fbtraceId())->toBe('AbCdEfGhIjKlMnOp');
    }
});

it('does not fail when the permalink cannot be loaded', function () {
    FakeThreads::make()->on('GET', 'v1.0/18050000000000001', FakeThreads::response('error_permission', status: 403))->fake();

    $result = Threads::for('TOKEN')->post()->text('Hallo')->publish();

    expect($result->mediaId)->toBe('18050000000000001')->and($result->permalink)->toBeNull();
});

it('does not dispatch events when disabled', function () {
    config()->set('threads.events', false);
    app()->forgetInstance(ThreadsManager::class);
    Event::fake();
    FakeThreads::make()->fake();

    Threads::for('TOKEN')->post()->text('Hallo')->publish();

    Event::assertNotDispatched(PostPublished::class);
    Event::assertNotDispatched(ContainerCreated::class);
});

it('deletes posts', function () {
    FakeThreads::make()->fake();

    expect(Threads::for('TOKEN')->delete('18050000000000001'))->toBeTrue();

    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE'
        && requestPath($r) === 'v1.0/18050000000000001'
        && str_contains($r->url(), 'access_token=TOKEN'));
});
