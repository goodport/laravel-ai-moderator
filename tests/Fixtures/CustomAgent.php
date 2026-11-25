<?php

namespace Goodport\AiModerator\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

final class CustomAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'Custom agent instructions.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'verdict' => $schema->string()->enum(['approve', 'reject'])->required(),
            'reason' => $schema->string()->required(),
            'violations' => $schema->array()
                ->items(
                    $schema->object([
                        'code' => $schema->string()->required(),
                        'message' => $schema->string()->required(),
                    ])->withoutAdditionalProperties()
                )
                ->required(),
        ];
    }
}
