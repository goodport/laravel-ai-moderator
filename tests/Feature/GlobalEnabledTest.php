<?php

namespace Goodport\AiModerator\Tests\Feature;

use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\Audit\ModerationAuditContext;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Models\AiModerationAudit;
use Goodport\AiModerator\Tests\TestCase;

final class GlobalEnabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-journal.enabled', true);
    }

    /**
     * The global switch wins over per-check and per-policy settings.
     */
    public function test_disabled_global_switch_skips_without_calls_or_audit(): void
    {
        $this->app['config']->set('ai-moderator.enabled', false);
        $this->configureVerdictCheck(['enabled' => true]);
        $this->setPolicy('post-policy', ['checks' => ['post_body']]);

        ModerationAgent::fake([])->preventStrayPrompts();

        $result = AiModerator::check('post_body')
            ->audit(new ModerationAuditContext(actorType: 'user', actorId: '42'))
            ->input(['body' => 'Hello'])
            ->run();

        $report = AiModerator::policy('post-policy')
            ->audit(new ModerationAuditContext(actorType: 'user', actorId: '42'))
            ->run();

        $this->assertTrue($result->skipped());
        $this->assertTrue($report->results()['post_body']->skipped());
        $this->assertSame(0, AiModerationAudit::query()->count());
        $this->assertSame(0, AiInvocation::query()->count());
    }

    /**
     * With the global switch enabled, the check keeps its normal behavior.
     */
    public function test_enabled_global_switch_keeps_normal_check_behavior(): void
    {
        $this->app['config']->set('ai-moderator.enabled', true);
        $this->configureVerdictCheck();

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        $result = AiModerator::check('post_body')->input(['body' => 'Hello'])->run();

        $this->assertTrue($result->passed());
        $this->assertSame(1, AiModerationAudit::query()->count());
        ModerationAgent::assertPromptedTimes(1);
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
                'bad' => ['severity' => 'high', 'blocking' => true],
            ],
        ], $overrides));
    }
}
