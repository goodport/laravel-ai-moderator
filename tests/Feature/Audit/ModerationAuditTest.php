<?php

namespace Goodport\AiModerator\Tests\Feature\Audit;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\Audit\ModerationAuditContext;
use Goodport\AiModerator\Audit\ModerationAuditRepository;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Models\AiModerationAudit;
use Goodport\AiModerator\Tests\TestCase;

final class ModerationAuditTest extends TestCase
{
    /**
     * The audit stores the final result and the explicit run context.
     */
    public function test_records_result_with_context(): void
    {
        $this->configureVerdictCheck();

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        AiModerator::check('post_body')
            ->audit(new ModerationAuditContext(
                actorType: 'user',
                actorId: '42',
                subjectType: 'post',
                subjectId: '100',
                requestUrl: 'https://example.com/posts/100',
            ))
            ->input(['body' => 'Hello'])
            ->run();

        $audit = AiModerationAudit::query()->latest('id')->firstOrFail();

        $this->assertSame('post_body', $audit->check);
        $this->assertSame('passed', $audit->outcome);
        $this->assertSame('Fine.', $audit->reason);
        $this->assertSame('user', $audit->actor_type);
        $this->assertSame('42', $audit->actor_id);
        $this->assertSame('post', $audit->subject_type);
        $this->assertSame('100', $audit->subject_id);
        $this->assertSame('https://example.com/posts/100', $audit->request_url);
        $this->assertNotNull($audit->invocation_id);
        $this->assertSame($audit->invocation_id, $audit->first_invocation_id);
        $this->assertNull($audit->second_invocation_id);
    }

    /**
     * A second pass is recorded separately from the selected final result.
     */
    public function test_records_both_passes(): void
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

        AiModerator::check('post_body')
            ->input(['body' => 'Maybe bad'])
            ->run();

        $audit = AiModerationAudit::query()->latest('id')->firstOrFail();

        $this->assertSame('passed', $audit->outcome);
        $this->assertSame(2, $audit->pass);
        $this->assertNotNull($audit->first_invocation_id);
        $this->assertNotNull($audit->second_invocation_id);
        $this->assertSame($audit->second_invocation_id, $audit->invocation_id);
        $this->assertNotSame($audit->first_invocation_id, $audit->second_invocation_id);
    }

    /**
     * Both passes are recorded even when the first pass is selected.
     */
    public function test_records_both_passes_when_first_is_selected(): void
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

        $audit = AiModerationAudit::query()->latest('id')->firstOrFail();

        $this->assertTrue($result->failed());
        $this->assertSame(1, $audit->pass);
        $this->assertNotNull($audit->first_invocation_id);
        $this->assertNotNull($audit->second_invocation_id);
        $this->assertSame($audit->first_invocation_id, $audit->invocation_id);
    }

    /**
     * An undecided result keeps its diagnostic error.
     */
    public function test_records_undecided_error(): void
    {
        $this->configureVerdictCheck();

        ModerationAgent::fake([
            ['verdict' => 'reject', 'reason' => 'Rejected.', 'violations' => []],
        ]);

        AiModerator::check('post_body')->input(['body' => 'Bad'])->run();

        $audit = AiModerationAudit::query()->latest('id')->firstOrFail();

        $this->assertSame('undecided', $audit->outcome);
        $this->assertNotNull($audit->error);
    }

    /**
     * A thrown failure still leaves an audit row.
     */
    public function test_records_exception_when_on_error_throws(): void
    {
        $this->configureVerdictCheck(['on_error' => 'throw']);

        ModerationAgent::fake([
            fn (): never => throw new \RuntimeException('Provider failed.'),
        ]);

        try {
            AiModerator::check('post_body')->input(['body' => 'Bad'])->run();
        } catch (\RuntimeException) {
            // Expected: the application requested a thrown failure.
        }

        $audit = AiModerationAudit::query()->latest('id')->firstOrFail();

        $this->assertSame('undecided', $audit->outcome);
        $this->assertStringContainsString('Provider failed.', $audit->error);
    }

    /**
     * A skipped check still has an audit row.
     */
    public function test_records_skipped_check(): void
    {
        $this->configureVerdictCheck(['enabled' => false]);

        AiModerator::check('post_body')->run();

        $audit = AiModerationAudit::query()->latest('id')->firstOrFail();

        $this->assertSame('skipped', $audit->outcome);
        $this->assertSame(0, $audit->pass);
        $this->assertNull($audit->invocation_id);
        $this->assertNull($audit->error);
    }

    /**
     * A policy sets its key in the audit context.
     */
    public function test_policy_is_recorded(): void
    {
        $this->configureVerdictCheck();
        $this->setPolicy('post-policy', ['checks' => ['post_body']]);

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        AiModerator::policy('post-policy')
            ->audit(new ModerationAuditContext(actorType: 'user', actorId: '42'))
            ->input(['body' => 'Hello'])
            ->run();

        $audit = AiModerationAudit::query()->latest('id')->firstOrFail();

        $this->assertSame('post-policy', $audit->policy);
        $this->assertSame('user', $audit->actor_type);
        $this->assertSame('42', $audit->actor_id);
    }

    /**
     * A disabled check in a policy also has a skipped audit row.
     */
    public function test_policy_records_skipped_check(): void
    {
        $this->configureVerdictCheck(['enabled' => false]);
        $this->setPolicy('post-policy', ['checks' => ['post_body']]);

        AiModerator::policy('post-policy')->input(['body' => 'Hello'])->run();

        $audit = AiModerationAudit::query()->latest('id')->firstOrFail();

        $this->assertSame('post-policy', $audit->policy);
        $this->assertSame('post_body', $audit->check);
        $this->assertSame('skipped', $audit->outcome);
    }

    /**
     * The read repository filters the audit page.
     */
    public function test_repository_filters_audits(): void
    {
        $this->configureVerdictCheck();

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
            ['verdict' => 'reject', 'reason' => 'Bad.', 'violations' => [['code' => 'bad', 'message' => 'Bad']]],
        ]);

        AiModerator::check('post_body')->input(['body' => 'Good'])->run();
        AiModerator::check('post_body')->audit(new ModerationAuditContext(actorType: 'user', actorId: '42'))->input(['body' => 'Bad'])->run();

        $page = app(ModerationAuditRepository::class)->getPage(['outcome' => 'rejected', 'actor_type' => 'user']);

        $this->assertSame(1, $page->total());
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
