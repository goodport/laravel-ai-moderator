<?php

namespace Goodport\AiModerator\Tests\Feature\Check;

use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Tests\Fixtures\CustomAgent;
use Goodport\AiModerator\Tests\Fixtures\RateLimitedCustomAgent;
use Goodport\AiModerator\Tests\TestCase;
use Laravel\Ai\Prompts\AgentPrompt;

final class CustomAgentTest extends TestCase
{
    /**
     * A custom agent owns its instructions and schema; the package only builds the user prompt.
     */
    public function test_custom_agent_is_used(): void
    {
        $this->setCheck('post_body', [
            'agent' => CustomAgent::class,
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 100],
                ],
            ],
            'instructions' => 'Ignored instructions.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true],
            ],
        ]);

        CustomAgent::fake([
            [
                'verdict' => 'reject',
                'reason' => 'Bad content.',
                'violations' => [['code' => 'bad', 'message' => 'Bad']],
            ],
        ]);

        $result = AiModerator::check('post_body')
            ->input(['body' => 'Hello'])
            ->run();

        $this->assertTrue($result->failed());

        CustomAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            return $prompt->agent->instructions() === 'Custom agent instructions.'
                && str_contains($prompt->prompt, "Body:\nHello");
        });
    }

    /**
     * A custom agent can attach the resilience rate limiter itself.
     */
    public function test_custom_agent_can_use_rate_limit_middleware(): void
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
            'agent' => RateLimitedCustomAgent::class,
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 100],
                ],
            ],
            'instructions' => 'Ignored instructions.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true],
            ],
        ]);

        RateLimitedCustomAgent::fake([
            ['verdict' => 'approve', 'reason' => 'First.', 'violations' => []],
            ['verdict' => 'approve', 'reason' => 'Second.', 'violations' => []],
        ]);

        $first = AiModerator::check('post_body')->input(['body' => 'Hello'])->run();
        $second = AiModerator::check('post_body')->input(['body' => 'Hello'])->run();

        $this->assertTrue($first->passed());
        $this->assertTrue($second->undecided());
    }
}
