<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Testing;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use KaiHempel\Threads\Publishing\Sleeper;

/**
 * Http::fake() helper for applications using this package.
 *
 *   FakeThreads::make()
 *       ->on('POST', '* /threads_publish', FakeThreads::response('error_server', status: 500))
 *       ->fake();
 *
 * Routes are matched by HTTP method and path (without host, e.g. "v1.0/me/threads"); "*" is a wildcard.
 * Own routes win over the defaults, which answer with the fixtures from src/Testing/fixtures.
 */
final class FakeThreads
{
    /** @var list<array{method: string, path: string, response: mixed}> */
    private array $routes = [];

    private bool $withDefaults = true;

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>|PromiseInterface|ResponseSequence|Closure(Request): mixed  $response
     */
    public function on(string $method, string $path, mixed $response): self
    {
        $this->routes[] = ['method' => strtoupper($method), 'path' => ltrim($path, '/'), 'response' => $response];

        return $this;
    }

    public function withoutDefaults(): self
    {
        $this->withDefaults = false;

        return $this;
    }

    /**
     * Registers the routes with Http::fake() and (optionally) binds a FakeSleeper.
     */
    public function fake(?Application $app = null, bool $fakeSleeper = true): Factory
    {
        if ($fakeSleeper && $app !== null) {
            $app->instance(Sleeper::class, new FakeSleeper);
        }

        return Http::fake(fn (Request $request): mixed => $this->resolve($request));
    }

    /**
     * @internal
     */
    public function resolve(Request $request): mixed
    {
        $method = strtoupper($request->method());
        $path = ltrim((string) parse_url($request->url(), PHP_URL_PATH), '/');

        foreach ([...$this->routes, ...($this->withDefaults ? $this->defaults($request) : [])] as $route) {
            if (($route['method'] === '*' || $route['method'] === $method) && Str::is($route['path'], $path)) {
                $response = $route['response'];

                return match (true) {
                    $response instanceof ResponseSequence, $response instanceof Closure => $response($request),
                    is_array($response) => Http::response($response),
                    default => $response,
                };
            }
        }

        return Http::response(['error' => [
            'message' => "FakeThreads: no fake route for $method /$path",
            'type' => 'FakeThreadsException',
            'code' => 404,
        ]], 404);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function fixture(string $name, array $overrides = []): array
    {
        $file = __DIR__.'/fixtures/'.$name.'.json';

        if (! is_file($file)) {
            throw new InvalidArgumentException("Unknown Threads fixture [$name].");
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        return array_replace_recursive($data, $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, string>  $headers
     */
    public static function response(string $fixture, array $overrides = [], int $status = 200, array $headers = []): PromiseInterface
    {
        return Http::response(self::fixture($fixture, $overrides), $status, $headers);
    }

    /**
     * @return list<array{method: string, path: string, response: mixed}>
     */
    private function defaults(Request $request): array
    {
        $fields = (string) ($request->data()['fields'] ?? '');
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $fields = $fields !== '' ? $fields : (is_string($query['fields'] ?? null) ? $query['fields'] : '');

        $route = static fn (string $method, string $path, string $fixture): array => [
            'method' => $method,
            'path' => $path,
            'response' => self::response($fixture),
        ];

        return [
            $route('POST', 'oauth/access_token', 'short_lived_token'),
            $route('GET', 'access_token', 'long_lived_token'),
            $route('GET', 'refresh_access_token', 'refreshed_token'),
            $route('POST', '*/threads_publish', 'published'),
            $route('GET', '*/threads_publishing_limit', 'publishing_limit'),
            $route('GET', '*/threads_insights', 'account_insights'),
            $route('GET', '*/insights', 'media_insights'),
            $route('POST', '*/threads', 'container_created'),
            $route('GET', '*/threads', 'media_list'),
            $route('GET', '*/pending_replies', 'pending_replies'),
            $route('GET', '*/replies', 'replies'),
            $route('GET', '*/conversation', 'replies'),
            $route('POST', '*/manage_reply', 'success'),
            $route('POST', '*/manage_pending_reply', 'success'),
            $route('GET', '*/location_search', 'location_search'),
            $route('GET', '*/me', 'profile'),
            $route('DELETE', '*', 'success'),
            match (true) {
                str_contains($fields, 'status') => $route('GET', '*', 'container_finished'),
                $fields === 'permalink' => $route('GET', '*', 'permalink'),
                default => $route('GET', '*', 'media'),
            },
        ];
    }
}
