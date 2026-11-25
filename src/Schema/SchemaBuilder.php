<?php

namespace Goodport\AiModerator\Schema;

use Goodport\AiModerator\Checks\CheckDefinition;
use Goodport\AiModerator\Checks\ChecklistCheckDefinition;
use Goodport\AiModerator\Checks\ClassifyCheckDefinition;
use Goodport\AiModerator\Checks\VerdictCheckDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

final class SchemaBuilder
{
    /**
     * @return array<string, Type>
     */
    public function build(CheckDefinition $check, JsonSchema $schema): array
    {
        $properties = match (true) {
            $check instanceof VerdictCheckDefinition => $this->verdictProperties($check, $schema),
            $check instanceof ChecklistCheckDefinition => $this->checklistProperties($check, $schema),
            $check instanceof ClassifyCheckDefinition => $this->classifyProperties($check, $schema),
            default => [],
        };

        $properties['reason'] = $schema->string()->required();

        if ($check->config->detailsSchema !== null) {
            $details = ($check->config->detailsSchema)($schema);

            $properties['details'] = $schema->object($details)
                ->withoutAdditionalProperties()
                ->required();
        }

        return $properties;
    }

    /**
     * @return array<string, Type>
     */
    private function verdictProperties(VerdictCheckDefinition $check, JsonSchema $schema): array
    {
        return [
            'verdict' => $schema->string()->enum(['approve', 'reject'])->required(),
            'violations' => $schema->array()
                ->items(
                    $schema->object([
                        'code' => $schema->string()->enum(array_keys($check->violations))->required(),
                        'message' => $schema->string()->required(),
                    ])->withoutAdditionalProperties()
                )
                ->required(),
        ];
    }

    /**
     * @return array<string, Type>
     */
    private function checklistProperties(ChecklistCheckDefinition $check, JsonSchema $schema): array
    {
        $answers = [];

        foreach ($check->questions as $question) {
            $answers[$question->key] = $schema->string()
                ->enum($question->values)
                ->required();
        }

        return [
            'answers' => $schema->object($answers)
                ->withoutAdditionalProperties()
                ->required(),
        ];
    }

    /**
     * @return array<string, Type>
     */
    private function classifyProperties(ClassifyCheckDefinition $check, JsonSchema $schema): array
    {
        return [
            'label' => $schema->string()->enum($check->labels)->required(),
        ];
    }
}
