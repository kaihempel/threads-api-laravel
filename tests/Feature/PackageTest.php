<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Sleep;
use KaiHempel\Threads\Auth\Data\TokenSet;
use KaiHempel\Threads\Exceptions\ApiException;
use KaiHempel\Threads\Facades\Threads;
use KaiHempel\Threads\Publishing\ContainerPoller;
use KaiHempel\Threads\Publishing\PostDraft;
use KaiHempel\Threads\Publishing\Sleeper;
use KaiHempel\Threads\Publishing\Sleepers\RealSleeper;
use KaiHempel\Threads\Support\ThreadsConfig;
use KaiHempel\Threads\Testing\FakeSleeper;
use KaiHempel\Threads\Testing\FakeThreads;
use KaiHempel\Threads\ThreadsCapabilities;
use KaiHempel\Threads\ThreadsClient;
use KaiHempel\Threads\ThreadsManager;
use KaiHempel\Threads\ThreadsServiceProvider;

it('registers the manager, alias and config', function () {
    expect(app(ThreadsManager::class))->toBe(app('threads'))
        ->and(Threads::getFacadeRoot())->toBeInstanceOf(ThreadsManager::class)
        ->and(config('threads.graph_url'))->toBe('https://graph.threads.net')
        ->and(config('threads.publishing.initial_wait_seconds'))->toBe(30)
        ->and(Threads::config()->appId)->toBe('test-app-id');
});

it('creates token bound clients', function () {
    $client = Threads::for('TOKEN');

    expect($client)->toBeInstanceOf(ThreadsClient::class)
        ->and($client->userId())->toBe('me')
        ->and(Threads::for('TOKEN', '42')->userId())->toBe('42')
        ->and(Threads::for(new TokenSet('T', '43'))->userId())->toBe('43')
        ->and($client->poller())->toBeInstanceOf(ContainerPoller::class);
});

it('provides capabilities and the validator', function () {
    expect(Threads::capabilities())->toEqual(ThreadsCapabilities::all())
        ->and(Threads::validator()->countLinks('https://a.de'))->toBe(1);
});

it('loads translations', function () {
    app()->setLocale('de');

    expect(__('threads::validation.text_required'))->toBe('Für Text-Beiträge ist ein Text erforderlich.');
});

it('binds the real sleeper by default', function () {
    $app = $this->app;
    $app->forgetInstance(Sleeper::class);
    $app->offsetUnset(Sleeper::class);
    (new ThreadsServiceProvider($app))->register();

    expect($app->make(Sleeper::class))->toBeInstanceOf(RealSleeper::class);
});

it('sleeps with the real sleeper through the laravel sleep helper', function () {
    Sleep::fake();

    (new RealSleeper)->sleep(3);
    (new RealSleeper)->sleep(0);

    Sleep::assertSleptTimes(1);
    Sleep::assertSequence([Sleep::for(3)->seconds()]);
});

it('publishes the config', function () {
    $this->artisan('vendor:publish', ['--tag' => 'threads-config', '--force' => true])->assertSuccessful();

    expect(file_exists(config_path('threads.php')))->toBeTrue();
    @unlink(config_path('threads.php'));
});

it('works without events and translator bindings', function () {
    $container = new Container;
    $container->instance(Factory::class, app(Factory::class));
    $container->instance(Sleeper::class, new FakeSleeper);
    FakeThreads::make()->fake();

    $manager = new ThreadsManager($container, ThreadsConfig::fromArray([]));

    expect($manager->for('TOKEN')->post()->text('Hallo')->publish()->mediaId)->toBe('18050000000000001')
        ->and($manager->validator()->violations(new PostDraft)[0]->message)->toBe('The text is required for text posts.');
});

it('provides a fake helper with fixtures and a fallback route', function () {
    $fake = FakeThreads::make()->withoutDefaults()->on('*', 'v1.0/me', ['id' => '1']);
    $fake->fake($this->app);

    expect(Threads::for('TOKEN')->profile()->me()->id)->toBe('1')
        ->and(app(Sleeper::class))->toBeInstanceOf(FakeSleeper::class)
        ->and(fn () => Threads::for('TOKEN')->media()->list())->toThrow(ApiException::class, 'no fake route')
        ->and(fn () => FakeThreads::fixture('does_not_exist'))->toThrow(InvalidArgumentException::class)
        ->and(FakeThreads::fixture('profile', ['username' => 'other'])['username'])->toBe('other');
});
