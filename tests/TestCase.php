<?php

namespace Goodport\AiModerator\Tests;

use Goodport\AiJournal\AiJournalServiceProvider;
use Goodport\AiModerator\AiModeratorServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            AiJournalServiceProvider::class,
            AiModeratorServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai-moderator.defaults.provider', 'openai');
        $app['config']->set('ai-moderator.defaults.model', 'gpt-test');
        $app['config']->set('ai-journal.enabled', false);
        $app['config']->set('ai-moderator.journal.replay.enabled', false);
    }

    /**
     * @param  array<string, mixed>  $check
     */
    protected function setCheck(string $key, array $check): void
    {
        $this->app['config']->set("ai-moderator.checks.{$key}", $check);
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    protected function setPolicy(string $key, array $policy): void
    {
        $this->app['config']->set("ai-moderator.policies.{$key}", $policy);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    protected function setProfile(string $key, array $profile): void
    {
        $this->app['config']->set("ai-moderator.profiles.{$key}", $profile);
    }
}
