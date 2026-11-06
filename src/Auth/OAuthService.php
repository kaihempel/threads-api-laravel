<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Auth;

use BackedEnum;
use Illuminate\Contracts\Events\Dispatcher;
use KaiHempel\Threads\Auth\Data\TokenSet;
use KaiHempel\Threads\Events\TokenRefreshed;
use KaiHempel\Threads\Exceptions\ApiError;
use KaiHempel\Threads\Exceptions\AuthenticationException;
use KaiHempel\Threads\Exceptions\AuthorizationDeniedException;
use KaiHempel\Threads\Exceptions\ConfigurationException;
use KaiHempel\Threads\Exceptions\TokenExpiredException;
use KaiHempel\Threads\Exceptions\TokenNotRefreshableException;
use KaiHempel\Threads\Http\GraphRequest;
use KaiHempel\Threads\Support\ThreadsConfig;
use SensitiveParameter;

/**
 * OAuth 2.0 flow: authorize URL -> code -> short-lived token (1h) -> long-lived token (60 days) -> refresh.
 */
final class OAuthService
{
    public const int SHORT_LIVED_SECONDS = 3600;

    public const int LONG_LIVED_SECONDS = 5184000; // 60 days

    public function __construct(
        private readonly GraphRequest $request,
        private readonly ThreadsConfig $config,
        private readonly ?Dispatcher $events = null,
    ) {}

    /**
     * @param  list<string|BackedEnum>|null  $scopes
     */
    public function authorizationUrl(?string $state = null, ?array $scopes = null, ?string $redirectUri = null): string
    {
        $scopes = array_map(
            static fn (string|BackedEnum $scope): string => $scope instanceof BackedEnum ? (string) $scope->value : $scope,
            $scopes ?? $this->config->scopes,
        );

        $query = array_filter([
            'client_id' => $this->requireAppId(),
            'redirect_uri' => $this->requireRedirectUri($redirectUri),
            'scope' => implode(',', array_unique($scopes)),
            'response_type' => 'code',
            'state' => $state,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        return $this->config->authorizeUrl.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Handles the redirect query of the callback route. Throws if the user denied access.
     * State validation is the job of the application (see StateGenerator::matches()).
     *
     * @param  array<string, mixed>  $query
     */
    public function handleCallback(array $query, ?string $redirectUri = null): TokenSet
    {
        if (isset($query['error'])) {
            $description = is_string($query['error_description'] ?? null) ? $query['error_description'] : 'The authorization was denied.';
            $error = new ApiError(
                message: $description,
                type: is_string($query['error_reason'] ?? null) ? $query['error_reason'] : (string) (is_scalar($query['error']) ? $query['error'] : 'error'),
            );

            if ($query['error'] === 'access_denied') {
                throw new AuthorizationDeniedException($description, $error);
            }

            throw new AuthenticationException($description, $error);
        }

        $code = $query['code'] ?? null;

        if (! is_string($code) || $code === '') {
            throw new AuthenticationException('The callback does not contain an authorization code.');
        }

        return $this->exchangeCode($code, $redirectUri);
    }

    /**
     * Exchanges the authorization code (valid 1h, single use) for a short-lived token (1h).
     */
    public function exchangeCode(#[SensitiveParameter] string $code, ?string $redirectUri = null): TokenSet
    {
        // Meta appends "#_" to the code in the redirect.
        $code = preg_replace('/#_$/', '', $code) ?? $code;

        $response = $this->request->post('oauth/access_token', [
            'client_id' => $this->requireAppId(),
            'client_secret' => $this->requireSecret(),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->requireRedirectUri($redirectUri),
            'code' => $code,
        ], versioned: false);

        return TokenSet::fromResponse($response, longLived: false, defaultLifetime: self::SHORT_LIVED_SECONDS);
    }

    /**
     * Exchanges a (non expired) short-lived token for a long-lived token (60 days). Server side only.
     */
    public function toLongLived(#[SensitiveParameter] string|TokenSet $shortLivedToken): TokenSet
    {
        $previous = $shortLivedToken instanceof TokenSet ? $shortLivedToken : null;

        if ($previous !== null && $previous->isExpired()) {
            throw new TokenExpiredException('The short-lived token is expired and cannot be exchanged anymore. The user has to authorize again.');
        }

        $response = $this->request->get('access_token', [
            'grant_type' => 'th_exchange_token',
            'client_secret' => $this->requireSecret(),
            'access_token' => $previous->accessToken ?? $shortLivedToken,
        ], versioned: false);

        return TokenSet::fromResponse(
            $response,
            longLived: true,
            userId: $previous?->userId,
            scopes: $previous->scopes ?? [],
            defaultLifetime: self::LONG_LIVED_SECONDS,
        );
    }

    /**
     * Refreshes a long-lived token (>= 24h old, not expired) for another 60 days.
     *
     * Pass a TokenSet to get a local, understandable exception if the token is too young or expired.
     */
    public function refresh(#[SensitiveParameter] string|TokenSet $longLivedToken): TokenSet
    {
        $previous = $longLivedToken instanceof TokenSet ? $longLivedToken : null;

        // The refresh endpoint itself does not need the secret, but it must only be called server side.
        $this->requireSecret();

        if ($previous !== null) {
            if ($previous->isExpired()) {
                throw new TokenExpiredException('The access token is expired and cannot be refreshed. The user has to authorize again.');
            }

            if (! $previous->canRefresh()) {
                throw new TokenNotRefreshableException(
                    $previous->longLived
                        ? 'The access token must be at least 24 hours old before it can be refreshed.'
                        : 'Only long-lived tokens can be refreshed. Exchange the short-lived token with toLongLived() first.'
                );
            }
        }

        $response = $this->request->get('refresh_access_token', [
            'grant_type' => 'th_refresh_token',
            'access_token' => $previous->accessToken ?? $longLivedToken,
        ], versioned: false);

        $token = TokenSet::fromResponse(
            $response,
            longLived: true,
            userId: $previous?->userId,
            scopes: $previous->scopes ?? [],
            defaultLifetime: self::LONG_LIVED_SECONDS,
        );

        if ($this->config->events) {
            $this->events?->dispatch(new TokenRefreshed($token, $previous));
        }

        return $token;
    }

    private function requireAppId(): string
    {
        return $this->config->appId ?? throw new ConfigurationException('The Threads app id is not configured (THREADS_APP_ID).');
    }

    private function requireSecret(): string
    {
        return $this->config->appSecret ?? throw new ConfigurationException('The Threads app secret is not configured (THREADS_APP_SECRET). Token exchange and refresh must run server side.');
    }

    private function requireRedirectUri(?string $redirectUri): string
    {
        return $redirectUri ?? $this->config->redirectUri ?? throw new ConfigurationException('The Threads redirect uri is not configured (THREADS_REDIRECT_URI).');
    }
}
