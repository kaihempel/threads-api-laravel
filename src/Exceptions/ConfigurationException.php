<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Exceptions;

/**
 * The package is misconfigured (missing app id, secret, redirect uri ...).
 */
class ConfigurationException extends ThreadsException {}
