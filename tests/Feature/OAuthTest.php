<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use KaiHempel\Threads\Auth\Data\TokenSet;
use KaiHempel\Threads\Auth\StateGenerator;
use KaiHempel\Threads\Enums\Scope;
use KaiHempel\Threads\Events\TokenRefreshed;
use KaiHempel\Threads\Exceptions\AuthenticationException;
use KaiHempel\Threads\Exceptions\AuthorizationDeniedException;
use KaiHempel\Threads\Exceptions\ConfigurationException;
use KaiHempel\Threads\Exceptions\TokenExpiredException;
use KaiHempel\Threads\Exceptions\TokenNotRefreshableException;
use KaiHempel\Threads\Facades\Threads;
use KaiHempel\Threads\Support\ThreadsConfig;
use KaiHempel\Threads\Testing\FakeThreads;
use KaiHempel\Threads\ThreadsManager;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-11-06 12:00:00'));
afterEach(fn () => CarbonImmutable::setTestNow());

it('builds the authorization url', function () {
    $url = Threads::auth()->authorizationUrl(state: 'xyz');

    expect($url)->toStartWith('https://threads.com/oauth/authorize?');

    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toBe([
        'client_id' => 'test-app-id',
        'redirect_uri' => 'https://example.com/threads/callback',
        'scope' => 'threads_basic,threads_content_publish',
        'response_type' => 'code',
        'state' => 'xyz',
    ]);
});

it('accepts custom scopes and redirect uri', function () {
    $url = Threads::auth()->authorizationUrl(scopes: [Scope::Basic, 'threads_delete', Scope::Basic], redirectUri: 'https://other.example.com/cb');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($query['scope'])->toBe('threads_basic,threads_delete')
        ->and($query['redirect_uri'])->toBe('https://other.example.com/cb')
        ->and($query)->not->toHaveKey('state');
});

it('runs the complete oauth chain: code -> short-lived -> long-lived -> refresh', function () {
    Event::fake([TokenRefreshed::class]);
    FakeThreads::make()->fake();

    $short = Threads::auth()->exchangeCode('AUTH_CODE#_');

    expect($short->accessToken)->toBe('SHORT_LIVED_TOKEN')
        ->and($short->userId)->toBe('1234567890')
        ->and($short->longLived)->toBeFalse()
        ->and($short->expiresAt->toDateTimeString())->toBe('2026-11-06 13:00:00');

    $long = Threads::auth()->toLongLived($short);

    expect($long->accessToken)->toBe('LONG_LIVED_TOKEN')
        ->and($long->userId)->toBe('1234567890')
        ->and($long->longLived)->toBeTrue()
        ->and($long->expiresAt->toDateTimeString())->toBe(CarbonImmutable::now()->addSeconds(5183944)->toDateTimeString());

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addDays(50));

    $refreshed = Threads::auth()->refresh($long);

    expect($refreshed->accessToken)->toBe('REFRESHED_TOKEN')
        ->and($refreshed->userId)->toBe('1234567890')
        ->and($refreshed->issuedAt->toDateString())->toBe('2026-12-26');

    Event::assertDispatched(TokenRefreshed::class, fn (TokenRefreshed $e) => $e->token === $refreshed && $e->previous === $long);

    $requests = Http::recorded()->map(fn ($pair) => $pair[0])->values();

    // code exchange: POST form body to the unversioned endpoint
    expect($requests[0]->method())->toBe('POST')
        ->and($requests[0]->url())->toBe('https://graph.threads.net/oauth/access_token')
        ->and($requests[0]->data())->toBe([
            'client_id' => 'test-app-id',
            'client_secret' => 'test-app-secret',
            'grant_type' => 'authorization_code',
            'redirect_uri' => 'https://example.com/threads/callback',
            'code' => 'AUTH_CODE',
        ]);

    expect($requests[1]->method())->toBe('GET')
        ->and(requestPath($requests[1]))->toBe('access_token')
        ->and($requests[1]->data())->toBe([
            'grant_type' => 'th_exchange_token',
            'client_secret' => 'test-app-secret',
            'access_token' => 'SHORT_LIVED_TOKEN',
        ]);

    expect(requestPath($requests[2]))->toBe('refresh_access_token')
        ->and($requests[2]->data())->toBe([
            'grant_type' => 'th_refresh_token',
            'access_token' => 'LONG_LIVED_TOKEN',
        ]);
});

it('accepts plain token strings', function () {
    FakeThreads::make()->fake();

    expect(Threads::auth()->toLongLived('SHORT')->userId)->toBeNull()
        ->and(Threads::auth()->refresh('LONG')->accessToken)->toBe('REFRESHED_TOKEN');
});

it('handles the callback query', function () {
    FakeThreads::make()->fake();

    expect(Threads::auth()->handleCallback(['code' => 'AUTH_CODE', 'state' => 'abc'])->accessToken)->toBe('SHORT_LIVED_TOKEN');
});

it('throws when the user denied the authorization', function () {
    Threads::auth()->handleCallback([
        'error' => 'access_denied',
        'error_reason' => 'user_denied',
        'error_description' => 'Permissions error',
    ]);
})->throws(AuthorizationDeniedException::class, 'Permissions error');

it('throws on other callback errors or a missing code', function () {
    expect(fn () => Threads::auth()->handleCallback(['error' => 'server_error']))->toThrow(AuthenticationException::class)
        ->and(fn () => Threads::auth()->handleCallback([]))->toThrow(AuthenticationException::class, 'authorization code');
});

it('refuses to refresh tokens younger than 24 hours with an understandable exception', function () {
    Http::fake();

    $token = new TokenSet('LONG', '1', CarbonImmutable::now()->addDays(60), CarbonImmutable::now()->subHours(2), longLived: true);

    expect(fn () => Threads::auth()->refresh($token))->toThrow(TokenNotRefreshableException::class, 'at least 24 hours old');
    Http::assertNothingSent();
});

it('refuses to refresh expired or short-lived tokens', function () {
    Http::fake();

    $expired = new TokenSet('LONG', '1', CarbonImmutable::now()->subMinute(), CarbonImmutable::now()->subDays(61), longLived: true);
    $short = new TokenSet('SHORT', '1', CarbonImmutable::now()->addHour(), CarbonImmutable::now()->subDays(2));

    expect(fn () => Threads::auth()->refresh($expired))->toThrow(TokenExpiredException::class)
        ->and(fn () => Threads::auth()->refresh($short))->toThrow(TokenNotRefreshableException::class, 'Only long-lived tokens')
        ->and(fn () => Threads::auth()->toLongLived(new TokenSet('SHORT', '1', CarbonImmutable::now()->subMinute())))->toThrow(TokenExpiredException::class);

    Http::assertNothingSent();
});

it('maps api errors of the token endpoints (flat format)', function () {
    FakeThreads::make()
        ->on('GET', 'refresh_access_token', FakeThreads::response('error_oauth_flat', status: 400))
        ->on('POST', 'oauth/access_token', FakeThreads::response('error_invalid_code', status: 400))
        ->fake();

    expect(fn () => Threads::auth()->refresh('LONG_LIVED_TOKEN'))->toThrow(TokenExpiredException::class, 'Invalid OAuth access token')
        ->and(fn () => Threads::auth()->exchangeCode('bad'))->toThrow(AuthenticationException::class, 'Invalid verification code format.');
});

it('requires the app secret for server side token operations', function () {
    config()->set('threads.app_secret', null);
    app()->forgetInstance(ThreadsManager::class);
    Http::fake();

    expect(fn () => Threads::auth()->toLongLived('SHORT'))->toThrow(ConfigurationException::class)
        ->and(fn () => Threads::auth()->refresh('LONG'))->toThrow(ConfigurationException::class)
        ->and(fn () => Threads::auth()->exchangeCode('code'))->toThrow(ConfigurationException::class);

    Http::assertNothingSent();
});

it('requires app id and redirect uri for the authorization url', function () {
    $manager = Threads::using('id', 'secret');

    expect(fn () => (new ThreadsManager(app(), ThreadsConfig::fromArray([])))->auth()->authorizationUrl())
        ->toThrow(ConfigurationException::class, 'app id')
        ->and(fn () => (new ThreadsManager(app(), ThreadsConfig::fromArray(['app_id' => 'x'])))->auth()->authorizationUrl())
        ->toThrow(ConfigurationException::class, 'redirect uri')
        ->and($manager->auth()->authorizationUrl())->toContain('client_id=id');
});

it('uses runtime credentials with using()', function () {
    FakeThreads::make()->fake();

    Threads::using('project-app', 'project-secret', 'https://project.example.com/cb')->auth()->exchangeCode('CODE');

    Http::assertSent(fn (Request $r) => $r['client_id'] === 'project-app'
        && $r['client_secret'] === 'project-secret'
        && $r['redirect_uri'] === 'https://project.example.com/cb');
});

it('generates and compares state values', function () {
    $state = StateGenerator::generate();

    expect($state)->toHaveLength(40)
        ->and(StateGenerator::generate(8))->toHaveLength(16)
        ->and(StateGenerator::matches($state, $state))->toBeTrue()
        ->and(StateGenerator::matches($state, 'other'))->toBeFalse()
        ->and(StateGenerator::matches(null, $state))->toBeFalse()
        ->and(StateGenerator::matches('', ''))->toBeFalse();
});
