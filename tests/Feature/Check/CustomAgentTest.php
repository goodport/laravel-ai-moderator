<?php

namespace Goodport\AiModerator\Tests\Feature\Check;

use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Tests\Fixtures\CustomAgent;
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
}
