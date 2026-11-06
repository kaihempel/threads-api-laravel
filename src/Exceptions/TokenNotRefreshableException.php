<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Exceptions;

/**
 * The token cannot be refreshed (younger than 24 hours or already expired).
 */
class TokenNotRefreshableException extends AuthenticationException {}
