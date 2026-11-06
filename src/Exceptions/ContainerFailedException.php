<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Exceptions;

use KaiHempel\Threads\Data\Container;
use KaiHempel\Threads\Enums\ContainerError;
use KaiHempel\Threads\Enums\ContainerStatus;

/**
 * The media container is in a terminal state (ERROR or EXPIRED). A new container is required.
 */
class ContainerFailedException extends ThreadsException
{
    public function __construct(
        string $message,
        public readonly string $containerId,
        public readonly ContainerStatus $status,
        public readonly ?ContainerError $containerError = null,
    ) {
        parent::__construct($message);
    }

    public static function fromContainer(Container $container): self
    {
        $status = $container->status ?? ContainerStatus::Error;

        return new self(
            sprintf('The container %s is %s%s.', $container->id, $status->value, $container->errorMessage !== null ? ' ('.$container->errorMessage.')' : ''),
            $container->id,
            $status,
            $container->error,
        );
    }
}
