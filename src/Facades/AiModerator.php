<?php

namespace Goodport\AiModerator\Facades;

use Goodport\AiModerator\AiModeratorManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Goodport\AiModerator\Checks\PendingCheck check(string $key)
 * @method static \Goodport\AiModerator\Policies\PendingPolicy policy(string $key)
 *
 * @see AiModeratorManager
 */
final class AiModerator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AiModeratorManager::class;
    }
}
