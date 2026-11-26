<?php

namespace Goodport\AiModerator\Tests\Feature;

use Goodport\AiJournal\Models\AiInvocation;
use Goodport\AiJournal\Models\AiInvocationStep;
use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Tests\TestCase;

final class JournalIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-journal.enabled', true);
        $app['config']->set('ai-moderator.journal.replay.enabled', true);
        $app['config']->set('ai-moderator.journal.replay.ttl', 3600);
    }

    /**
     * A repeated moderation call reuses the journaled response by default.
     */
    public function test_replay_is_enabled_by_default(): void
    {
        $this->configureVerdictCheck();

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ])->preventStrayPrompts();

        $first = AiModerator::check('post_body')->input(['body' => 'Hello'])->run();
        $second = AiModerator::check('post_body')->input(['body' => 'Hello'])->run();

        $this->assertTrue($first->passed());
        $this->assertTrue($second->passed());
        $this->assertSame(2, AiInvocation::query()->count());
        $this->assertTrue(AiInvocationStep::query()->where('status', 'replayed')->exists());
    }

    /**
     * A check from always_fresh_checks always makes a new provider call.
     */
    public function test_always_fresh_check_skips_replay(): void
    {
        $this->configureVerdictCheck();
        $this->app['config']->set('ai-moderator.journal.replay.always_fresh_checks', ['post_body']);

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'First.', 'violations' => []],
            ['verdict' => 'approve', 'reason' => 'Second.', 'violations' => []],
        ]);

        AiModerator::check('post_body')->input(['body' => 'Hello'])->run();
        AiModerator::check('post_body')->input(['body' => 'Hello'])->run();

        $this->assertFalse(AiInvocationStep::query()->where('status', 'replayed')->exists());
        $this->assertSame(2, AiInvocationStep::query()->where('status', 'succeeded')->count());
    }

    /**
     * A replayed response is returned before the rate limiter can wait.
     */
    public function test_replay_does_not_consume_rate_limit(): void
    {
        $this->app['config']->set('ai-resilience.limits.enabled', true);
        $this->app['config']->set('ai-resilience.limits.default', [
            'max_requests' => null,
            'per_seconds' => null,
            'min_interval_seconds' => 60,
            'mode' => 'fail',
            'max_wait_seconds' => 0,
        ]);
        $this->configureVerdictCheck();

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ])->preventStrayPrompts();

        AiModerator::check('post_body')->input(['body' => 'Hello'])->run();
        $result = AiModerator::check('post_body')->input(['body' => 'Hello'])->run();

        $this->assertTrue($result->passed());
        $this->assertTrue(AiInvocationStep::query()->where('status', 'replayed')->exists());
    }

    /**
     * The fresh() call bypasses a stored response for one run.
     */
    public function test_fresh_call_bypasses_replay(): void
    {
        $this->configureVerdictCheck();

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'First.', 'violations' => []],
            ['verdict' => 'approve', 'reason' => 'Second.', 'violations' => []],
        ]);

        AiModerator::check('post_body')->input(['body' => 'Hello'])->run();
        AiModerator::check('post_body')->input(['body' => 'Hello'])->fresh()->run();

        $this->assertFalse(AiInvocationStep::query()->where('status', 'replayed')->exists());
        $this->assertSame(2, AiInvocationStep::query()->where('status', 'succeeded')->count());
    }

    /**
     * The two passes use separate journal sources for replay.
     */
    public function test_second_passes_replay_their_own_sources(): void
    {
        $this->configureVerdictCheck([
            'second_pass' => [
                'enabled' => true,
                'trigger' => 'always',
                'merge' => 'second_decides',
            ],
        ]);

        ModerationAgent::fake([
            ['verdict' => 'reject', 'reason' => 'First pass.', 'violations' => [['code' => 'bad', 'message' => 'Bad']]],
            ['verdict' => 'reject', 'reason' => 'Second pass.', 'violations' => [['code' => 'bad', 'message' => 'Bad']]],
        ])->preventStrayPrompts();

        AiModerator::check('post_body')->input(['body' => 'Hello'])->run();
        AiModerator::check('post_body')->input(['body' => 'Hello'])->run();

        $replayed = AiInvocationStep::query()->where('status', 'replayed')->with(['invocation', 'replayOf.invocation'])->get();

        $this->assertCount(2, $replayed);

        foreach ($replayed as $step) {
            $this->assertSame(
                $step->invocation->context['ai.pass'],
                $step->replayOf->invocation->context['ai.pass'],
            );
        }
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
