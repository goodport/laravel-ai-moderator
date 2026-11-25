<?php

namespace Goodport\AiModerator\Config;

use Goodport\AiModerator\Input\InputType;
use Goodport\AiModerator\Input\TruncationStrategy;

final class InputFieldValidator
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function assert(string $key, array $config): void
    {
        $fields = $config['input']['fields'] ?? null;

        if (! is_array($fields) || $fields === []) {
            throw InvalidConfigurationException::invalid($key, 'input.fields', 'must be a non-empty object.');
        }

        foreach ($fields as $name => $field) {
            if (! is_string($name) || $name === '') {
                throw InvalidConfigurationException::invalid($key, 'input.fields', 'field names must be non-empty strings.');
            }

            if (! is_array($field)) {
                throw InvalidConfigurationException::invalid($key, "input.fields.{$name}", 'must be an object.');
            }

            $type = $field['type'] ?? null;

            if (! is_string($type) || ($inputType = InputType::tryFrom($type)) === null) {
                throw InvalidConfigurationException::invalid($key, "input.fields.{$name}.type", 'must be "text" or "image".');
            }

            if (! is_string($field['label'] ?? null) || $field['label'] === '') {
                throw InvalidConfigurationException::invalid($key, "input.fields.{$name}.label", 'must be a non-empty string.');
            }

            if (isset($field['required']) && ! is_bool($field['required'])) {
                throw InvalidConfigurationException::invalid($key, "input.fields.{$name}.required", 'must be a boolean.');
            }

            if ($inputType === InputType::Image) {
                $this->assertImageField($key, $name, $field);

                continue;
            }

            $this->assertTextField($key, $name, $field);
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function assertImageField(string $key, string $name, array $field): void
    {
        if (isset($field['max_chars']) || isset($field['strategy'])) {
            throw InvalidConfigurationException::invalid($key, "input.fields.{$name}", 'image fields cannot define max_chars or strategy.');
        }

        $unknown = array_diff(array_keys($field), ['type', 'label', 'required']);

        if ($unknown !== []) {
            throw InvalidConfigurationException::invalid($key, "input.fields.{$name}.".array_key_first($unknown), 'is not allowed.');
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function assertTextField(string $key, string $name, array $field): void
    {
        if (! is_int($field['max_chars'] ?? null) || $field['max_chars'] <= 0) {
            throw InvalidConfigurationException::invalid($key, "input.fields.{$name}.max_chars", 'must be a positive integer.');
        }

        $strategy = $field['strategy'] ?? TruncationStrategy::Head->value;

        if (! is_string($strategy) || TruncationStrategy::tryFrom($strategy) === null) {
            throw InvalidConfigurationException::invalid($key, "input.fields.{$name}.strategy", 'must be head, head_tail, or none.');
        }

        $unknown = array_diff(array_keys($field), ['type', 'label', 'required', 'max_chars', 'strategy']);

        if ($unknown !== []) {
            throw InvalidConfigurationException::invalid($key, "input.fields.{$name}.".array_key_first($unknown), 'is not allowed.');
        }
    }
}
