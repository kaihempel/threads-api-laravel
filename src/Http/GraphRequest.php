<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Http;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use KaiHempel\Threads\Exceptions\ApiException;
use KaiHempel\Threads\Support\Redactor;
use KaiHempel\Threads\Support\ThreadsConfig;
use Throwable;

/**
 * Low level HTTP wrapper: base URL, version, token, timeouts, (safe) retries and error mapping.
 *
 * @internal
 */
final class GraphRequest
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ThreadsConfig $config,
        private readonly ?string $accessToken = null,
    ) {}

    public function withToken(string $accessToken): self
    {
        return new self($this->http, $this->config, $accessToken);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = [], bool $versioned = true): array
    {
        return $this->send('GET', $path, $query, $versioned);
    }

    /**
     * POST requests are never retried automatically (container creation / publishing are not idempotent).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function post(string $path, array $params = [], bool $versioned = true): array
    {
        return $this->send('POST', $path, $params, $versioned);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function delete(string $path, array $query = []): array
    {
        return $this->send('DELETE', $path, $query, true);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $params, bool $versioned): array
    {
        $params = $this->normalize($params);

        if ($this->accessToken !== null && ! array_key_exists('access_token', $params)) {
            $params['access_token'] = $this->accessToken;
        }

        $url = $this->url($path, $versioned);
        $redactor = $this->redactor($params);
        $request = $this->pendingRequest($method);

        try {
            $response = match ($method) {
                'GET' => $request->get($url, $params),
                'DELETE' => $request->withQueryParameters($params)->delete($url),
                default => $request->asForm()->post($url, $params),
            };
        } catch (ConnectionException $e) {
            throw ApiException::network(
                (string) $redactor->redact('Connection to the Threads API failed: '.$e->getMessage()),
            );
        } catch (RequestException $e) {
            // only thrown when retries are exhausted with throw=true; we use throw=false, kept for safety
            $response = $e->response;
        }

        return $this->handle($response, $redactor);
    }

    /**
     * @return array<string, mixed>
     */
    private function handle(Response $response, Redactor $redactor): array
    {
        $data = $response->json();

        if (! $response->successful() || (is_array($data) && isset($data['error']))) {
            throw ErrorParser::parse($response->status(), $response->body(), $response->headers(), $redactor);
        }

        return is_array($data) ? $data : [];
    }

    private function pendingRequest(string $method): PendingRequest
    {
        $request = $this->http
            ->timeout($this->config->timeout)
            ->connectTimeout($this->config->connectTimeout)
            ->acceptJson();

        if ($method === 'GET' && $this->config->retries > 0) {
            $request->retry(
                $this->config->retries + 1,
                $this->config->retryDelayMs,
                static fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            );
        }

        return $request;
    }

    private function url(string $path, bool $versioned): string
    {
        $path = ltrim($path, '/');

        return $versioned
            ? sprintf('%s/%s/%s', $this->config->graphUrl, $this->config->version, $path)
            : sprintf('%s/%s', $this->config->graphUrl, $path);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, string|int|float>
     */
    private function normalize(array $params): array
    {
        $normalized = [];

        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }

            $normalized[$key] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->getTimestamp(),
                is_array($value) => array_is_list($value) && $this->isScalarList($value)
                    ? implode(',', array_map(static fn (mixed $v): string => $v instanceof BackedEnum ? (string) $v->value : (string) $v, $value))
                    : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                is_int($value), is_float($value) => $value,
                default => (string) $value,
            };
        }

        return $normalized;
    }

    /**
     * @param  list<mixed>  $values
     */
    private function isScalarList(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_scalar($value) && ! $value instanceof BackedEnum) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function redactor(array $params): Redactor
    {
        $secrets = [$this->accessToken, $this->config->appSecret];

        foreach (['access_token', 'client_secret', 'code'] as $key) {
            if (isset($params[$key]) && is_string($params[$key])) {
                $secrets[] = $params[$key];
            }
        }

        return new Redactor($secrets);
    }
}
