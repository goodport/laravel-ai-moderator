<?php

namespace Goodport\AiModerator\Config;

use Goodport\AiModerator\Checks\CheckKind;

final class ConfigValidator
{
    public function __construct(
        private readonly InputFieldValidator $fields = new InputFieldValidator,
        private readonly RuntimeConfigValidator $runtime = new RuntimeConfigValidator,
        private readonly VerdictValidator $verdict = new VerdictValidator,
        private readonly ChecklistValidator $checklist = new ChecklistValidator,
        private readonly ClassifyValidator $classify = new ClassifyValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function assert(string $key, array $config): void
    {
        $kind = $this->kind($key, $config);

        $this->assertCommon($key, $config);
        $this->assertAllowedKeys($key, $config, $kind);
        $this->fields->assert($key, $config);
        $this->runtime->assert($key, $config);

        match ($kind) {
            CheckKind::Verdict => $this->verdict->assert($key, $config),
            CheckKind::Checklist => $this->checklist->assert($key, $config),
            CheckKind::Classify => $this->classify->assert($key, $config),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function kind(string $key, array $config): CheckKind
    {
        $kind = $config['kind'] ?? null;

        if (! is_string($kind)) {
            $this->fail($key, 'kind', 'must be one of: verdict, checklist, classify.');
        }

        $enum = CheckKind::tryFrom($kind);

        if ($enum === null) {
            $this->fail($key, 'kind', 'must be one of: verdict, checklist, classify.');
        }

        return $enum;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertCommon(string $key, array $config): void
    {
        $this->assertNonEmptyString($key, $config, 'instructions');
        $this->assertNullableString($key, $config, 'language');
        $this->assertNullableString($key, $config, 'context');
        $this->assertNonEmptyString($key, $config, 'role');
        $this->assertNullableString($key, $config, 'provider');
        $this->assertNullableString($key, $config, 'model');

        if (isset($config['timeout']) && (! is_int($config['timeout']) || $config['timeout'] <= 0)) {
            $this->fail($key, 'timeout', 'must be a positive integer or null.');
        }

        if (! is_int($config['reason_max_chars'] ?? null) || $config['reason_max_chars'] <= 0) {
            $this->fail($key, 'reason_max_chars', 'must be a positive integer.');
        }

        if (! in_array($config['on_error'] ?? null, ['undecided', 'throw'], true)) {
            $this->fail($key, 'on_error', 'must be "undecided" or "throw".');
        }

        if (isset($config['enabled']) && ! is_bool($config['enabled'])) {
            $this->fail($key, 'enabled', 'must be a boolean.');
        }

        if (isset($config['strict']) && ! is_bool($config['strict'])) {
            $this->fail($key, 'strict', 'must be a boolean.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertAllowedKeys(string $key, array $config, CheckKind $kind): void
    {
        $allowed = [
            'kind', 'enabled', 'input', 'instructions', 'language', 'context', 'role',
            'provider', 'model', 'strict', 'timeout', 'middleware', 'agent', 'second_pass',
            'reason_max_chars', 'on_error', 'prompt_builder', 'decision_rule', 'details_schema',
        ];

        $allowed = match ($kind) {
            CheckKind::Verdict => [...$allowed, 'violations'],
            CheckKind::Checklist => [...$allowed, 'questions'],
            CheckKind::Classify => [...$allowed, 'labels', 'decision'],
        };

        $unknown = array_diff(array_keys($config), $allowed);

        if ($unknown !== []) {
            $this->fail($key, (string) array_key_first($unknown), 'is not allowed for this check kind.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertNonEmptyString(string $key, array $config, string $field): void
    {
        $value = $config[$field] ?? null;

        if (! is_string($value) || $value === '') {
            $this->fail($key, $field, 'must be a non-empty string.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertNullableString(string $key, array $config, string $field): void
    {
        $value = $config[$field] ?? null;

        if ($value !== null && (! is_string($value) || $value === '')) {
            $this->fail($key, $field, 'must be a non-empty string or null.');
        }
    }

    private function fail(string $key, string $field, string $message): never
    {
        throw InvalidConfigurationException::invalid($key, $field, $message);
    }
}
