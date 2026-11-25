<?php

namespace Goodport\AiModerator\Tests\Feature;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Tests\TestCase;

final class PolicyTest extends TestCase
{
    /**
     * A policy collects all check results into one report.
     */
    public function test_aggregates_checks(): void
    {
        $this->configurePolicy();

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Body is fine.', 'violations' => []],
            ['label' => 'safe', 'reason' => 'Title is fine.'],
        ]);

        $report = AiModerator::policy('post')
            ->input(['body' => 'Body', 'title' => 'Title'])
            ->run();

        $this->assertTrue($report->passed());
        $this->assertCount(2, $report->results());
        $this->assertSame(
            $report->results()['post_body']->meta()->traceId,
            $report->results()['post_title']->meta()->traceId,
        );
    }

    /**
     * One check rejection makes the report rejected.
     */
    public function test_rejection_is_reported(): void
    {
        $this->configurePolicy();

        ModerationAgent::fake([
            [
                'verdict' => 'reject',
                'reason' => 'Body is bad.',
                'violations' => [['code' => 'bad', 'message' => 'Bad body']],
            ],
            ['label' => 'safe', 'reason' => 'Title is fine.'],
        ]);

        $report = AiModerator::policy('post')
            ->input(['body' => 'Body', 'title' => 'Title'])
            ->run();

        $this->assertFalse($report->passed());
        $this->assertTrue($report->rejected());
        $this->assertSame('Body is bad.', $report->messages()['post_body']);
        $this->assertArrayHasKey('post_body', $report->violations());
    }

    /**
     * stop_on_reject stops the run after the first rejection.
     */
    public function test_stop_on_reject(): void
    {
        $this->configurePolicy(['stop_on_reject' => true]);

        ModerationAgent::fake([
            [
                'verdict' => 'reject',
                'reason' => 'Body is bad.',
                'violations' => [['code' => 'bad', 'message' => 'Bad body']],
            ],
            ['label' => 'safe', 'reason' => 'Title is fine.'],
        ]);

        $report = AiModerator::policy('post')
            ->input(['body' => 'Body', 'title' => 'Title'])
            ->run();

        $this->assertCount(1, $report->results());
        ModerationAgent::assertPromptedTimes(1);
    }

    /**
     * A disabled check is omitted from the report.
     */
    public function test_disabled_check_is_omitted(): void
    {
        $this->configurePolicy();
        $this->app['config']->set('ai-moderator.checks.post_title.enabled', false);

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Body is fine.', 'violations' => []],
        ]);

        $report = AiModerator::policy('post')
            ->input(['body' => 'Body', 'title' => 'Title'])
            ->run();

        $this->assertCount(1, $report->results());
        $this->assertArrayNotHasKey('post_title', $report->results());
    }

    /**
     * An undecided check makes the report undecided.
     */
    public function test_undecided_check_makes_report_undecided(): void
    {
        $this->configurePolicy();

        ModerationAgent::fake([
            ['verdict' => 'reject', 'reason' => 'Broken.', 'violations' => []],
            ['label' => 'safe', 'reason' => 'Title is fine.'],
        ]);

        $report = AiModerator::policy('post')
            ->input(['body' => 'Body', 'title' => 'Title'])
            ->run();

        $this->assertNull($report->passed());
        $this->assertTrue($report->undecided());
    }

    private function configurePolicy(array $overrides = []): void
    {
        $this->setCheck('post_body', [
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 1000],
                ],
            ],
            'instructions' => 'Reject bad content.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true, 'description' => 'Bad content'],
            ],
        ]);

        $this->setCheck('post_title', [
            'kind' => 'classify',
            'input' => [
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Title', 'max_chars' => 300],
                ],
            ],
            'instructions' => 'Classify the title.',
            'labels' => ['safe', 'adult'],
            'decision' => ['reject_on' => ['adult'], 'severity' => 'high'],
        ]);

        $this->setPolicy('post', array_replace([
            'checks' => ['post_body', 'post_title'],
            'stop_on_reject' => false,
        ], $overrides));
    }
}
