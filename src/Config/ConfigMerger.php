<?php

namespace Goodport\AiModerator\Config;

use Goodport\AiModerator\Checks\CheckKind;
use Goodport\AiModerator\Checks\Severity;
use Illuminate\Contracts\Config\Repository;

final class ConfigMerger
{
    /**
     * Keys that belong to a check and cannot be inherited from defaults or a profile.
     *
     * @var array<int, string>
     */
    private const CHECK_ONLY = [
        'kind',
        'input',
        'instructions',
        'violations',
        'questions',
        'labels',
        'decision',
        'agent',
        'prompt_builder',
        'decision_rule',
        'details_schema',
    ];

    public function __construct(
        private readonly Repository $config,
        private readonly ModelResolver $models,
    ) {}

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    public function merge(string $key, array $entry): array
    {
        $defaults = $this->config->get('ai-moderator.defaults', []);

        if (! is_array($defaults)) {
            throw InvalidConfigurationException::invalid($key, 'defaults', 'must be an object.');
        }

        $profile = $this->profile($key, $entry['profile'] ?? null);

        unset($entry['profile']);

        $config = array_replace($defaults, $profile, $entry);

        foreach (self::CHECK_ONLY as $key) {
            if (array_key_exists($key, $entry)) {
                $config[$key] = $entry[$key];

                continue;
            }

            unset($config[$key]);
        }

        $config['language'] = $this->nearest([
            $defaults['language'] ?? null,
            $profile['language'] ?? null,
            $entry['language'] ?? null,
        ], ['auto']);

        $config['context'] = $this->nearest([
            $defaults['context'] ?? null,
            $profile['context'] ?? null,
            $entry['context'] ?? null,
        ]);

        $config['enabled'] = $entry['enabled'] ?? $profile['enabled'] ?? true;

        $config['middleware'] = $this->mergeMiddleware(
            $key,
            $defaults['middleware'] ?? [],
            $profile['middleware'] ?? [],
            $entry['middleware'] ?? [],
        );

        $config['second_pass'] = $this->mergeSecondPass(
            $key,
            $defaults['second_pass'] ?? [],
            $profile['second_pass'] ?? [],
            $entry['second_pass'] ?? [],
        );

        if (($config['kind'] ?? null) === CheckKind::Classify->value && is_array($config['decision'] ?? [])) {
            $config['decision'] = array_replace(
                ['reject_on' => [], 'severity' => Severity::High->value],
                $config['decision'] ?? [],
            );
        }

        $selection = $this->models->resolve($key, $defaults, $profile, $entry);
        $config['provider'] = $selection->provider;
        $config['model'] = $selection->model;
        $config['timeout'] = $selection->timeout;

        $secondPass = $this->models->resolveSecondPass(
            $key,
            $selection,
            $config['second_pass'],
        );

        $config['second_pass'] = array_replace($config['second_pass'], [
            'provider' => $secondPass->provider,
            'model' => $secondPass->model,
            'timeout' => $secondPass->timeout,
        ]);

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(string $key, mixed $profileName): array
    {
        if ($profileName === null) {
            return [];
        }

        if (! is_string($profileName)) {
            throw InvalidConfigurationException::invalid($key, 'profile', 'must be a string or null.');
        }

        $profile = $this->config->get("ai-moderator.profiles.{$profileName}");

        if ($profile === null) {
            throw InvalidConfigurationException::unknownProfile($profileName);
        }

        if (! is_array($profile)) {
            throw InvalidConfigurationException::invalid($key, 'profile', 'must reference an object profile.');
        }

        return $profile;
    }

    /**
     * Return the nearest configured value, skipping nulls and the provided ignored values.
     *
     * @param  array<int, mixed>  $values
     * @param  array<int, mixed>  $ignored
     */
    private function nearest(array $values, array $ignored = []): mixed
    {
        foreach (array_reverse($values) as $value) {
            if ($value === null || in_array($value, $ignored, true)) {
                continue;
            }

            return $value;
        }

        return null;
    }

    /**
     * @return array<int, mixed>
     */
    private function mergeMiddleware(string $key, mixed $defaults, mixed $profile, mixed $entry): array
    {
        if (! is_array($defaults) || ! is_array($profile) || ! is_array($entry)) {
            throw InvalidConfigurationException::invalid($key, 'middleware', 'must be an array.');
        }

        $seen = [];
        $unique = [];

        foreach ([...$defaults, ...$profile, ...$entry] as $item) {
            if (is_object($item)) {
                $key = 'object:'.spl_object_id($item);
            } elseif (is_string($item)) {
                $key = 'value:'.$item;
            } else {
                $unique[] = $item;

                continue;
            }

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $item;
        }

        return $unique;
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeSecondPass(string $key, mixed $defaults, mixed $profile, mixed $entry): array
    {
        if (! is_array($defaults) || ! is_array($profile) || ! is_array($entry)) {
            throw InvalidConfigurationException::invalid($key, 'second_pass', 'must be an object.');
        }

        return array_replace($defaults, $profile, $entry);
    }
}
