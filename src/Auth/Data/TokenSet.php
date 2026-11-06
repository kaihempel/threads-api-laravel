<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Auth\Data;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use KaiHempel\Threads\Data\Concerns;
use SensitiveParameter;

/**
 * An access token with its lifetime. Store it encrypted (e.g. Eloquent "encrypted" cast).
 */
final readonly class TokenSet
{
    /** A token must be at least 24 hours old before it can be refreshed. */
    public const int MIN_REFRESH_AGE_SECONDS = 86400;

    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        #[SensitiveParameter] public string $accessToken,
        public ?string $userId = null,
        public ?CarbonImmutable $expiresAt = null,
        public ?CarbonImmutable $issuedAt = null,
        public string $tokenType = 'bearer',
        public array $scopes = [],
        public bool $longLived = false,
    ) {}

    /**
     * @param  array<string, mixed>  $response  Raw token endpoint response.
     * @param  list<string>  $scopes
     */
    public static function fromResponse(array $response, bool $longLived, ?string $userId = null, array $scopes = [], int $defaultLifetime = 3600): self
    {
        $issuedAt = CarbonImmutable::now();
        $expiresIn = Concerns::int($response['expires_in'] ?? null) ?? $defaultLifetime;

        return new self(
            accessToken: (string) ($response['access_token'] ?? ''),
            userId: Concerns::string($response['user_id'] ?? null) ?? $userId,
            expiresAt: $issuedAt->addSeconds($expiresIn),
            issuedAt: $issuedAt,
            tokenType: Concerns::string($response['token_type'] ?? null) ?? 'bearer',
            scopes: $scopes,
            longLived: $longLived,
        );
    }

    /**
     * Restore a token set from persisted data (see toArray()).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $scopes = $data['scopes'] ?? [];

        return new self(
            accessToken: (string) ($data['access_token'] ?? ''),
            userId: Concerns::string($data['user_id'] ?? null),
            expiresAt: Concerns::date($data['expires_at'] ?? null),
            issuedAt: Concerns::date($data['issued_at'] ?? null),
            tokenType: Concerns::string($data['token_type'] ?? null) ?? 'bearer',
            scopes: array_values(array_map('strval', is_array($scopes) ? $scopes : explode(',', (string) $scopes))),
            longLived: (bool) ($data['long_lived'] ?? false),
        );
    }

    /**
     * @return array{access_token: string, user_id: ?string, expires_at: ?string, issued_at: ?string, token_type: string, scopes: list<string>, long_lived: bool}
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'user_id' => $this->userId,
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'issued_at' => $this->issuedAt?->toIso8601String(),
            'token_type' => $this->tokenType,
            'scopes' => $this->scopes,
            'long_lived' => $this->longLived,
        ];
    }

    public function isExpired(?DateTimeInterface $now = null): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt->lessThanOrEqualTo($now ?? CarbonImmutable::now());
    }

    /**
     * True if the token expires within $daysBefore days (or is already expired).
     */
    public function needsRefresh(int $daysBefore = 10, ?DateTimeInterface $now = null): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());

        return $this->expiresAt->lessThanOrEqualTo($now->addDays($daysBefore));
    }

    /**
     * Refresh is possible for long-lived tokens that are at least 24h old and not expired.
     */
    public function canRefresh(?DateTimeInterface $now = null): bool
    {
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now());

        if (! $this->longLived || $this->isExpired($now)) {
            return false;
        }

        if ($this->issuedAt === null) {
            return true; // unknown age - let the API decide
        }

        return $this->issuedAt->addSeconds(self::MIN_REFRESH_AGE_SECONDS)->lessThanOrEqualTo($now);
    }

    public function expiresInSeconds(?DateTimeInterface $now = null): ?int
    {
        if ($this->expiresAt === null) {
            return null;
        }

        return max(0, $this->expiresAt->getTimestamp() - ($now ?? CarbonImmutable::now())->getTimestamp());
    }

    public function withUserId(string $userId): self
    {
        return new self($this->accessToken, $userId, $this->expiresAt, $this->issuedAt, $this->tokenType, $this->scopes, $this->longLived);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['accessToken' => '***'] + array_diff_key($this->toArray(), ['access_token' => true]);
    }
}
