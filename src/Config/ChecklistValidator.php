<?php

namespace Goodport\AiModerator\Config;

final class ChecklistValidator
{
    public function __construct(
        private readonly SeverityValidator $severity = new SeverityValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function assert(string $key, array $config): void
    {
        $questions = $config['questions'] ?? null;

        if (! is_array($questions) || $questions === []) {
            throw InvalidConfigurationException::invalid($key, 'questions', 'must be a non-empty object.');
        }

        foreach ($questions as $questionKey => $question) {
            if (! is_string($questionKey) || $questionKey === '') {
                throw InvalidConfigurationException::invalid($key, 'questions', 'question keys must be non-empty strings.');
            }

            if (! is_array($question)) {
                throw InvalidConfigurationException::invalid($key, "questions.{$questionKey}", 'must be an object.');
            }

            if (! is_string($question['label'] ?? null) || $question['label'] === '') {
                throw InvalidConfigurationException::invalid($key, "questions.{$questionKey}.label", 'must be a non-empty string.');
            }

            $values = $question['values'] ?? ['yes', 'no'];

            if (! is_array($values) || $values === []) {
                throw InvalidConfigurationException::invalid($key, "questions.{$questionKey}.values", 'must be a non-empty array.');
            }

            if (count($values) !== count(array_unique($values))) {
                throw InvalidConfigurationException::invalid($key, "questions.{$questionKey}.values", 'must contain unique values.');
            }

            foreach ($values as $value) {
                if (! is_string($value) || $value === '') {
                    throw InvalidConfigurationException::invalid($key, "questions.{$questionKey}.values", 'must contain non-empty strings.');
                }
            }

            if (! is_string($question['expected'] ?? null) || ! in_array($question['expected'], $values, true)) {
                throw InvalidConfigurationException::invalid($key, "questions.{$questionKey}.expected", 'must be one of the allowed values.');
            }

            if (! is_bool($question['blocking'] ?? null)) {
                throw InvalidConfigurationException::invalid($key, "questions.{$questionKey}.blocking", 'must be a boolean.');
            }

            $this->severity->assert($key, $question, "questions.{$questionKey}.severity", 'medium');

            $unknown = array_diff(array_keys($question), ['label', 'values', 'expected', 'blocking', 'severity']);

            if ($unknown !== []) {
                throw InvalidConfigurationException::invalid($key, "questions.{$questionKey}.".array_key_first($unknown), 'is not allowed.');
            }
        }
    }
}
