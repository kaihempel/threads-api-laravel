<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use KaiHempel\Threads\Auth\Data\TokenSet;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-11-06 12:00:00'));
afterEach(fn () => CarbonImmutable::setTestNow());

it('computes expiresAt from expires_in', function () {
    $token = TokenSet::fromResponse(['access_token' => 'abc', 'expires_in' => 5183944, 'token_type' => 'bearer'], longLived: true, userId: '42');

    expect($token->accessToken)->toBe('abc')
        ->and($token->userId)->toBe('42')
        ->and($token->longLived)->toBeTrue()
        ->and($token->issuedAt->toDateTimeString())->toBe('2026-11-06 12:00:00')
        ->and($token->expiresAt->toDateTimeString())->toBe(CarbonImmutable::now()->addSeconds(5183944)->toDateTimeString())
        ->and($token->expiresInSeconds())->toBe(5183944);
});

it('uses the default lifetime and user id from the response', function () {
    $token = TokenSet::fromResponse(['access_token' => 'abc', 'user_id' => 1234], longLived: false);

    expect($token->userId)->toBe('1234')
        ->and($token->expiresAt->toDateTimeString())->toBe('2026-11-06 13:00:00');
});

it('knows when a refresh is needed', function () {
    $token = new TokenSet('abc', expiresAt: CarbonImmutable::now()->addDays(11), issuedAt: CarbonImmutable::now()->subDays(49), longLived: true);

    expect($token->needsRefresh())->toBeFalse()
        ->and($token->needsRefresh(12))->toBeTrue()
        ->and($token->needsRefresh(10, CarbonImmutable::now()->addDay()))->toBeTrue()
        ->and((new TokenSet('abc'))->needsRefresh())->toBeFalse();
});

it('can only refresh long-lived tokens that are at least 24 hours old and not expired', function () {
    $young = new TokenSet('abc', expiresAt: CarbonImmutable::now()->addDays(60), issuedAt: CarbonImmutable::now()->subHours(23), longLived: true);
    $old = new TokenSet('abc', expiresAt: CarbonImmutable::now()->addDays(59), issuedAt: CarbonImmutable::now()->subHours(24), longLived: true);
    $expired = new TokenSet('abc', expiresAt: CarbonImmutable::now()->subSecond(), issuedAt: CarbonImmutable::now()->subDays(61), longLived: true);
    $short = new TokenSet('abc', expiresAt: CarbonImmutable::now()->addHour(), issuedAt: CarbonImmutable::now()->subDays(2));
    $unknownAge = new TokenSet('abc', expiresAt: CarbonImmutable::now()->addDays(10), longLived: true);

    expect($young->canRefresh())->toBeFalse()
        ->and($old->canRefresh())->toBeTrue()
        ->and($expired->canRefresh())->toBeFalse()
        ->and($expired->isExpired())->toBeTrue()
        ->and($short->canRefresh())->toBeFalse()
        ->and($unknownAge->canRefresh())->toBeTrue()
        ->and($young->canRefresh(CarbonImmutable::now()->addHour()))->toBeTrue();
});

it('round-trips through toArray and fromArray', function () {
    $token = new TokenSet('abc', '42', CarbonImmutable::now()->addDays(60), CarbonImmutable::now(), 'bearer', ['threads_basic'], true);
    $restored = TokenSet::fromArray(json_decode(json_encode($token->toArray()), true));

    expect($restored)->toEqual($token)
        ->and($restored->withUserId('43')->userId)->toBe('43')
        ->and(TokenSet::fromArray(['access_token' => 'x', 'scopes' => 'a,b'])->scopes)->toBe(['a', 'b']);
});

it('hides the token in debug output', function () {
    $output = print_r(new TokenSet('super-secret-token', '42'), true);

    expect($output)->not->toContain('super-secret-token')->toContain('***');
});
