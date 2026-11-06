<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Support;

/**
 * Typed, immutable view on the package configuration.
 */
final readonly class ThreadsConfig
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public ?string $appId = null,
        public ?string $appSecret = null,
        public ?string $redirectUri = null,
        public array $scopes = ['threads_basic', 'threads_content_publish'],
        public string $graphUrl = 'https://graph.threads.net',
        public string $authorizeUrl = 'https://threads.com/oauth/authorize',
        public string $version = 'v1.0',
        public int $timeout = 30,
        public int $connectTimeout = 10,
        public int $retries = 2,
        public int $retryDelayMs = 500,
        public int $initialWaitSeconds = 30,
        public int $pollIntervalSeconds = 60,
        public int $pollMaxSeconds = 300,
        public bool $enforceQuota = true,
        public int $publishRetries = 1,
        public bool $fetchPermalink = true,
        public bool $events = true,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $http = is_array($config['http'] ?? null) ? $config['http'] : [];
        $publishing = is_array($config['publishing'] ?? null) ? $config['publishing'] : [];

        $scopes = $config['scopes'] ?? ['threads_basic', 'threads_content_publish'];
        $scopes = is_string($scopes) ? explode(',', $scopes) : (array) $scopes;

        return new self(
            appId: self::nullableString($config['app_id'] ?? null),
            appSecret: self::nullableString($config['app_secret'] ?? null),
            redirectUri: self::nullableString($config['redirect_uri'] ?? null),
            scopes: array_values(array_filter(array_map(
                static fn (mixed $scope): string => trim((string) ($scope instanceof \BackedEnum ? $scope->value : $scope)),
                $scopes,
            ))),
            graphUrl: rtrim((string) ($config['graph_url'] ?? 'https://graph.threads.net'), '/'),
            authorizeUrl: (string) ($config['authorize_url'] ?? 'https://threads.com/oauth/authorize'),
            version: trim((string) ($config['version'] ?? 'v1.0'), '/'),
            timeout: (int) ($http['timeout'] ?? 30),
            connectTimeout: (int) ($http['connect_timeout'] ?? 10),
            retries: max(0, (int) ($http['retries'] ?? 2)),
            retryDelayMs: max(0, (int) ($http['retry_delay_ms'] ?? 500)),
            initialWaitSeconds: max(0, (int) ($publishing['initial_wait_seconds'] ?? 30)),
            pollIntervalSeconds: max(1, (int) ($publishing['poll_interval_seconds'] ?? 60)),
            pollMaxSeconds: max(0, (int) ($publishing['poll_max_seconds'] ?? 300)),
            enforceQuota: (bool) ($publishing['enforce_quota'] ?? true),
            publishRetries: max(0, (int) ($publishing['publish_retries'] ?? 1)),
            fetchPermalink: (bool) ($publishing['fetch_permalink'] ?? true),
            events: (bool) ($config['events'] ?? true),
        );
    }

    /**
     * Returns a copy with other app credentials (e.g. credentials per project).
     */
    public function withCredentials(string $appId, string $appSecret, ?string $redirectUri = null): self
    {
        return new self(
            appId: $appId,
            appSecret: $appSecret,
            redirectUri: $redirectUri ?? $this->redirectUri,
            scopes: $this->scopes,
            graphUrl: $this->graphUrl,
            authorizeUrl: $this->authorizeUrl,
            version: $this->version,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
            retries: $this->retries,
            retryDelayMs: $this->retryDelayMs,
            initialWaitSeconds: $this->initialWaitSeconds,
            pollIntervalSeconds: $this->pollIntervalSeconds,
            pollMaxSeconds: $this->pollMaxSeconds,
            enforceQuota: $this->enforceQuota,
            publishRetries: $this->publishRetries,
            fetchPermalink: $this->fetchPermalink,
            events: $this->events,
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
