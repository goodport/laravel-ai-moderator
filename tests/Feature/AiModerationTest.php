<?php

namespace Goodport\AiModerator\Tests\Feature;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\AiModeration;
use Goodport\AiModerator\Models\AiModerationAudit;
use Goodport\AiModerator\Tests\Fixtures\AuditSubject;
use Goodport\AiModerator\Tests\Fixtures\IntCheck;
use Goodport\AiModerator\Tests\Fixtures\PostCheck;
use Goodport\AiModerator\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\App;
use Laravel\Ai\Prompts\AgentPrompt;

final class AiModerationTest extends TestCase
{
    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    /**
     * The text convenience method returns the complete result.
     */
    public function test_text_returns_result(): void
    {
        $this->configureTextCheck();
        ModerationAgent::fake([
            ['verdict' => 'reject', 'reason' => 'Not allowed.', 'violations' => [['code' => 'bad', 'message' => 'Bad']]],
        ]);

        $result = app(AiModeration::class)->text('post_body', 'Hello');

        $this->assertTrue($result->failed());
        $this->assertSame('Not allowed.', $result->reason());
    }

    /**
     * The image convenience method uses the image input field.
     */
    public function test_image_uses_image_field(): void
    {
        $this->configureCoverCheck();
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        $result = app(AiModeration::class)->image(PostCheck::Cover, 'https://example.com/cover.jpg');

        $this->assertTrue($result->passed());
        ModerationAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            return $prompt->attachments->count() === 1
                && str_contains($prompt->prompt, 'Cover: attached image.');
        });
    }

    /**
     * Checklist and classify accept arbitrary field arrays.
     */
    public function test_checklist_and_classify_accept_input_arrays(): void
    {
        $this->configureChecklistCheck();
        ModerationAgent::fake([
            ['answers' => ['insults' => 'no'], 'reason' => 'Fine.'],
            ['label' => 'safe', 'reason' => 'Safe.'],
        ]);

        $checklist = app(AiModeration::class)->checklist('post_questions', ['text' => 'Hello']);
        $classify = app(AiModeration::class)->classify('post_category', ['text' => 'Hello']);

        $this->assertTrue($checklist->passed());
        $this->assertSame(['insults' => 'no'], $checklist->answers());
        $this->assertTrue($classify->passed());
        $this->assertSame('safe', $classify->label());
    }

    /**
     * The universal run method accepts an arbitrary input array.
     */
    public function test_run_accepts_arbitrary_input(): void
    {
        $this->configureTextCheck();
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        $result = app(AiModeration::class)->run('post_body', ['text' => 'Hello']);

        $this->assertTrue($result->passed());
    }

    /**
     * Without a language argument, the package keeps its configured language.
     */
    public function test_language_is_not_overridden_when_omitted(): void
    {
        App::setLocale('ru');
        $this->configureTextCheck(['language' => 'en']);
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        app(AiModeration::class)->text('post_body', 'Hello');

        ModerationAgent::assertPrompted(
            fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), 'in English')
        );
    }

    /**
     * An explicit language overrides the configured language.
     */
    public function test_explicit_language_overrides_configured_language(): void
    {
        App::setLocale('ru');
        $this->configureTextCheck(['language' => 'ru']);
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        app(AiModeration::class)->text('post_body', 'Hello', language: 'en');

        ModerationAgent::assertPrompted(
            fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), 'in English')
        );
    }

    /**
     * An arbitrary multi-field input reaches the prompt.
     */
    public function test_multiple_input_fields_are_passed(): void
    {
        $this->setCheck('post_multi', [
            'kind' => 'checklist',
            'input' => [
                'fields' => [
                    'headline' => ['type' => 'text', 'label' => 'Headline', 'max_chars' => 100],
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 100],
                ],
            ],
            'instructions' => 'Review both fields.',
            'questions' => [
                'safe' => [
                    'label' => 'Is it safe?',
                    'values' => ['yes', 'no'],
                    'expected' => 'yes',
                    'blocking' => true,
                    'severity' => 'high',
                ],
            ],
        ]);

        ModerationAgent::fake([
            ['answers' => ['safe' => 'yes'], 'reason' => 'Fine.'],
        ]);

        app(AiModeration::class)->checklist('post_multi', [
            'headline' => 'A title',
            'body' => 'A body',
        ]);

        ModerationAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            return str_contains($prompt->prompt, 'Headline:
A title')
                && str_contains($prompt->prompt, 'Body:
A body');
        });
    }

    /**
     * Empty string and non-string enum keys are rejected.
     */
    public function test_invalid_check_keys_are_rejected(): void
    {
        foreach (['', IntCheck::Body] as $key) {
            try {
                app(AiModeration::class)->run($key, ['text' => 'Hello']);
                $this->fail('An invalid check key was accepted.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    /**
     * A morph map alias is used for audit actor and subject types.
     */
    public function test_morph_map_alias_is_used_in_audit_context(): void
    {
        Relation::morphMap(['audit-subject' => AuditSubject::class], false);
        $this->configureTextCheck();
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        $actor = new AuditSubject(['id' => 7]);
        $subject = new AuditSubject(['id' => 8]);

        app(AiModeration::class)->text('post_body', 'Hello', actor: $actor, subject: $subject);

        $audit = AiModerationAudit::query()->latest('id')->firstOrFail();

        $this->assertSame('audit-subject', $audit->actor_type);
        $this->assertSame('audit-subject', $audit->subject_type);
    }

    /**
     * The enum key and model audit context are resolved without application helpers.
     */
    public function test_enum_key_and_model_context(): void
    {
        $this->configureTextCheck();
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        $actor = new AuditSubject(['id' => 42]);
        $subject = new AuditSubject(['id' => 100]);

        app(AiModeration::class)->text(
            PostCheck::Body,
            'Hello',
            actor: $actor,
            subject: $subject,
            requestUrl: 'https://example.com/posts/100',
        );

        $audit = AiModerationAudit::query()->latest('id')->firstOrFail();

        $this->assertSame(AuditSubject::class, $audit->actor_type);
        $this->assertSame('42', $audit->actor_id);
        $this->assertSame(AuditSubject::class, $audit->subject_type);
        $this->assertSame('100', $audit->subject_id);
        $this->assertSame('https://example.com/posts/100', $audit->request_url);
    }

    /**
     * Omitting the actor leaves actor fields empty.
     */
    public function test_missing_actor_is_allowed(): void
    {
        $this->configureTextCheck();
        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        app(AiModeration::class)->text('post_body', 'Hello');

        $audit = AiModerationAudit::query()->latest('id')->firstOrFail();

        $this->assertNull($audit->actor_type);
        $this->assertNull($audit->actor_id);
        $this->assertNull($audit->subject_type);
        $this->assertNull($audit->subject_id);
        $this->assertNull($audit->request_url);
    }

    private function configureTextCheck(array $overrides = []): void
    {
        $this->setCheck('post_body', array_replace([
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'text' => ['type' => 'text', 'label' => 'Text', 'max_chars' => 1000],
                ],
            ],
            'instructions' => 'Reject bad content.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true],
            ],
        ], $overrides));
    }

    private function configureCoverCheck(): void
    {
        $this->setCheck('post_cover', [
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'image' => ['type' => 'image', 'label' => 'Cover'],
                ],
            ],
            'instructions' => 'Review the cover.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true],
            ],
        ]);
    }

    private function configureChecklistCheck(): void
    {
        $this->setCheck('post_questions', [
            'kind' => 'checklist',
            'input' => [
                'fields' => [
                    'text' => ['type' => 'text', 'label' => 'Text', 'max_chars' => 1000],
                ],
            ],
            'instructions' => 'Answer the questions.',
            'questions' => [
                'insults' => [
                    'label' => 'Insults?',
                    'values' => ['yes', 'no'],
                    'expected' => 'no',
                    'blocking' => true,
                    'severity' => 'high',
                ],
            ],
        ]);

        $this->setCheck('post_category', [
            'kind' => 'classify',
            'input' => [
                'fields' => [
                    'text' => ['type' => 'text', 'label' => 'Text', 'max_chars' => 1000],
                ],
            ],
            'instructions' => 'Choose a label.',
            'labels' => ['safe', 'adult'],
            'decision' => ['reject_on' => ['adult'], 'severity' => 'high'],
        ]);
    }
}
