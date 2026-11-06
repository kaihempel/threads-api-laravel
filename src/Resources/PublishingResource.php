<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use KaiHempel\Threads\Data\Concerns;
use KaiHempel\Threads\Data\Container;
use KaiHempel\Threads\Enums\ContainerStatus;
use KaiHempel\Threads\Enums\MediaType;
use KaiHempel\Threads\Events\ContainerCreated;
use KaiHempel\Threads\Events\PostPublished;
use KaiHempel\Threads\Exceptions\ApiException;
use KaiHempel\Threads\Exceptions\ContainerFailedException;
use KaiHempel\Threads\Exceptions\ContainerNotReadyException;
use KaiHempel\Threads\Exceptions\QuotaExceededException;
use KaiHempel\Threads\Exceptions\ThreadsException;
use KaiHempel\Threads\Http\GraphRequest;
use KaiHempel\Threads\Publishing\CarouselItem;
use KaiHempel\Threads\Publishing\PublishResult;
use KaiHempel\Threads\Support\ThreadsConfig;

/**
 * Low level publishing: containers, status, publish (idempotent), delete.
 */
final class PublishingResource extends Resource
{
    public function __construct(
        GraphRequest $request,
        string $userId,
        private readonly ThreadsConfig $config,
        private readonly QuotaResource $quota,
        private readonly ?Dispatcher $events = null,
    ) {
        parent::__construct($request, $userId);
    }

    /**
     * POST /{user-id}/threads with raw parameters.
     *
     * @param  array<string, mixed>  $params
     */
    public function createContainer(array $params): Container
    {
        $response = $this->request->post("{$this->userId}/threads", $params);
        $container = Container::fromArray($response);

        if ($container->id === '') {
            throw new ApiException('The Threads API did not return a container id.');
        }

        $mediaType = $params['media_type'] ?? null;
        $this->dispatch(new ContainerCreated(
            $container,
            $this->userId,
            $mediaType instanceof MediaType ? $mediaType : MediaType::tryFrom((string) (is_scalar($mediaType) ? $mediaType : '')),
            (bool) ($params['is_carousel_item'] ?? false),
        ));

        return $container;
    }

    public function createCarouselItem(CarouselItem $item): Container
    {
        return $this->createContainer($item->toParams());
    }

    /**
     * Container incl. status and error message (GET /{container-id}?fields=id,status,error_message).
     */
    public function container(string $containerId): Container
    {
        $container = Container::fromArray($this->request->get($containerId, ['fields' => 'id,status,error_message']));

        return $container->id === '' ? new Container($containerId, $container->status, $container->error, $container->errorMessage) : $container;
    }

    public function status(string $containerId): ?ContainerStatus
    {
        return $this->container($containerId)->status;
    }

    /**
     * Publishes a container. Safe to call repeatedly (e.g. from a retried queue job):
     *  - the container status is checked first: PUBLISHED => success (no second post), ERROR/EXPIRED => exception
     *  - if the publish call fails with a network error / 5xx, the status is checked again before retrying
     *
     * @param  bool|null  $enforceQuota  null = use config publishing.enforce_quota
     * @param  bool  $isReply  check the reply quota instead of the post quota
     * @param  bool  $checkStatus  check the container status before publishing
     */
    public function publish(string $containerId, ?bool $enforceQuota = null, bool $isReply = false, bool $checkStatus = true): PublishResult
    {
        if ($checkStatus) {
            $container = $this->container($containerId);

            if ($container->status === ContainerStatus::Published) {
                return $this->published(new PublishResult($containerId, null, recovered: true));
            }

            $this->assertNotFailed($container);

            if ($container->status === ContainerStatus::InProgress) {
                throw new ContainerNotReadyException("The container $containerId is still being processed.", $containerId);
            }
        }

        if ($enforceQuota ?? $this->config->enforceQuota) {
            $this->assertQuota($isReply);
        }

        $attempt = 0;

        while (true) {
            try {
                $response = $this->request->post("{$this->userId}/threads_publish", ['creation_id' => $containerId]);

                break;
            } catch (ApiException $e) {
                if (! $e->isRetryable()) {
                    throw $e;
                }

                // Outcome unknown (timeout / 5xx): the container status is authoritative.
                try {
                    $container = $this->container($containerId);
                } catch (ThreadsException) {
                    throw $e;
                }

                if ($container->status === ContainerStatus::Published) {
                    return $this->published(new PublishResult($containerId, null, recovered: true));
                }

                $this->assertNotFailed($container);

                if ($container->status === ContainerStatus::Finished && $attempt < $this->config->publishRetries) {
                    $attempt++;

                    continue;
                }

                throw $e;
            }
        }

        $mediaId = Concerns::string($response['id'] ?? null);

        if ($mediaId === null) {
            throw new ApiException('The Threads API did not return a media id.');
        }

        return $this->published(new PublishResult($containerId, $mediaId));
    }

    /**
     * Result for a text post created with auto_publish_text=true (no separate publish call).
     *
     * @internal
     */
    public function autoPublished(Container $container): PublishResult
    {
        return $this->published(new PublishResult($container->id, $container->id));
    }

    /**
     * DELETE /{media-id} (threads_delete, 100 deletes / 24h).
     */
    public function delete(string $mediaId): bool
    {
        return (bool) ($this->request->delete($mediaId)['success'] ?? false);
    }

    public function permalink(string $mediaId): ?string
    {
        return Concerns::string($this->request->get($mediaId, ['fields' => 'permalink'])['permalink'] ?? null);
    }

    /**
     * @throws QuotaExceededException
     */
    public function assertQuota(bool $isReply = false): void
    {
        $limit = $this->quota->publishing();
        $exhausted = $isReply ? $limit->isReplyQuotaExhausted() : $limit->isExhausted();

        if (! $exhausted) {
            return;
        }

        $duration = $isReply ? ($limit->replyQuotaDuration ?? 86400) : $limit->quotaDuration;
        $total = $isReply ? (int) $limit->replyQuotaTotal : $limit->quotaTotal;
        $kind = $isReply ? 'replies' : 'posts';

        throw new QuotaExceededException(
            sprintf('The publishing limit of %d %s per rolling 24 hours is reached.', $total, $kind),
            limit: $limit,
            resetEstimate: CarbonImmutable::now()->addSeconds($duration),
            quota: $kind,
        );
    }

    private function assertNotFailed(Container $container): void
    {
        if ($container->status === null || ! $container->status->isFailed()) {
            return;
        }

        throw ContainerFailedException::fromContainer($container);
    }

    private function published(PublishResult $result): PublishResult
    {
        if ($this->config->fetchPermalink && $result->mediaId !== null) {
            try {
                $result = $result->withPermalink($this->permalink($result->mediaId));
            } catch (ThreadsException) {
                // the post is published - a missing permalink must not fail the operation
            }
        }

        $this->dispatch(new PostPublished($result, $this->userId));

        return $result;
    }

    private function dispatch(object $event): void
    {
        if ($this->config->events) {
            $this->events?->dispatch($event);
        }
    }
}
