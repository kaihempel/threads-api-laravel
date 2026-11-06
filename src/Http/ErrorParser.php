<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Http;

use KaiHempel\Threads\Exceptions\AccountRestrictedException;
use KaiHempel\Threads\Exceptions\ApiError;
use KaiHempel\Threads\Exceptions\ApiException;
use KaiHempel\Threads\Exceptions\AuthenticationException;
use KaiHempel\Threads\Exceptions\PermissionException;
use KaiHempel\Threads\Exceptions\QuotaExceededException;
use KaiHempel\Threads\Exceptions\RateLimitException;
use KaiHempel\Threads\Exceptions\ThreadsException;
use KaiHempel\Threads\Exceptions\TokenExpiredException;
use KaiHempel\Threads\Exceptions\ValidationException;
use KaiHempel\Threads\Support\Redactor;

/**
 * Converts error responses into typed exceptions.
 *
 * Supports both formats used by Threads:
 *  - Graph envelope: {"error": {"message", "type", "code", "error_subcode", "fbtrace_id", ...}}
 *  - Flat OAuth format of the token endpoints: {"error_type", "code", "error_message"}
 */
final class ErrorParser
{
    public const int ACCOUNT_RESTRICTED_SUBCODE = 4279013;

    public const string LINK_LIMIT_EXCEEDED = 'THREADS_API__LINK_LIMIT_EXCEEDED';

    /** Graph API throttling codes. */
    private const array RATE_LIMIT_CODES = [4, 17, 32, 613, 80001, 80002];

    /** OAuth sub codes that mean "token invalid/expired". */
    private const array TOKEN_SUBCODES = [458, 459, 460, 463, 464, 467, 492];

    /** Content publishing limit (as used by the Instagram Graph API). */
    private const array QUOTA_SUBCODES = [2207042];

    /**
     * @param  array<string, list<string>|string>  $headers
     */
    public static function parse(int $httpStatus, string $body, array $headers = [], ?Redactor $redactor = null): ThreadsException
    {
        $redactor ??= new Redactor;
        $error = self::extract($httpStatus, $body, $redactor);

        return self::toException($error, self::retryAfter($headers));
    }

    public static function extract(int $httpStatus, string $body, ?Redactor $redactor = null): ApiError
    {
        $redactor ??= new Redactor;
        $decoded = json_decode($body, true);
        $data = is_array($decoded) ? $decoded : [];

        if (isset($data['error']) && is_array($data['error'])) {
            // Graph envelope
            $e = $data['error'];
            $message = self::string($e['message'] ?? null);
            $type = self::string($e['type'] ?? null);
            $code = self::int($e['code'] ?? null);
            $subcode = self::int($e['error_subcode'] ?? null);
            $trace = self::string($e['fbtrace_id'] ?? null);
            $userTitle = self::string($e['error_user_title'] ?? null);
            $userMessage = self::string($e['error_user_msg'] ?? null);
            $transient = (bool) ($e['is_transient'] ?? false);
        } else {
            // Flat OAuth format (or OAuth2 style {"error": "...", "error_description": "..."})
            $message = self::string($data['error_message'] ?? $data['error_description'] ?? $data['message'] ?? null);
            $type = self::string($data['error_type'] ?? $data['type'] ?? (is_string($data['error'] ?? null) ? $data['error'] : null));
            $code = self::int($data['code'] ?? $data['error_code'] ?? null);
            $subcode = self::int($data['error_subcode'] ?? null);
            $trace = self::string($data['fbtrace_id'] ?? null);
            $userTitle = null;
            $userMessage = null;
            $transient = false;
        }

        if ($message === null && $data === [] && $body !== '') {
            $message = mb_substr(trim(strip_tags($body)), 0, 200) ?: null;
        }

        return new ApiError(
            httpStatus: $httpStatus,
            message: $redactor->redact($message ?? sprintf('Threads API request failed with HTTP status %d.', $httpStatus)),
            type: $type,
            code: $code,
            subcode: $subcode,
            fbtraceId: $trace,
            userTitle: $redactor->redact($userTitle),
            userMessage: $redactor->redact($userMessage),
            isTransient: $transient,
            rawBody: $redactor->redact($body),
        );
    }

    public static function toException(ApiError $error, ?int $retryAfter = null): ThreadsException
    {
        $message = (string) $error->message;
        $haystack = implode(' ', array_filter([$error->message, $error->type, $error->userTitle, $error->userMessage, $error->rawBody]));

        if (str_contains($haystack, self::LINK_LIMIT_EXCEEDED)) {
            return ValidationException::fromApi('text', 'link_limit', $error);
        }

        if ($error->subcode === self::ACCOUNT_RESTRICTED_SUBCODE) {
            return new AccountRestrictedException($message, $error);
        }

        if ($error->code === 190 || in_array($error->subcode, self::TOKEN_SUBCODES, true)) {
            return new TokenExpiredException($message, $error);
        }

        if (in_array($error->subcode, self::QUOTA_SUBCODES, true)) {
            return new QuotaExceededException($message, $error);
        }

        if ($error->httpStatus === 429 || in_array($error->code, self::RATE_LIMIT_CODES, true)) {
            return new RateLimitException($message, $error, $retryAfter);
        }

        if ($error->code === 10 || ($error->code !== null && $error->code >= 200 && $error->code <= 299) || $error->httpStatus === 403) {
            return new PermissionException($message, $error);
        }

        if ($error->httpStatus >= 500 || $error->isTransient || in_array($error->code, [1, 2], true)) {
            return new ApiException($message, $error, retryable: true);
        }

        if ($error->type === 'OAuthException' || $error->httpStatus === 401) {
            return new AuthenticationException($message, $error);
        }

        return new ApiException($message, $error, retryable: false);
    }

    /**
     * @param  array<string, list<string>|string>  $headers
     */
    private static function retryAfter(array $headers): ?int
    {
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) !== 'retry-after') {
                continue;
            }

            $value = is_array($value) ? ($value[0] ?? null) : $value;

            if (is_numeric($value)) {
                return max(0, (int) $value);
            }

            if (is_string($value) && ($time = strtotime($value)) !== false) {
                return max(0, $time - time());
            }
        }

        return null;
    }

    private static function string(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
