<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use KaiHempel\Threads\Enums\ContainerStatus;
use KaiHempel\Threads\Events\PostPublished;
use KaiHempel\Threads\Exceptions\ApiException;
use KaiHempel\Threads\Exceptions\ContainerFailedException;
use KaiHempel\Threads\Exceptions\ContainerNotReadyException;
use KaiHempel\Threads\Exceptions\RateLimitException;
use KaiHempel\Threads\Facades\Threads;
use KaiHempel\Threads\Testing\FakeThreads;
use KaiHempel\Threads\ThreadsManager;

function publishCalls(): int
{
    return Http::recorded(fn (Request $r) => str_ends_with(requestPath($r), 'threads_publish'))->count();
}

it('treats an already published container as success without publishing again', function () {
    Event::fake([PostPublished::class]);
    FakeThreads::make()->on('GET', 'v1.0/C1', FakeThreads::response('container_published'))->fake();

    $result = Threads::for('TOKEN')->publishing()->publish('C1');

    expect($result->recovered)->toBeTrue()
        ->and($result->mediaId)->toBeNull()
        ->and($result->containerId)->toBe('C1')
        ->and(publishCalls())->toBe(0);

    Event::assertDispatched(PostPublished::class, fn (PostPublished $e) => $e->result->recovered);
});

it('recovers when the publish call fails but the container is PUBLISHED (no double post)', function () {
    FakeThreads::make()
        ->on('POST', '*/threads_publish', FakeThreads::response('error_server', status: 500))
        ->on('GET', 'v1.0/C1', Http::sequence()
            ->push(FakeThreads::fixture('container_finished'))
            ->push(FakeThreads::fixture('container_published')))
        ->fake();

    $result = Threads::for('TOKEN')->publishing()->publish('C1');

    expect($result->recovered)->toBeTrue()->and(publishCalls())->toBe(1);
});

it('recovers after a network timeout of the publish call', function () {
    $attempts = 0;
    FakeThreads::make()
        ->on('POST', '*/threads_publish', function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('cURL error 28: Operation timed out');
        })
        ->on('GET', 'v1.0/C1', Http::sequence()
            ->push(FakeThreads::fixture('container_finished'))
            ->push(FakeThreads::fixture('container_published')))
        ->fake();

    expect(Threads::for('TOKEN')->publishing()->publish('C1')->recovered)->toBeTrue()
        ->and($attempts)->toBe(1);
});

it('retries the publish in a controlled way when the container is still FINISHED', function () {
    FakeThreads::make()
        ->on('POST', '*/threads_publish', Http::sequence()
            ->push(FakeThreads::fixture('error_server'), 500)
            ->push(FakeThreads::fixture('published')))
        ->fake();

    $result = Threads::for('TOKEN')->publishing()->publish('C1');

    expect($result->mediaId)->toBe('18050000000000001')
        ->and($result->recovered)->toBeFalse()
        ->and(publishCalls())->toBe(2);
});

it('gives up after the configured publish retries', function () {
    FakeThreads::make()->on('POST', '*/threads_publish', FakeThreads::response('error_server', status: 500))->fake();

    expect(fn () => Threads::for('TOKEN')->publishing()->publish('C1'))->toThrow(ApiException::class);
    expect(publishCalls())->toBe(2);
});

it('does not retry the publish when publish_retries is 0', function () {
    config()->set('threads.publishing.publish_retries', 0);
    app()->forgetInstance(ThreadsManager::class);
    FakeThreads::make()->on('POST', '*/threads_publish', FakeThreads::response('error_server', status: 500))->fake();

    expect(fn () => Threads::for('TOKEN')->publishing()->publish('C1'))->toThrow(ApiException::class);
    expect(publishCalls())->toBe(1);
});

it('rethrows the original error when the status check after a failed publish fails too', function () {
    FakeThreads::make()
        ->on('POST', '*/threads_publish', FakeThreads::response('error_server', status: 500))
        ->on('GET', 'v1.0/C1', Http::sequence()
            ->push(FakeThreads::fixture('container_finished'))
            ->push(FakeThreads::fixture('error_permission'), 403))
        ->fake();

    expect(fn () => Threads::for('TOKEN')->publishing()->publish('C1'))
        ->toThrow(ApiException::class, 'An unexpected error has occurred');
});

it('throws a terminal exception when the container failed after a failed publish', function () {
    FakeThreads::make()
        ->on('POST', '*/threads_publish', FakeThreads::response('error_server', status: 500))
        ->on('GET', 'v1.0/C1', Http::sequence()
            ->push(FakeThreads::fixture('container_finished'))
            ->push(FakeThreads::fixture('container_expired')))
        ->fake();

    expect(fn () => Threads::for('TOKEN')->publishing()->publish('C1'))->toThrow(ContainerFailedException::class, 'EXPIRED');
});

it('does not check the status again for non retryable publish errors', function () {
    FakeThreads::make()->on('POST', '*/threads_publish', FakeThreads::response('error_rate_limit', status: 400))->fake();

    expect(fn () => Threads::for('TOKEN')->publishing()->publish('C1'))->toThrow(RateLimitException::class);
    expect(Http::recorded(fn (Request $r) => requestPath($r) === 'v1.0/C1')->count())->toBe(1);
});

it('refuses to publish failed or unfinished containers', function (string $fixture, string $exception) {
    FakeThreads::make()->on('GET', 'v1.0/C1', FakeThreads::response($fixture))->fake();

    expect(fn () => Threads::for('TOKEN')->publishing()->publish('C1'))->toThrow($exception);
    expect(publishCalls())->toBe(0);
})->with([
    'error' => ['container_error', ContainerFailedException::class],
    'expired' => ['container_expired', ContainerFailedException::class],
    'in progress' => ['container_in_progress', ContainerNotReadyException::class],
]);

it('reports the container status', function () {
    FakeThreads::make()->on('GET', 'v1.0/C1', FakeThreads::response('container_error'))->fake();

    $container = Threads::for('TOKEN')->publishing()->container('C1');

    expect($container->status)->toBe(ContainerStatus::Error)
        ->and($container->errorMessage)->toBe('FAILED_DOWNLOADING_VIDEO');
});

it('keeps the container id when the status response has none', function () {
    FakeThreads::make()->on('GET', 'v1.0/C1', ['status' => 'FINISHED'])->fake();

    expect(Threads::for('TOKEN')->publishing()->container('C1')->id)->toBe('C1');
});

it('fails when the api does not return ids', function () {
    FakeThreads::make()
        ->on('POST', '*/threads_publish', [])
        ->on('POST', 'v1.0/me/threads', [])
        ->fake();

    expect(fn () => Threads::for('TOKEN')->publishing()->publish('C1', enforceQuota: false))->toThrow(ApiException::class, 'media id')
        ->and(fn () => Threads::for('TOKEN')->publishing()->createContainer(['media_type' => 'TEXT', 'text' => 'x']))->toThrow(ApiException::class, 'container id');
});
