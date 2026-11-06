<?php

declare(strict_types=1);

namespace KaiHempel\Threads;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use KaiHempel\Threads\Publishing\Sleeper;
use KaiHempel\Threads\Publishing\Sleepers\RealSleeper;
use KaiHempel\Threads\Support\ThreadsConfig;

final class ThreadsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/threads.php', 'threads');

        $this->app->bindIf(Sleeper::class, RealSleeper::class);

        $this->app->singleton(ThreadsManager::class, static function (Application $app): ThreadsManager {
            /** @var array<string, mixed> $config */
            $config = (array) $app->make('config')->get('threads', []);

            return new ThreadsManager($app, ThreadsConfig::fromArray($config));
        });

        $this->app->alias(ThreadsManager::class, 'threads');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'threads');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/threads.php' => config_path('threads.php'),
            ], 'threads-config');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/threads'),
            ], 'threads-lang');
        }
    }
}
