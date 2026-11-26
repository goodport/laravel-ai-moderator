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

    /**
     * The explicit language is passed to the second pass as well.
     */
    public function test_explicit_language_is_used_in_second_pass(): void
    {
        App::setLocale('ru');
        $this->configureVerdictCheck([
            'language' => 'en',
            'second_pass' => [
                'enabled' => true,
                'trigger' => 'on_reject',
                'merge' => 'approve_wins',
            ],
        ]);

        ModerationAgent::fake([
            ['verdict' => 'reject', 'reason' => 'First.', 'violations' => [['code' => 'bad', 'message' => 'Bad']]],
            ['verdict' => 'approve', 'reason' => 'Second.', 'violations' => []],
        ]);

        AiModerator::check('post_body')
            ->input(['body' => 'Hello'])
            ->run();

        ModerationAgent::assertPrompted(
            fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), 'second-opinion review')
                && str_contains($prompt->agent->instructions(), 'in English')
        );
    }

    /**
     * A policy language is passed to its checks and wins over the check language.
     */
    public function test_policy_language_wins_over_check_language(): void
    {
        App::setLocale('ru');
        $this->configureVerdictCheck(['language' => 'ru']);
        $this->setPolicy('post-policy', ['checks' => ['post_body']]);

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        AiModerator::policy('post-policy')
            ->input(['body' => 'Hello'])
            ->language('en')
            ->run();

        ModerationAgent::assertPrompted(
            fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), 'in English')
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureVerdictCheck(array $overrides = []): void
    {
        $this->setCheck('post_body', array_replace([
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
        ], $overrides));
    }
}
