<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Resources;

use BackedEnum;
use KaiHempel\Threads\Http\GraphRequest;

/**
 * @internal
 */
abstract class Resource
{
    public function __construct(
        protected readonly GraphRequest $request,
        protected readonly string $userId = 'me',
    ) {}

    /**
     * @param  list<string|BackedEnum>|string|null  $fields
     * @param  list<string>  $default
     */
    protected function fields(array|string|null $fields, array $default): string
    {
        if (is_string($fields)) {
            return $fields;
        }

        $fields ??= $default;

        return implode(',', array_map(
            static fn (string|BackedEnum $field): string => $field instanceof BackedEnum ? (string) $field->value : $field,
            $fields,
        ));
    }
}
