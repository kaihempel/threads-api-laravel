<?php

declare(strict_types=1);

namespace KaiHempel\Threads;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Translation\Translator;
use KaiHempel\Threads\Http\GraphRequest;
use KaiHempel\Threads\Publishing\ContainerPoller;
use KaiHempel\Threads\Publishing\PostBuilder;
use KaiHempel\Threads\Publishing\PostValidator;
use KaiHempel\Threads\Publishing\Sleeper;
use KaiHempel\Threads\Resources\InsightsResource;
use KaiHempel\Threads\Resources\LocationResource;
use KaiHempel\Threads\Resources\MediaResource;
use KaiHempel\Threads\Resources\ProfileResource;
use KaiHempel\Threads\Resources\PublishingResource;
use KaiHempel\Threads\Resources\QuotaResource;
use KaiHempel\Threads\Resources\RepliesResource;
use KaiHempel\Threads\Support\ThreadsConfig;

/**
 * Client bound to one access token (and Threads user id, default "me").
 */
final class ThreadsClient
{
    private ?PublishingResource $publishing = null;

    public function __construct(
        private readonly GraphRequest $request,
        private readonly ThreadsConfig $config,
        private readonly Sleeper $sleeper,
        private readonly string $userId = 'me',
        private readonly ?Dispatcher $events = null,
        private readonly ?Translator $translator = null,
    ) {}

    public function userId(): string
    {
        return $this->userId;
    }

    /**
     * Fluent post builder.
     */
    public function post(): PostBuilder
    {
        return new PostBuilder($this->publishing(), $this->poller(), new PostValidator($this->translator), $this->config);
    }

    public function publishing(): PublishingResource
    {
        return $this->publishing ??= new PublishingResource($this->request, $this->userId, $this->config, $this->quota(), $this->events);
    }

    public function poller(): ContainerPoller
    {
        return new ContainerPoller($this->publishing(), $this->sleeper, $this->config);
    }

    public function profile(): ProfileResource
    {
        return new ProfileResource($this->request, $this->userId);
    }

    public function media(): MediaResource
    {
        return new MediaResource($this->request, $this->userId);
    }

    public function replies(): RepliesResource
    {
        return new RepliesResource($this->request, $this->userId);
    }

    public function insights(): InsightsResource
    {
        return new InsightsResource($this->request, $this->userId);
    }

    public function quota(): QuotaResource
    {
        return new QuotaResource($this->request, $this->userId);
    }

    public function locations(): LocationResource
    {
        return new LocationResource($this->request, $this->userId);
    }

    /**
     * Shortcut for $client->publishing()->delete($mediaId).
     */
    public function delete(string $mediaId): bool
    {
        return $this->publishing()->delete($mediaId);
    }
}
