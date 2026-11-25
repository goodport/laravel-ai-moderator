<?php

namespace Goodport\AiModerator\Tests\Feature\Check;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Responses\InvalidResponseException;
use Goodport\AiModerator\Tests\TestCase;

final class ErrorHandlingTest extends TestCase
{
    /**
     * on_error=throw rethrows an invalid response error.
     */
    public function test_throw_mode_rethrows(): void
    {
        $this->configureVerdictCheck(['on_error' => 'throw']);

        ModerationAgent::fake([
            ['verdict' => 'reject', 'reason' => 'Broken.', 'violations' => []],
        ]);

        $this->expectException(InvalidResponseException::class);

        AiModerator::check('post_body')
            ->input(['body' => 'Hello'])
            ->run();
    }

    /**
     * The always second pass can reject approved content.
     */
    public function test_always_trigger_runs_after_approval(): void
    {
        $this->configureVerdictCheck([
            'second_pass' => [
                'enabled' => true,
                'trigger' => 'always',
                'merge' => 'strict',
            ],
        ]);

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'First pass is fine.', 'violations' => []],
            [
                'verdict' => 'reject',
                'reason' => 'Second pass is strict.',
                'violations' => [['code' => 'bad', 'message' => 'Bad']],
            ],
        ]);

        $result = AiModerator::check('post_body')
            ->input(['body' => 'Hello'])
            ->run();

        $this->assertTrue($result->failed());
        $this->assertSame(2, $result->meta->pass);
    }

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
