<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Tests;

use Illuminate\Support\Facades\Http;
use KaiHempel\Threads\Facades\Threads;
use KaiHempel\Threads\Publishing\Sleeper;
use KaiHempel\Threads\Testing\FakeSleeper;
use KaiHempel\Threads\ThreadsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected FakeSleeper $sleeper;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->sleeper = new FakeSleeper;
        $this->app->instance(Sleeper::class, $this->sleeper);
    }

    protected function getPackageProviders($app): array
    {
        return [ThreadsServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Threads' => Threads::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('threads.app_id', 'test-app-id');
        $app['config']->set('threads.app_secret', 'test-app-secret');
        $app['config']->set('threads.redirect_uri', 'https://example.com/threads/callback');
        $app['config']->set('threads.http.retry_delay_ms', 0);
    }
}
