<?php

namespace Goodport\AiModerator;

use Goodport\AiModerator\Config\CheckRegistry;
use Goodport\AiModerator\Journal\JournalReplay;
use Illuminate\Support\ServiceProvider;

final class AiModeratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-moderator.php', 'ai-moderator');

        $this->app->singleton(CheckRegistry::class);
        $this->app->singleton(JournalReplay::class);
        $this->app->singleton(AiModeratorManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ai-moderator.php' => config_path('ai-moderator.php'),
            ], 'ai-moderator-config');
        }
    }
}
