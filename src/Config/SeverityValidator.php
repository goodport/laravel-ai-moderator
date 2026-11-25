<?php

namespace Goodport\AiModerator\Config;

final class SeverityValidator
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function assert(string $key, array $config, string $field, string $default = 'high'): void
    {
        $severity = $config['severity'] ?? $default;

        if (! is_string($severity) || ! in_array($severity, ['low', 'medium', 'high'], true)) {
            throw InvalidConfigurationException::invalid($key, $field, 'must be low, medium, or high.');
        }
    }
}
