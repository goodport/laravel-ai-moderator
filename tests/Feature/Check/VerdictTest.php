<?php

namespace Goodport\AiModerator\Tests\Feature\Check;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Input\InvalidInputException;
use Goodport\AiModerator\Tests\TestCase;

final class VerdictTest extends TestCase
{
    /**
     * The model approves the content.
     */
    public function test_approves_content(): void
    {
        $this->configureVerdictCheck();

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Looks fine.', 'violations' => []],
        ]);

        $result = AiModerator::check('post_body')
            ->input(['body' => 'Hello'])
            ->run();

        $this->assertTrue($result->passed());
        $this->assertFalse($result->failed());
        $this->assertSame('Looks fine.', $result->reason());
        $this->assertSame(1, $result->meta->pass);
        $this->assertNotNull($result->meta->invocationId);
    }

    /**
     * The model rejects the content with a blocking violation.
     */
    public function test_rejects_content(): void
    {
        $this->configureVerdictCheck();

        ModerationAgent::fake([
            [
                'verdict' => 'reject',
                'reason' => 'Contains bad content.',
                'violations' => [
                    ['code' => 'bad', 'message' => 'Bad content'],
                ],
            ],
        ]);

        $result = AiModerator::check('post_body')
            ->input(['body' => 'Bad'])
            ->run();

        $this->assertFalse($result->passed());
        $this->assertTrue($result->failed());
        $this->assertSame('Contains bad content.', $result->reason());
        $this->assertCount(1, $result->violations);
        $this->assertSame('bad', $result->violations[0]->code);
        $this->assertTrue($result->violations[0]->blocking);
    }

    /**
     * An inconsistent model response becomes undecided, not approved.
     */
    public function test_inconsistent_response_is_undecided(): void
    {
        $this->configureVerdictCheck();

        ModerationAgent::fake([
            ['verdict' => 'reject', 'reason' => 'Rejected.', 'violations' => []],
        ]);

        $result = AiModerator::check('post_body')
            ->input(['body' => 'Bad'])
            ->run();

        $this->assertTrue($result->undecided());
        $this->assertNull($result->passed());
        $this->assertNotNull($result->meta->error);
    }

    /**
     * A disabled check does not call the model.
     */
    public function test_disabled_check_is_skipped(): void
    {
        $this->configureVerdictCheck(['enabled' => false]);

        $result = AiModerator::check('post_body')->run();

        $this->assertTrue($result->skipped());
        $this->assertNull($result->passed());
        ModerationAgent::assertNeverPrompted();
    }

    /**
     * Without input the check fails before calling the model.
     */
    public function test_requires_input(): void
    {
        $this->configureVerdictCheck();

        $this->expectException(InvalidInputException::class);

        AiModerator::check('post_body')->run();
    }

    /**
     * The second pass can rescue rejected content.
     */
    public function test_second_pass_can_rescue_rejection(): void
    {
        $this->configureVerdictCheck([
            'second_pass' => [
                'enabled' => true,
                'trigger' => 'on_reject',
                'merge' => 'approve_wins',
            ],
        ]);

        ModerationAgent::fake([
            [
                'verdict' => 'reject',
                'reason' => 'First pass rejected.',
                'violations' => [['code' => 'bad', 'message' => 'Bad']],
            ],
            ['verdict' => 'approve', 'reason' => 'Second pass approved.', 'violations' => []],
        ]);

        $result = AiModerator::check('post_body')
            ->input(['body' => 'Maybe bad'])
            ->run();

        $this->assertTrue($result->passed());
        $this->assertSame(2, $result->meta->pass);
        $this->assertSame('Second pass approved.', $result->reason());
    }

    /**
     * Strict merging keeps the first pass rejection.
     */
    public function test_strict_merge_keeps_rejection(): void
    {
        $this->configureVerdictCheck([
            'second_pass' => [
                'enabled' => true,
                'trigger' => 'on_reject',
                'merge' => 'strict',
            ],
        ]);

        ModerationAgent::fake([
            [
                'verdict' => 'reject',
                'reason' => 'First pass rejected.',
                'violations' => [['code' => 'bad', 'message' => 'Bad']],
            ],
            ['verdict' => 'approve', 'reason' => 'Second pass approved.', 'violations' => []],
        ]);

        $result = AiModerator::check('post_body')
            ->input(['body' => 'Bad'])
            ->run();

        $this->assertTrue($result->failed());
        $this->assertSame(1, $result->meta->pass);
        $this->assertSame('First pass rejected.', $result->reason());
    }

    /**
     * The second pass is not run after approval.
     */
    public function test_second_pass_is_not_run_after_approval(): void
    {
        $this->configureVerdictCheck([
            'second_pass' => [
                'enabled' => true,
                'trigger' => 'on_reject',
                'merge' => 'approve_wins',
            ],
        ]);

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        AiModerator::check('post_body')
            ->input(['body' => 'Hello'])
            ->run();

        ModerationAgent::assertPromptedTimes(1);
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
