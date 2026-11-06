<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Exceptions;

/**
 * The access token is invalid or expired (OAuth code 190). Refresh the token or re-authorize.
 */
class TokenExpiredException extends AuthenticationException {}
