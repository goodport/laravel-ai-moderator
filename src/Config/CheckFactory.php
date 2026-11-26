<?php

namespace Goodport\AiModerator\Config;

use Closure;
use Goodport\AiModerator\Checks\Check;
use Goodport\AiModerator\Checks\CheckConfig;
use Goodport\AiModerator\Checks\CheckDefinition;
use Goodport\AiModerator\Checks\CheckKind;
use Goodport\AiModerator\Checks\ChecklistCheckDefinition;
use Goodport\AiModerator\Checks\ClassifyCheckDefinition;
use Goodport\AiModerator\Checks\FailureMode;
use Goodport\AiModerator\Checks\QuestionDefinition;
use Goodport\AiModerator\Checks\SecondPassConfig;
use Goodport\AiModerator\Checks\Severity;
use Goodport\AiModerator\Checks\VerdictCheckDefinition;
use Goodport\AiModerator\Checks\ViolationDefinition;
use Goodport\AiModerator\Decision\MergeStrategy;
use Goodport\AiModerator\Decision\SecondPassTrigger;
use Goodport\AiModerator\Input\InputField;
use Goodport\AiModerator\Input\InputType;
use Goodport\AiModerator\Input\TruncationStrategy;
use Illuminate\Contracts\Config\Repository;

final class CheckFactory
{
    public function __construct(
        private readonly Repository $config,
        private readonly ConfigValidator $validator,
        private readonly ConfigMerger $merger,
    ) {}

    public function make(string $key): CheckDefinition
    {
        $entry = $this->config->get("ai-moderator.checks.{$key}");

        if ($entry === null) {
            throw InvalidConfigurationException::unknownCheck($key);
        }

        if (is_string($entry)) {
            return $this->fromClass($key, $entry);
        }

        if (! is_array($entry)) {
            throw InvalidConfigurationException::invalid($key, 'checks', 'must be an array or a class name.');
        }

        $config = $this->merger->merge($key, $entry);

        $this->validator->assert($key, $config);

        return $this->build($key, $config);
    }

    private function fromClass(string $key, string $class): CheckDefinition
    {
        $check = app($class);

        if (! $check instanceof Check) {
            throw InvalidConfigurationException::invalid($key, 'checks', 'must implement Check.');
        }

        $definition = $check->definition();

        if ($definition->key() !== $key) {
            throw InvalidConfigurationException::invalid(
                $key,
                'checks',
                "class definition uses key [{$definition->key()}].",
            );
        }

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function build(string $key, array $config): CheckDefinition
    {
        $checkConfig = new CheckConfig(
            key: $key,
            kind: CheckKind::from($config['kind']),
            enabled: $config['enabled'],
            fields: $this->fields($config),
            instructions: $config['instructions'],
            context: $config['context'],
            language: $this->language($config['language']),
            role: $config['role'],
            provider: $config['provider'],
            model: $config['model'],
            strict: $config['strict'],
            timeout: $config['timeout'],
            middleware: $config['middleware'],
            agent: $config['agent'] ?? null,
            secondPass: $this->secondPass($config['second_pass']),
            reasonMaxChars: $config['reason_max_chars'],
            onError: FailureMode::from($config['on_error']),
            promptBuilder: $config['prompt_builder'] ?? null,
            decisionRule: $config['decision_rule'] ?? null,
            detailsSchema: $this->detailsSchema($config['details_schema'] ?? null),
        );

        return match ($checkConfig->kind) {
            CheckKind::Verdict => new VerdictCheckDefinition($checkConfig, $this->violations($config)),
            CheckKind::Checklist => new ChecklistCheckDefinition($checkConfig, $this->questions($config)),
            CheckKind::Classify => new ClassifyCheckDefinition(
                $checkConfig,
                $this->labels($config),
                $config['decision']['reject_on'],
                Severity::from($config['decision']['severity']),
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, InputField>
     */
    private function fields(array $config): array
    {
        $fields = [];

        foreach ($config['input']['fields'] as $name => $field) {
            $type = InputType::from($field['type']);

            $fields[$name] = new InputField(
                name: $name,
                type: $type,
                label: $field['label'],
                required: $field['required'] ?? true,
                maxChars: $type === InputType::Text ? $field['max_chars'] : 0,
                strategy: $type === InputType::Text
                    ? TruncationStrategy::from($field['strategy'] ?? TruncationStrategy::Head->value)
                    : TruncationStrategy::None,
            );
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, ViolationDefinition>
     */
    private function violations(array $config): array
    {
        $violations = [];

        foreach ($config['violations'] as $code => $violation) {
            $violations[$code] = new ViolationDefinition(
                code: $code,
                severity: Severity::from($violation['severity']),
                blocking: $violation['blocking'],
                description: $violation['description'] ?? null,
            );
        }

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, QuestionDefinition>
     */
    private function questions(array $config): array
    {
        $questions = [];

        foreach ($config['questions'] as $key => $question) {
            $questions[$key] = new QuestionDefinition(
                key: $key,
                label: $question['label'],
                values: $question['values'] ?? ['yes', 'no'],
                expected: $question['expected'],
                blocking: $question['blocking'],
                severity: Severity::from($question['severity'] ?? Severity::Medium->value),
            );
        }

        return $questions;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, string>
     */
    private function labels(array $config): array
    {
        return $config['labels'];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function secondPass(array $config): SecondPassConfig
    {
        return new SecondPassConfig(
            enabled: $config['enabled'] ?? false,
            trigger: SecondPassTrigger::from($config['trigger'] ?? SecondPassTrigger::OnReject->value),
            merge: MergeStrategy::from($config['merge'] ?? MergeStrategy::ApproveWins->value),
            provider: $config['provider'] ?? null,
            model: $config['model'] ?? null,
            timeout: $config['timeout'] ?? null,
            agent: $config['agent'] ?? null,
        );
    }

    private function language(?string $language): ?string
    {
        return $language === null || $language === 'auto' ? null : $language;
    }

    private function detailsSchema(mixed $schema): ?Closure
    {
        if ($schema === null) {
            return null;
        }

        return $schema instanceof Closure ? $schema : Closure::fromCallable($schema);
    }
}
