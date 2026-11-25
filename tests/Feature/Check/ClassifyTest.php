<?php

namespace Goodport\AiModerator\Tests\Feature\Check;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Tests\TestCase;

final class ClassifyTest extends TestCase
{
    /**
     * A label outside reject_on passes the check.
     */
    public function test_passes_allowed_label(): void
    {
        $this->configureClassifyCheck();

        ModerationAgent::fake([
            ['label' => 'safe', 'reason' => 'Safe content.'],
        ]);

        $result = AiModerator::check('age_rating')
            ->input(['body' => 'A story'])
            ->run();

        $this->assertTrue($result->passed());
        $this->assertSame('safe', $result->label());
    }

    /**
     * A label in reject_on rejects the check.
     */
    public function test_rejects_blocked_label(): void
    {
        $this->configureClassifyCheck();

        ModerationAgent::fake([
            ['label' => 'adult', 'reason' => 'Adult content.'],
        ]);

        $result = AiModerator::check('age_rating')
            ->input(['body' => 'A story'])
            ->run();

        $this->assertTrue($result->failed());
        $this->assertSame('adult', $result->label());
        $this->assertCount(1, $result->violations());
        $this->assertSame('adult', $result->violations()[0]->code);
    }

    /**
     * An unknown label makes the response invalid.
     */
    public function test_unknown_label_is_undecided(): void
    {
        $this->configureClassifyCheck();

        ModerationAgent::fake([
            ['label' => 'unknown', 'reason' => 'Unknown.'],
        ]);

        $result = AiModerator::check('age_rating')
            ->input(['body' => 'A story'])
            ->run();

        $this->assertTrue($result->undecided());
    }

    /**
     * An empty reject_on makes the check label-only.
     */
    public function test_empty_reject_on_never_rejects(): void
    {
        $this->configureClassifyCheck([
            'decision' => ['reject_on' => [], 'severity' => 'high'],
        ]);

        ModerationAgent::fake([
            ['label' => 'adult', 'reason' => 'Adult content.'],
        ]);

        $result = AiModerator::check('age_rating')
            ->input(['body' => 'A story'])
            ->run();

        $this->assertTrue($result->passed());
        $this->assertSame('adult', $result->label());
    }

    private function configureClassifyCheck(array $overrides = []): void
    {
        $this->setCheck('age_rating', array_replace([
            'kind' => 'classify',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 1000],
                ],
            ],
            'instructions' => 'Review the content.',
            'labels' => ['safe', 'adult'],
            'decision' => ['reject_on' => ['adult'], 'severity' => 'high'],
        ], $overrides));
    }
}
