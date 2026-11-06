<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Exceptions;

use KaiHempel\Threads\Publishing\Violation;
use Throwable;

/**
 * A post violates a (local or remote) rule. Carries field + rule code so a frontend can map it.
 */
class ValidationException extends ThreadsException
{
    /**
     * @param  list<Violation>  $violations
     */
    public function __construct(
        string $message,
        public readonly array $violations,
        ?ApiError $error = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $error, $previous);
    }

    /**
     * @param  list<Violation>  $violations
     */
    public static function withViolations(array $violations): self
    {
        return new self($violations[0]->message ?? 'The post is invalid.', $violations);
    }

    public static function fromApi(string $field, string $rule, ApiError $error): self
    {
        $message = $error->message ?? $rule;

        return new self($message, [new Violation($field, $rule, $message)], $error);
    }

    public function field(): ?string
    {
        return $this->violations[0]->field ?? null;
    }

    public function rule(): ?string
    {
        return $this->violations[0]->rule ?? null;
    }

    /**
     * Errors grouped by field (handy for Inertia / form error bags).
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        $errors = [];

        foreach ($this->violations as $violation) {
            $errors[$violation->field][] = $violation->message;
        }

        return $errors;
    }
}
