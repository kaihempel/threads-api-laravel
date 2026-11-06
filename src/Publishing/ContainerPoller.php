<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Publishing;

use KaiHempel\Threads\Data\Container;
use KaiHempel\Threads\Enums\ContainerStatus;
use KaiHempel\Threads\Exceptions\ContainerFailedException;
use KaiHempel\Threads\Exceptions\ContainerTimeoutException;
use KaiHempel\Threads\Resources\PublishingResource;
use KaiHempel\Threads\Support\ThreadsConfig;

/**
 * Waits until a container is FINISHED (Meta: wait ~30s, poll once per minute, at most 5 minutes).
 */
final class ContainerPoller
{
    public function __construct(
        private readonly PublishingResource $publishing,
        private readonly Sleeper $sleeper,
        private readonly ThreadsConfig $config,
    ) {}

    /**
     * @param  bool  $initialWait  wait initial_wait_seconds before the first check (skip for text posts)
     *
     * @throws ContainerFailedException
     * @throws ContainerTimeoutException
     */
    public function waitUntilReady(string $containerId, bool $initialWait = true): Container
    {
        if ($initialWait) {
            $this->sleeper->sleep($this->config->initialWaitSeconds);
        }

        $waited = 0;

        while (true) {
            $container = $this->publishing->container($containerId);

            if ($container->status === ContainerStatus::Finished || $container->status === ContainerStatus::Published) {
                return $container;
            }

            if ($container->status !== null && $container->status->isFailed()) {
                throw ContainerFailedException::fromContainer($container);
            }

            if ($waited >= $this->config->pollMaxSeconds) {
                throw new ContainerTimeoutException(
                    sprintf('The container %s was not ready after %d seconds. Check its status later.', $containerId, $waited),
                    $containerId,
                    $container->status,
                    $waited,
                );
            }

            $sleep = min($this->config->pollIntervalSeconds, max(1, $this->config->pollMaxSeconds - $waited));
            $this->sleeper->sleep($sleep);
            $waited += $sleep;
        }
    }
}
