<?php

namespace Goodport\AiModerator\Config;

final class VerdictValidator
{
    public function __construct(
        private readonly SeverityValidator $severity = new SeverityValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function assert(string $key, array $config): void
    {
        $violations = $config['violations'] ?? null;

        if (! is_array($violations) || $violations === []) {
            throw InvalidConfigurationException::invalid($key, 'violations', 'must be a non-empty object.');
        }

        foreach ($violations as $code => $violation) {
            if (! is_string($code) || $code === '') {
                throw InvalidConfigurationException::invalid($key, 'violations', 'violation codes must be non-empty strings.');
            }

            if (! is_array($violation)) {
                throw InvalidConfigurationException::invalid($key, "violations.{$code}", 'must be an object.');
            }

            if (! isset($violation['severity'])) {
                throw InvalidConfigurationException::invalid($key, "violations.{$code}.severity", 'is required.');
            }

            $this->severity->assert($key, $violation, "violations.{$code}.severity");

            if (! is_bool($violation['blocking'] ?? null)) {
                throw InvalidConfigurationException::invalid($key, "violations.{$code}.blocking", 'must be a boolean.');
            }

            if (isset($violation['description']) && ! is_string($violation['description'])) {
                throw InvalidConfigurationException::invalid($key, "violations.{$code}.description", 'must be a string or null.');
            }

            $unknown = array_diff(array_keys($violation), ['severity', 'blocking', 'description']);

            if ($unknown !== []) {
                throw InvalidConfigurationException::invalid($key, "violations.{$code}.".array_key_first($unknown), 'is not allowed.');
            }
        }
    }
}
