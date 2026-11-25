<?php

namespace Goodport\AiModerator\Tests\Unit\Schema;

use Goodport\AiModerator\Config\CheckFactory;
use Goodport\AiModerator\Schema\SchemaBuilder;
use Goodport\AiModerator\Tests\TestCase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

final class SchemaBuilderTest extends TestCase
{
    /**
     * The verdict schema contains the decision enum and the code enum.
     */
    public function test_builds_verdict_schema(): void
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
                'ads' => ['severity' => 'medium', 'blocking' => false],
            ],
        ]);

        $check = app(CheckFactory::class)->make('post_body');
        $schema = new JsonSchemaTypeFactory;

        $properties = (new SchemaBuilder)->build($check, $schema);

        $this->assertSame(['approve', 'reject'], $properties['verdict']->toArray()['enum']);
        $this->assertSame(['bad', 'ads'], $properties['violations']->toArray()['items']['properties']['code']['enum']);
        $this->assertArrayHasKey('reason', $properties);
    }

    /**
     * The checklist schema requires every question.
     */
    public function test_builds_checklist_schema(): void
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
                    'label' => 'Insults?',
                    'values' => ['yes', 'no'],
                    'expected' => 'no',
                    'blocking' => true,
                ],
            ],
        ]);

        $check = app(CheckFactory::class)->make('review_questions');
        $schema = new JsonSchemaTypeFactory;

        $properties = (new SchemaBuilder)->build($check, $schema);

        $answers = $properties['answers']->toArray();

        $this->assertSame(['yes', 'no'], $answers['properties']['insults']['enum']);
        $this->assertSame(['insults'], $answers['required']);
    }

    /**
     * The classification schema contains the label enum.
     */
    public function test_builds_classify_schema(): void
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
        ]);

        $check = app(CheckFactory::class)->make('age_rating');
        $schema = new JsonSchemaTypeFactory;

        $properties = (new SchemaBuilder)->build($check, $schema);

        $this->assertSame(['safe', 'adult'], $properties['label']->toArray()['enum']);
    }
}
