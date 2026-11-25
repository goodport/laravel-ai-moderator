<?php

namespace Goodport\AiModerator\Config;

final class ClassifyValidator
{
    public function __construct(
        private readonly SeverityValidator $severity = new SeverityValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function assert(string $key, array $config): void
    {
        $labels = $config['labels'] ?? null;

        if (! is_array($labels) || $labels === []) {
            throw InvalidConfigurationException::invalid($key, 'labels', 'must be a non-empty array.');
        }

        if (count($labels) !== count(array_unique($labels))) {
            throw InvalidConfigurationException::invalid($key, 'labels', 'must contain unique labels.');
        }

        foreach ($labels as $label) {
            if (! is_string($label) || $label === '') {
                throw InvalidConfigurationException::invalid($key, 'labels', 'must contain non-empty strings.');
            }
        }

        $decision = $config['decision'] ?? null;

        if (! is_array($decision)) {
            throw InvalidConfigurationException::invalid($key, 'decision', 'must be an object.');
        }

        $rejectOn = $decision['reject_on'] ?? [];

        if (! is_array($rejectOn)) {
            throw InvalidConfigurationException::invalid($key, 'decision.reject_on', 'must be an array.');
        }

        foreach ($rejectOn as $label) {
            if (! is_string($label) || ! in_array($label, $labels, true)) {
                throw InvalidConfigurationException::invalid($key, 'decision.reject_on', 'must contain only declared labels.');
            }
        }

        $this->severity->assert($key, $decision, 'decision.severity', 'high');

        $unknown = array_diff(array_keys($decision), ['reject_on', 'severity']);

        if ($unknown !== []) {
            throw InvalidConfigurationException::invalid($key, 'decision.'.array_key_first($unknown), 'is not allowed.');
        }
    }
}
