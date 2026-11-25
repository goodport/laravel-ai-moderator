<?php

namespace Goodport\AiModerator\Tests\Feature\Check;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Tests\TestCase;
use Illuminate\Support\Facades\App;
use Laravel\Ai\Prompts\AgentPrompt;

final class LanguageTest extends TestCase
{
    /**
     * The response language comes from the application locale.
     */
    public function test_uses_app_locale(): void
    {
        App::setLocale('ru');
        $this->configureVerdictCheck();

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        AiModerator::check('post_body')
            ->input(['body' => 'Hello'])
            ->run();

        ModerationAgent::assertPrompted(
            fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), 'in Russian')
        );
    }

    /**
     * An explicit call language overrides the application locale.
     */
    public function test_explicit_language_wins(): void
    {
        App::setLocale('ru');
        $this->configureVerdictCheck();

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        AiModerator::check('post_body')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();

        ModerationAgent::assertPrompted(
            fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), 'in English')
        );
    }

    private function configureVerdictCheck(): void
    {
        $this->setCheck('post_body', [
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 100],
                ],
            ],
            'instructions' => 'Reject bad content.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true, 'description' => 'Bad content'],
            ],
        ]);
    }
}
