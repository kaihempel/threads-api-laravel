<?php

declare(strict_types=1);

use KaiHempel\Threads\Exceptions\AccountRestrictedException;
use KaiHempel\Threads\Exceptions\ApiException;
use KaiHempel\Threads\Exceptions\AuthenticationException;
use KaiHempel\Threads\Exceptions\PermissionException;
use KaiHempel\Threads\Exceptions\QuotaExceededException;
use KaiHempel\Threads\Exceptions\RateLimitException;
use KaiHempel\Threads\Exceptions\TokenExpiredException;
use KaiHempel\Threads\Exceptions\ValidationException;
use KaiHempel\Threads\Http\ErrorParser;
use KaiHempel\Threads\Support\Redactor;
use KaiHempel\Threads\Testing\FakeThreads;

function parseFixture(string $fixture, int $status = 400, array $headers = []): Throwable
{
    return ErrorParser::parse($status, json_encode(FakeThreads::fixture($fixture)), $headers);
}

it('parses the graph error envelope', function () {
    $e = parseFixture('error_token_expired');

    expect($e)->toBeInstanceOf(TokenExpiredException::class)
        ->and($e)->toBeInstanceOf(AuthenticationException::class)
        ->and($e->getMessage())->toBe('Error validating access token: Session has expired.')
        ->and($e->httpStatus())->toBe(400)
        ->and($e->errorCode())->toBe(190)
        ->and($e->errorSubcode())->toBe(463)
        ->and($e->errorType())->toBe('OAuthException')
        ->and($e->fbtraceId())->toBe('AbCdEfGhIjKlMnOp')
        ->and($e->rawBody())->toContain('Session has expired')
        ->and($e->isRetryable())->toBeFalse();
});

it('parses the flat oauth error format of the token endpoints', function () {
    $e = parseFixture('error_oauth_flat');

    expect($e)->toBeInstanceOf(TokenExpiredException::class)
        ->and($e->getMessage())->toBe('Invalid OAuth access token - Cannot parse access token')
        ->and($e->errorType())->toBe('OAuthException')
        ->and($e->errorCode())->toBe(190);

    expect(parseFixture('error_invalid_code'))->toBeInstanceOf(AuthenticationException::class)
        ->not->toBeInstanceOf(TokenExpiredException::class);
});

it('parses oauth2 style errors', function () {
    $e = ErrorParser::parse(400, json_encode(['error' => 'invalid_request', 'error_description' => 'Missing code']));

    expect($e)->toBeInstanceOf(ApiException::class)
        ->and($e->getMessage())->toBe('Missing code')
        ->and($e->errorType())->toBe('invalid_request');
});

it('maps error codes to exceptions', function (string $fixture, int $status, string $class, bool $retryable) {
    $e = parseFixture($fixture, $status);

    expect($e)->toBeInstanceOf($class)
        ->and($e->isRetryable())->toBe($retryable);
})->with([
    'permission' => ['error_permission', 403, PermissionException::class, false],
    'rate limit' => ['error_rate_limit', 400, RateLimitException::class, true],
    'server error' => ['error_server', 500, ApiException::class, true],
    'link limit' => ['error_link_limit', 400, ValidationException::class, false],
    'account restricted' => ['error_account_restricted', 400, AccountRestrictedException::class, false],
]);

it('maps the link limit error to a validation exception with field and rule', function () {
    $e = parseFixture('error_link_limit');

    expect($e->field())->toBe('text')->and($e->rule())->toBe('link_limit');
});

it('maps http status codes without body', function () {
    expect(ErrorParser::parse(429, ''))->toBeInstanceOf(RateLimitException::class)
        ->and(ErrorParser::parse(401, ''))->toBeInstanceOf(AuthenticationException::class)
        ->and(ErrorParser::parse(403, ''))->toBeInstanceOf(PermissionException::class)
        ->and(ErrorParser::parse(503, '<html>Service unavailable</html>'))->toBeInstanceOf(ApiException::class)
        ->and(ErrorParser::parse(503, '<html>Service unavailable</html>')->getMessage())->toBe('Service unavailable')
        ->and(ErrorParser::parse(502, '')->getMessage())->toBe('Threads API request failed with HTTP status 502.')
        ->and(ErrorParser::parse(400, json_encode(['error' => ['message' => 'Invalid parameter', 'code' => 100]]))->isRetryable())->toBeFalse();
});

it('maps the content publishing limit sub code to a quota exception', function () {
    $e = ErrorParser::parse(400, json_encode(['error' => ['message' => 'limit', 'code' => 9, 'error_subcode' => 2207042]]));

    expect($e)->toBeInstanceOf(QuotaExceededException::class)->and($e->isRetryable())->toBeTrue();
});

it('reads the retry-after header', function () {
    expect(parseFixture('error_rate_limit', 429, ['Retry-After' => ['120']])->retryAfter)->toBe(120)
        ->and(parseFixture('error_rate_limit', 429, ['retry-after' => '30'])->retryAfter)->toBe(30)
        ->and(parseFixture('error_rate_limit', 429, ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 60)])->retryAfter)->toBeGreaterThan(50)
        ->and(parseFixture('error_rate_limit', 429)->retryAfter)->toBeNull();
});

it('redacts tokens from messages and raw bodies', function () {
    $body = json_encode(['error' => ['message' => 'Bad token secret-token-123 in access_token=secret-token-123', 'code' => 100]]);
    $e = ErrorParser::parse(400, $body, [], new Redactor(['secret-token-123']));

    expect($e->getMessage())->not->toContain('secret-token-123')
        ->and($e->rawBody())->not->toContain('secret-token-123');
});
