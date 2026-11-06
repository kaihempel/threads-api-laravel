<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Exceptions;

/**
 * Authentication with the Threads API failed (invalid code, invalid credentials, ...).
 */
class AuthenticationException extends ThreadsException {}
