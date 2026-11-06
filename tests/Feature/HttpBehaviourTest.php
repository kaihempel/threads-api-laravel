<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use KaiHempel\Threads\Exceptions\ApiException;
use KaiHempel\Threads\Exceptions\PermissionException;
use KaiHempel\Threads\Exceptions\ThreadsException;
use KaiHempel\Threads\Exceptions\TokenExpiredException;
use KaiHempel\Threads\Facades\Threads;
use KaiHempel\Threads\Testing\FakeThreads;
use KaiHempel\Threads\ThreadsManager;

it('retries idempotent GET requests on server errors', function () {
    FakeThreads::make()->on('GET', 'v1.0/me', Http::sequence()
        ->push(FakeThreads::fixture('error_server'), 500)
        ->push(FakeThreads::fixture('error_server'), 502)
        ->push(FakeThreads::fixture('profile')))
        ->fake();

    expect(Threads::for('TOKEN')->profile()->me()->username)->toBe('threads_tester');
    Http::assertSentCount(3);
});

it('retries GET requests on connection errors and wraps the final error', function () {
    $attempts = 0;
    FakeThreads::make()->on('GET', 'v1.0/me', function (Request $r) use (&$attempts) {
        $attempts++;

        throw new ConnectionException('cURL error 28 for '.$r->url());
    })->fake();

    try {
        Threads::for('SECRET_TOKEN_123')->profile()->me();
        $this->fail('Expected exception');
    } catch (ApiException $e) {
        expect($e->isRetryable())->toBeTrue()
            ->and($e->isNetworkError())->toBeTrue()
            ->and($e->getMessage())->toContain('Connection to the Threads API failed')
            ->and($e->getMessage())->not->toContain('SECRET_TOKEN_123');
    }

    expect($attempts)->toBe(3);
});

it('does not retry GET requests on client errors', function () {
    FakeThreads::make()->on('GET', 'v1.0/me', FakeThreads::response('error_permission', status: 403))->fake();

    expect(fn () => Threads::for('TOKEN')->profile()->me())->toThrow(PermissionException::class);
    Http::assertSentCount(1);
});

it('never retries POST requests automatically', function () {
    config()->set('threads.publishing.enforce_quota', false);
    app()->forgetInstance(ThreadsManager::class);
    FakeThreads::make()->on('POST', 'v1.0/me/threads', FakeThreads::response('error_server', status: 500))->fake();

    expect(fn () => Threads::for('TOKEN')->post()->text('x')->createContainer())->toThrow(ApiException::class);
    Http::assertSentCount(1);
});

it('can disable retries', function () {
    config()->set('threads.http.retries', 0);
    app()->forgetInstance(ThreadsManager::class);
    FakeThreads::make()->on('GET', 'v1.0/me', FakeThreads::response('error_server', status: 500))->fake();

    expect(fn () => Threads::for('TOKEN')->profile()->me())->toThrow(ApiException::class);
    Http::assertSentCount(1);
});

it('throws on error payloads with a 200 status', function () {
    FakeThreads::make()->on('GET', 'v1.0/me', FakeThreads::fixture('error_token_expired'))->fake();

    expect(fn () => Threads::for('TOKEN')->profile()->me())->toThrow(TokenExpiredException::class);
});

it('never exposes tokens or secrets in exceptions', function () {
    FakeThreads::make()
        ->on('GET', 'v1.0/me', Http::response(['error' => ['message' => 'Invalid token SECRET_TOKEN_123', 'code' => 190]], 400))
        ->on('POST', 'oauth/access_token', Http::response(['error_message' => 'bad secret test-app-secret for code THE_CODE', 'code' => 100, 'error_type' => 'OAuthException'], 400))
        ->fake();

    foreach ([
        fn () => Threads::for('SECRET_TOKEN_123')->profile()->me(),
        fn () => Threads::auth()->exchangeCode('THE_CODE'),
    ] as $call) {
        try {
            $call();
            $this->fail('Expected exception');
        } catch (ThreadsException $e) {
            $dump = $e->getMessage().' '.$e->rawBody().' '.print_r($e->error, true);

            expect($dump)->not->toContain('SECRET_TOKEN_123')
                ->not->toContain('test-app-secret')
                ->not->toContain('THE_CODE')
                ->toContain('***');
        }
    }
});

it('uses the configured host, version and timeouts', function () {
    config()->set('threads.graph_url', 'https://graph.threads.com');
    config()->set('threads.version', 'v2.0');
    app()->forgetInstance(ThreadsManager::class);
    FakeThreads::make()->on('GET', 'v2.0/me', FakeThreads::response('profile'))->fake();

    Threads::for('TOKEN')->profile()->me();

    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://graph.threads.com/v2.0/me?')
        && $r->hasHeader('Accept', 'application/json'));
});
