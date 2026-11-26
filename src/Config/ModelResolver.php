<?php

namespace Goodport\AiModerator\Config;

use Illuminate\Contracts\Config\Repository;

final class ModelResolver
{
    public function __construct(private readonly Repository $config) {}

    /**
     * Resolve the inherited model selection for one check.
     *
     * Roles are applied before explicit fields at the same level. More
     * specific levels then override the inherited values.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $entry
     */
    public function resolve(string $key, array $defaults, array $profile, array $entry): ModelSelection
    {
        $selection = new ModelSelection;

        foreach ([$defaults, $profile, $entry] as $level) {
            $selection = $this->applyLevel($key, $level, $selection);
        }

        return $selection;
    }

    /**
     * Resolve the second pass from the final first-pass selection.
     *
     * @param  array<string, mixed>  $secondPass
     */
    public function resolveSecondPass(string $key, ModelSelection $firstPass, array $secondPass): ModelSelection
    {
        return $this->applyLevel($key, $secondPass, $firstPass, 'second_pass');
    }

    /**
     * @param  array<string, mixed>  $level
     */
    private function applyLevel(
        string $key,
        array $level,
        ModelSelection $selection,
        string $path = 'model_role',
    ): ModelSelection {
        $selection = $this->applyRole($key, $level, $selection, $path);

        return $this->applyExplicit($level, $selection);
    }

    /**
     * @param  array<string, mixed>  $level
     */
    private function applyRole(
        string $key,
        array $level,
        ModelSelection $selection,
        string $path,
    ): ModelSelection {
        if (! array_key_exists('model_role', $level) || $level['model_role'] === null) {
            return $selection;
        }

        $roleName = $level['model_role'];

        if (! is_string($roleName) || $roleName === '') {
            throw InvalidConfigurationException::invalid($key, $path, 'must be a non-empty string or null.');
        }

        $role = $this->role($roleName);

        return new ModelSelection(
            provider: $role['provider'],
            model: $role['model'],
            timeout: $role['timeout'] ?? $selection->timeout,
        );
    }

    /**
     * @param  array<string, mixed>  $level
     */
    private function applyExplicit(array $level, ModelSelection $selection): ModelSelection
    {
        return new ModelSelection(
            provider: array_key_exists('provider', $level) && $level['provider'] !== null
                ? $level['provider']
                : $selection->provider,
            model: array_key_exists('model', $level) && $level['model'] !== null
                ? $level['model']
                : $selection->model,
            timeout: array_key_exists('timeout', $level) && $level['timeout'] !== null
                ? $level['timeout']
                : $selection->timeout,
        );
    }

    /**
     * @return array{provider: string, model: string, timeout?: int|null}
     */
    private function role(string $name): array
    {
        $roles = $this->config->get('ai-moderator.model_roles', []);

        if (! is_array($roles)) {
            throw InvalidConfigurationException::invalidSetting('ai-moderator.model_roles', 'must be an object.');
        }

        if (! array_key_exists($name, $roles)) {
            throw InvalidConfigurationException::invalidSetting(
                'ai-moderator.model_roles.'.$name,
                "unknown model role [{$name}].",
            );
        }

        $role = $roles[$name];

        if (! is_array($role)) {
            throw InvalidConfigurationException::invalidSetting(
                'ai-moderator.model_roles.'.$name,
                'must be an object with provider and model.',
            );
        }

        $provider = $role['provider'] ?? null;
        $model = $role['model'] ?? null;
        $timeout = $role['timeout'] ?? null;

        if (! is_string($provider) || $provider === '') {
            throw InvalidConfigurationException::invalidSetting(
                'ai-moderator.model_roles.'.$name.'.provider',
                'must be a non-empty string.',
            );
        }

        if (! is_string($model) || $model === '') {
            throw InvalidConfigurationException::invalidSetting(
                'ai-moderator.model_roles.'.$name.'.model',
                'must be a non-empty string.',
            );
        }

        if ($timeout !== null && (! is_int($timeout) || $timeout <= 0)) {
            throw InvalidConfigurationException::invalidSetting(
                'ai-moderator.model_roles.'.$name.'.timeout',
                'must be a positive integer or null.',
            );
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'timeout' => $timeout,
        ];
    }
}
