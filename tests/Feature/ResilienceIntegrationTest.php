<?php

namespace Goodport\AiModerator\Tests\Feature;

use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Tests\TestCase;
use Laravel\Ai\Exceptions\ProviderOverloadedException;

final class ResilienceIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-journal.enabled', true);
    }

    /**
     * The configured rate limit blocks a second provider call.
     */
    public function test_rate_limit_blocks_excess_calls(): void
    {
        $this->app['config']->set('ai-resilience.limits.enabled', true);
        $this->app['config']->set('ai-resilience.limits.default', [
            'max_requests' => 1,
            'per_seconds' => 60,
            'min_interval_seconds' => 0,
            'mode' => 'fail',
            'max_wait_seconds' => 0,
        ]);

        $this->setCheck('post_body', [
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 100],
                ],
            ],
            'instructions' => 'Reject bad content.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true],
            ],
        ]);

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'First.', 'violations' => []],
            ['verdict' => 'approve', 'reason' => 'Second.', 'violations' => []],
        ]);

        $first = AiModerator::check('post_body')->input(['body' => 'Hello'])->run();
        $second = AiModerator::check('post_body')->input(['body' => 'Hello'])->run();

        $this->assertTrue($first->passed());
        $this->assertTrue($second->undecided());
    }

    /**
     * A transient provider error is retried before the moderation call fails.
     */
    public function test_transient_error_is_retried(): void
    {
        $this->app['config']->set('ai-resilience.retries.enabled', true);
        $this->app['config']->set('ai-resilience.retries.attempts', 2);
        $this->app['config']->set('ai-resilience.retries.base_delay_ms', 0);

        $this->setCheck('post_body', [
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 100],
                ],
            ],
            'instructions' => 'Reject bad content.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true],
            ],
        ]);

        $attempts = 0;

        ModerationAgent::fake([
            function () use (&$attempts): array {
                $attempts++;

                if ($attempts === 1) {
                    throw ProviderOverloadedException::forProvider('openai');
                }

                return ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []];
            },
        ]);

        $result = AiModerator::check('post_body')->input(['body' => 'Hello'])->run();

        $this->assertTrue($result->passed());
        ModerationAgent::assertPromptedTimes(2);
        $this->assertSame(2, AiInvocation::query()->count());
    }
}
