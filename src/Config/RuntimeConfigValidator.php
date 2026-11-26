<?php

namespace Goodport\AiModerator\Config;

use Goodport\AiModerator\Decision\DecisionRule;
use Goodport\AiModerator\Decision\MergeStrategy;
use Goodport\AiModerator\Decision\SecondPassTrigger;
use Goodport\AiModerator\Prompt\PromptBuilder;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;

final class RuntimeConfigValidator
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function assert(string $key, array $config): void
    {
        $this->assertMiddleware($key, $config);
        $this->assertAgent($key, $config, 'agent', 'agent');
        $this->assertSecondPass($key, $config);
        $this->assertPromptBuilder($key, $config);
        $this->assertDecisionRule($key, $config);

        if (isset($config['details_schema']) && ! is_callable($config['details_schema'])) {
            throw InvalidConfigurationException::invalid($key, 'details_schema', 'must be callable or null.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertMiddleware(string $key, array $config): void
    {
        $middleware = $config['middleware'] ?? [];

        if (! is_array($middleware)) {
            throw InvalidConfigurationException::invalid($key, 'middleware', 'must be an array.');
        }

        foreach ($middleware as $item) {
            if (is_string($item) && class_exists($item)) {
                continue;
            }

            if (is_object($item)) {
                continue;
            }

            throw InvalidConfigurationException::invalid($key, 'middleware', 'must contain class names or objects.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertAgent(string $key, array $config, string $field, string $path): void
    {
        $agent = $config[$field] ?? null;

        if ($agent === null) {
            return;
        }

        if (! is_string($agent) || ! class_exists($agent)) {
            throw InvalidConfigurationException::invalid($key, $path, 'must be an existing class name.');
        }

        if (
            ! is_subclass_of($agent, Agent::class)
            || ! is_subclass_of($agent, HasStructuredOutput::class)
            || ! is_subclass_of($agent, HasMiddleware::class)
        ) {
            throw InvalidConfigurationException::invalid(
                $key,
                $path,
                'must implement Agent, HasStructuredOutput, and HasMiddleware.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertSecondPass(string $key, array $config): void
    {
        $second = $config['second_pass'] ?? null;

        if ($second === null) {
            return;
        }

        if (! is_array($second)) {
            throw InvalidConfigurationException::invalid($key, 'second_pass', 'must be an object.');
        }

        if (isset($second['enabled']) && ! is_bool($second['enabled'])) {
            throw InvalidConfigurationException::invalid($key, 'second_pass.enabled', 'must be a boolean.');
        }

        if (isset($second['trigger']) && (! is_string($second['trigger']) || SecondPassTrigger::tryFrom($second['trigger']) === null)) {
            throw InvalidConfigurationException::invalid($key, 'second_pass.trigger', 'must be on_reject or always.');
        }

        if (isset($second['merge']) && (! is_string($second['merge']) || MergeStrategy::tryFrom($second['merge']) === null)) {
            throw InvalidConfigurationException::invalid($key, 'second_pass.merge', 'must be approve_wins, strict, or second_decides.');
        }

        $this->assertNullableString($key, $second, 'provider', 'second_pass.provider');
        $this->assertNullableString($key, $second, 'model', 'second_pass.model');

        if (isset($second['timeout']) && (! is_int($second['timeout']) || $second['timeout'] <= 0)) {
            throw InvalidConfigurationException::invalid($key, 'second_pass.timeout', 'must be a positive integer or null.');
        }

        $this->assertAgent($key, $second, 'agent', 'second_pass.agent');

        $unknown = array_diff(array_keys($second), ['enabled', 'trigger', 'merge', 'provider', 'model', 'timeout', 'agent']);

        if ($unknown !== []) {
            throw InvalidConfigurationException::invalid($key, 'second_pass.'.array_key_first($unknown), 'is not allowed.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertPromptBuilder(string $key, array $config): void
    {
        $builder = $config['prompt_builder'] ?? null;

        if ($builder === null) {
            return;
        }

        if (! is_string($builder) || ! class_exists($builder)) {
            throw InvalidConfigurationException::invalid($key, 'prompt_builder', 'must be an existing class name.');
        }

        if (! is_subclass_of($builder, PromptBuilder::class)) {
            throw InvalidConfigurationException::invalid($key, 'prompt_builder', 'must implement PromptBuilder.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertDecisionRule(string $key, array $config): void
    {
        $rule = $config['decision_rule'] ?? null;

        if ($rule === null) {
            return;
        }

        if (is_string($rule)) {
            if (! class_exists($rule)) {
                throw InvalidConfigurationException::invalid($key, 'decision_rule', 'must be an existing class name.');
            }

            if (! is_subclass_of($rule, DecisionRule::class)) {
                throw InvalidConfigurationException::invalid($key, 'decision_rule', 'must implement DecisionRule.');
            }

            return;
        }

        if (! $rule instanceof DecisionRule) {
            throw InvalidConfigurationException::invalid($key, 'decision_rule', 'must implement DecisionRule.');
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertNullableString(string $key, array $config, string $field, string $path): void
    {
        $value = $config[$field] ?? null;

        if ($value !== null && (! is_string($value) || $value === '')) {
            throw InvalidConfigurationException::invalid($key, $path, 'must be a non-empty string or null.');
        }
    }
}
