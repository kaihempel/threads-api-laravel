<?php

declare(strict_types=1);

namespace KaiHempel\Threads;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Client\Factory as HttpFactory;
use KaiHempel\Threads\Auth\Data\TokenSet;
use KaiHempel\Threads\Auth\OAuthService;
use KaiHempel\Threads\Http\GraphRequest;
use KaiHempel\Threads\Publishing\PostValidator;
use KaiHempel\Threads\Publishing\Sleeper;
use KaiHempel\Threads\Support\ThreadsConfig;
use SensitiveParameter;

/**
 * Entry point: Threads::auth(), Threads::for($token), Threads::using($appId, $secret).
 */
final class ThreadsManager
{
    public function __construct(
        private readonly Container $container,
        private readonly ThreadsConfig $config,
    ) {}

    /**
     * Returns a manager with other app credentials (e.g. credentials per project).
     */
    public function using(string $appId, #[SensitiveParameter] string $appSecret, ?string $redirectUri = null): self
    {
        return new self($this->container, $this->config->withCredentials($appId, $appSecret, $redirectUri));
    }

    public function config(): ThreadsConfig
    {
        return $this->config;
    }

    public function auth(): OAuthService
    {
        return new OAuthService($this->request(), $this->config, $this->events());
    }

    /**
     * Client bound to an access token. $userId defaults to the user id of a TokenSet or "me".
     */
    public function for(#[SensitiveParameter] string|TokenSet $token, ?string $userId = null): ThreadsClient
    {
        $accessToken = $token instanceof TokenSet ? $token->accessToken : $token;
        $userId ??= ($token instanceof TokenSet ? $token->userId : null) ?? 'me';

        return new ThreadsClient(
            $this->request()->withToken($accessToken),
            $this->config,
            $this->container->make(Sleeper::class),
            $userId,
            $this->events(),
            $this->translator(),
        );
    }

    public function validator(): PostValidator
    {
        return new PostValidator($this->translator());
    }

    public function capabilities(): ThreadsCapabilities
    {
        return ThreadsCapabilities::all();
    }

    private function request(): GraphRequest
    {
        // resolved lazily so Http::fake() in tests is always respected
        return new GraphRequest($this->container->make(HttpFactory::class), $this->config);
    }

    private function events(): ?Dispatcher
    {
        if (! $this->config->events || ! $this->container->bound(Dispatcher::class)) {
            return null;
        }

        return $this->container->make(Dispatcher::class);
    }

    private function translator(): ?Translator
    {
        return $this->container->bound('translator') ? $this->container->make('translator') : null;
    }
}
