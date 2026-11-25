<?php

namespace Goodport\AiModerator\Tests\Feature\Check;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Tests\TestCase;

final class ChecklistTest extends TestCase
{
    /**
     * All answers match the expected values.
     */
    public function test_passes_when_all_answers_match(): void
    {
        $this->configureChecklistCheck();

        ModerationAgent::fake([
            ['answers' => ['insults' => 'no', 'ads' => 'no'], 'reason' => 'Looks fine.'],
        ]);

        $result = AiModerator::check('review_questions')
            ->input(['body' => 'A review'])
            ->run();

        $this->assertTrue($result->passed());
        $this->assertSame(['insults' => 'no', 'ads' => 'no'], $result->answers());
    }

    /**
     * A blocking answer rejects the check.
     */
    public function test_blocking_answer_rejects(): void
    {
        $this->configureChecklistCheck();

        ModerationAgent::fake([
            ['answers' => ['insults' => 'yes', 'ads' => 'no'], 'reason' => 'Contains insults.'],
        ]);

        $result = AiModerator::check('review_questions')
            ->input(['body' => 'A review'])
            ->run();

        $this->assertTrue($result->failed());
        $this->assertCount(1, $result->violations());
        $this->assertSame('insults', $result->violations()[0]->code);
        $this->assertTrue($result->violations()[0]->blocking);
    }

    /**
     * A non-blocking answer keeps the check passed but produces a warning.
     */
    public function test_non_blocking_answer_is_a_warning(): void
    {
        $this->configureChecklistCheck();

        ModerationAgent::fake([
            ['answers' => ['insults' => 'no', 'ads' => 'yes'], 'reason' => 'Contains ads.'],
        ]);

        $result = AiModerator::check('review_questions')
            ->input(['body' => 'A review'])
            ->run();

        $this->assertTrue($result->passed());
        $this->assertCount(1, $result->violations());
        $this->assertSame('ads', $result->violations()[0]->code);
        $this->assertFalse($result->violations()[0]->blocking);
    }

    /**
     * A missing question makes the response invalid.
     */
    public function test_missing_answer_is_undecided(): void
    {
        $this->configureChecklistCheck();

        ModerationAgent::fake([
            ['answers' => ['insults' => 'no'], 'reason' => 'Incomplete.'],
        ]);

        $result = AiModerator::check('review_questions')
            ->input(['body' => 'A review'])
            ->run();

        $this->assertTrue($result->undecided());
    }

    private function configureChecklistCheck(): void
    {
        $this->setCheck('review_questions', [
            'kind' => 'checklist',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Review', 'max_chars' => 1000],
                ],
            ],
            'instructions' => 'Review the content.',
            'questions' => [
                'insults' => [
                    'label' => 'Does it contain insults?',
                    'values' => ['yes', 'no'],
                    'expected' => 'no',
                    'blocking' => true,
                    'severity' => 'high',
                ],
                'ads' => [
                    'label' => 'Does it contain advertising?',
                    'values' => ['yes', 'no'],
                    'expected' => 'no',
                    'blocking' => false,
                    'severity' => 'medium',
                ],
            ],
        ]);
    }
}
