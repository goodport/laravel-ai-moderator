<?php

namespace Goodport\AiModerator\Tests\Feature\Check;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Tests\Fixtures\CustomCheck;
use Goodport\AiModerator\Tests\TestCase;
use Laravel\Ai\Prompts\AgentPrompt;

final class CustomCheckTest extends TestCase
{
    /**
     * A class-based check defines its own prompt, rule, and details.
     */
    public function test_class_check_uses_custom_builder_and_rule(): void
    {
        $this->app['config']->set('ai-moderator.checks.custom', CustomCheck::class);

        ModerationAgent::fake([
            [
                'verdict' => 'approve',
                'reason' => 'Model reason.',
                'violations' => [],
                'details' => ['score' => 0.42],
            ],
        ]);

        $result = AiModerator::check('custom')
            ->input(['body' => 'Hello'])
            ->run();

        $this->assertTrue($result->passed());
        $this->assertSame('Custom rule.', $result->reason());
        $this->assertSame(['score' => 0.42], $result->details());

        ModerationAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            return $prompt->agent->instructions() === 'Custom system.'
                && $prompt->prompt === 'Custom user.';
        });
    }
}
