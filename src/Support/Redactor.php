<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Support;

/**
 * Removes access tokens and secrets from strings (exception messages, raw bodies, URLs).
 */
final class Redactor
{
    public const string MASK = '***';

    private const array SENSITIVE_KEYS = ['access_token', 'client_secret', 'code', 'app_secret'];

    /** @var list<string> */
    private array $secrets;

    /**
     * @param  list<string|null>  $secrets  Literal values that must never leak.
     */
    public function __construct(array $secrets = [])
    {
        $this->secrets = array_values(array_filter(
            $secrets,
            static fn (?string $secret): bool => $secret !== null && strlen($secret) >= 4,
        ));
    }

    /**
     * @param  list<string|null>  $secrets
     */
    public function with(array $secrets): self
    {
        return new self([...$this->secrets, ...$secrets]);
    }

    public function redact(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        foreach ($this->secrets as $secret) {
            $value = str_replace([$secret, rawurlencode($secret), urlencode($secret)], self::MASK, $value);
        }

        $keys = implode('|', self::SENSITIVE_KEYS);

        // query strings / form bodies: access_token=abc&...
        $value = (string) preg_replace('/(?<![A-Za-z_])('.$keys.')=([^&\s"\'<>]+)/i', '$1='.self::MASK, $value);

        // JSON bodies: "access_token":"abc"
        return (string) preg_replace('/"('.$keys.')"\s*:\s*"[^"]*"/i', '"$1":"'.self::MASK.'"', $value);
    }
}
