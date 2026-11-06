<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Exceptions;

/**
 * Normalised error details of a Threads API response (Graph envelope or flat OAuth format).
 * The raw body is already redacted (no tokens / secrets).
 */
final readonly class ApiError
{
    public function __construct(
        public int $httpStatus = 0,
        public ?string $message = null,
        public ?string $type = null,
        public ?int $code = null,
        public ?int $subcode = null,
        public ?string $fbtraceId = null,
        public ?string $userTitle = null,
        public ?string $userMessage = null,
        public bool $isTransient = false,
        public ?string $rawBody = null,
    ) {}
}
