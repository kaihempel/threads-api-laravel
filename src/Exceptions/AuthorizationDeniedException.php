<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Exceptions;

/**
 * The user denied the authorization request (error=access_denied).
 */
class AuthorizationDeniedException extends AuthenticationException {}
