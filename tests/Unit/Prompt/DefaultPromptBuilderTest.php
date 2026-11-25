<?php

namespace Goodport\AiModerator\Tests\Unit\Prompt;

use Goodport\AiModerator\Config\CheckFactory;
use Goodport\AiModerator\Input\NormalizedInput;
use Goodport\AiModerator\Prompt\DefaultPromptBuilder;
use Goodport\AiModerator\Prompt\PromptContext;
use Goodport\AiModerator\Tests\TestCase;
use Laravel\Ai\Files\Image;

final class DefaultPromptBuilderTest extends TestCase
{
    /**
     * The verdict system prompt contains the role, context, rules, codes, and language.
     */
    public function test_builds_verdict_prompt(): void
    {
        $this->setProfile('reviews', ['context' => 'A platform for reviews.']);
        $this->setCheck('post_body', [
            'profile' => 'reviews',
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
        ]);

        $check = app(CheckFactory::class)->make('post_body');
        $context = new PromptContext($check, new NormalizedInput(['body' => 'Hello'], []), 1, false, 'ru');

        $system = (new DefaultPromptBuilder)->system($context);

        $this->assertStringContainsString('A platform for reviews.', $system);
        $this->assertStringContainsString('Reject bad content.', $system);
        $this->assertStringContainsString('`bad` — Bad content (severity: high)', $system);
        $this->assertStringContainsString('Return a JSON object', $system);
        $this->assertStringEndsWith('in Russian.', trim($system));
    }

    /**
     * The second pass receives a second-opinion note.
     */
    public function test_second_pass_has_a_note(): void
    {
        $this->setCheck('post_body', [
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
        ]);

        $check = app(CheckFactory::class)->make('post_body');
        $context = new PromptContext($check, new NormalizedInput(['body' => 'Hello'], []), 2, true, 'en');

        $system = (new DefaultPromptBuilder)->system($context);

        $this->assertStringContainsString('second-opinion review', $system);
    }

    /**
     * The user prompt contains text fields and image labels.
     */
    public function test_builds_user_prompt_with_images(): void
    {
        $this->setCheck('post_cover', [
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 100],
                    'cover' => ['type' => 'image', 'label' => 'Cover'],
                ],
            ],
            'instructions' => 'Review the content.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true],
            ],
        ]);

        $check = app(CheckFactory::class)->make('post_cover');
        $image = Image::fromUrl('https://example.com/cover.jpg');
        $context = new PromptContext($check, new NormalizedInput(['body' => 'Hello'], ['cover' => $image]), 1, false, 'en');

        $builder = new DefaultPromptBuilder;

        $this->assertSame("Body:\nHello\n\nCover: attached image.", $builder->user($context));
        $this->assertSame([$image], $builder->attachments($context));
    }

    /**
     * A missing optional field does not break the prompt.
     */
    public function test_skips_missing_optional_fields(): void
    {
        $this->setCheck('post_body', [
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 100],
                    'note' => ['type' => 'text', 'label' => 'Note', 'max_chars' => 100, 'required' => false],
                    'cover' => ['type' => 'image', 'label' => 'Cover', 'required' => false],
                ],
            ],
            'instructions' => 'Review the content.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true],
            ],
        ]);

        $check = app(CheckFactory::class)->make('post_body');
        $context = new PromptContext($check, new NormalizedInput(['body' => 'Hello'], []), 1, false, 'en');

        $this->assertSame("Body:\nHello", (new DefaultPromptBuilder)->user($context));
    }

    /**
     * Checklist questions are sent without expected answers.
     */
    public function test_checklist_prompt_does_not_leak_expected_answers(): void
    {
        $this->setCheck('review_questions', [
            'kind' => 'checklist',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Review', 'max_chars' => 100],
                ],
            ],
            'instructions' => 'Answer the questions.',
            'questions' => [
                'insults' => [
                    'label' => 'Does it contain insults?',
                    'values' => ['yes', 'no'],
                    'expected' => 'no',
                    'blocking' => true,
                    'severity' => 'high',
                ],
            ],
        ]);

        $check = app(CheckFactory::class)->make('review_questions');
        $context = new PromptContext($check, new NormalizedInput(['body' => 'Hello'], []), 1, false, 'en');

        $system = (new DefaultPromptBuilder)->system($context);

        $this->assertStringContainsString('Does it contain insults?', $system);
        $this->assertStringContainsString('Allowed values: yes, no.', $system);
        $this->assertStringNotContainsString('expected', $system);
    }

    /**
     * Classification lists the labels.
     */
    public function test_builds_classify_prompt(): void
    {
        $this->setCheck('age_rating', [
            'kind' => 'classify',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 100],
                ],
            ],
            'instructions' => 'Classify the content.',
            'labels' => ['safe', 'adult'],
            'decision' => ['reject_on' => ['adult']],
        ]);

        $check = app(CheckFactory::class)->make('age_rating');
        $context = new PromptContext($check, new NormalizedInput(['body' => 'Hello'], []), 1, false, 'en');

        $system = (new DefaultPromptBuilder)->system($context);

        $this->assertStringContainsString('`safe`', $system);
        $this->assertStringContainsString('`adult`', $system);
        $this->assertStringContainsString('Choose exactly one label.', $system);
    }
}
