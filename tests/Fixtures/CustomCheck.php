<?php

namespace Goodport\AiModerator\Tests\Fixtures;

use Goodport\AiModerator\Checks\Check;
use Goodport\AiModerator\Checks\CheckConfig;
use Goodport\AiModerator\Checks\CheckDefinition;
use Goodport\AiModerator\Checks\CheckKind;
use Goodport\AiModerator\Checks\FailureMode;
use Goodport\AiModerator\Checks\Severity;
use Goodport\AiModerator\Checks\VerdictCheckDefinition;
use Goodport\AiModerator\Checks\ViolationDefinition;
use Goodport\AiModerator\Input\InputField;
use Goodport\AiModerator\Input\InputType;
use Goodport\AiModerator\Input\TruncationStrategy;
use Illuminate\Contracts\JsonSchema\JsonSchema;

final class CustomCheck implements Check
{
    public function definition(): CheckDefinition
    {
        $config = new CheckConfig(
            key: 'custom',
            kind: CheckKind::Verdict,
            enabled: true,
            fields: [
                'body' => new InputField('body', InputType::Text, 'Body', true, 100, TruncationStrategy::Head),
            ],
            instructions: 'Ignored custom instructions.',
            context: 'Custom context.',
            language: null,
            role: 'Custom role.',
            provider: 'openai',
            model: 'gpt-test',
            strict: false,
            timeout: 60,
            middleware: [],
            agent: null,
            secondPass: null,
            reasonMaxChars: 100,
            onError: FailureMode::Undecided,
            promptBuilder: CustomPromptBuilder::class,
            decisionRule: CustomRule::class,
            detailsSchema: fn (JsonSchema $schema): array => ['score' => $schema->number()->required()],
        );

        return new VerdictCheckDefinition($config, [
            'bad' => new ViolationDefinition('bad', Severity::High, true, 'Bad content'),
        ]);
    }
}
