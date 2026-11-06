<?php

declare(strict_types=1);

use KaiHempel\Threads\Enums\Scope;
use KaiHempel\Threads\Support\ThreadsConfig;
use KaiHempel\Threads\ThreadsCapabilities;

it('creates a typed config from the config array', function () {
    $config = ThreadsConfig::fromArray([
        'app_id' => 123,
        'app_secret' => '',
        'scopes' => 'threads_basic, threads_delete',
        'graph_url' => 'https://graph.threads.com/',
        'version' => '/v1.0/',
        'http' => ['timeout' => 5, 'retries' => -1],
        'publishing' => ['poll_interval_seconds' => 0, 'enforce_quota' => false],
        'events' => false,
    ]);

    expect($config->appId)->toBe('123')
        ->and($config->appSecret)->toBeNull()
        ->and($config->scopes)->toBe(['threads_basic', 'threads_delete'])
        ->and($config->graphUrl)->toBe('https://graph.threads.com')
        ->and($config->version)->toBe('v1.0')
        ->and($config->timeout)->toBe(5)
        ->and($config->retries)->toBe(0)
        ->and($config->pollIntervalSeconds)->toBe(1)
        ->and($config->enforceQuota)->toBeFalse()
        ->and($config->events)->toBeFalse();
});

it('accepts scope enums', function () {
    expect(ThreadsConfig::fromArray(['scopes' => Scope::publishing()])->scopes)->toBe(['threads_basic', 'threads_content_publish']);
});

it('overrides credentials at runtime', function () {
    $config = ThreadsConfig::fromArray(['app_id' => 'a', 'app_secret' => 'b', 'redirect_uri' => 'https://x.de/cb', 'version' => 'v2.0']);
    $other = $config->withCredentials('c', 'd');

    expect($other->appId)->toBe('c')
        ->and($other->appSecret)->toBe('d')
        ->and($other->redirectUri)->toBe('https://x.de/cb')
        ->and($other->version)->toBe('v2.0')
        ->and($config->withCredentials('c', 'd', 'https://y.de/cb')->redirectUri)->toBe('https://y.de/cb');
});

it('exposes the platform capabilities', function () {
    $capabilities = ThreadsCapabilities::all();

    expect($capabilities->maxTextLength)->toBe(500)
        ->and($capabilities->maxCarouselItems)->toBe(20)
        ->and($capabilities->maxPollOptions)->toBe(4)
        ->and($capabilities->supportsStories)->toBeFalse()
        ->and($capabilities->toArray())->toHaveKeys(['maxTextLength', 'maxVideoSeconds', 'postTypes']);
});
