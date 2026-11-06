<?php

declare(strict_types=1);

namespace KaiHempel\Threads\Facades;

use Illuminate\Support\Facades\Facade;
use KaiHempel\Threads\ThreadsManager;

/**
 * @method static \KaiHempel\Threads\Auth\OAuthService auth()
 * @method static \KaiHempel\Threads\ThreadsClient for(string|\KaiHempel\Threads\Auth\Data\TokenSet $token, ?string $userId = null)
 * @method static \KaiHempel\Threads\ThreadsManager using(string $appId, string $appSecret, ?string $redirectUri = null)
 * @method static \KaiHempel\Threads\Publishing\PostValidator validator()
 * @method static \KaiHempel\Threads\ThreadsCapabilities capabilities()
 * @method static \KaiHempel\Threads\Support\ThreadsConfig config()
 *
 * @see ThreadsManager
 */
final class Threads extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ThreadsManager::class;
    }
}
