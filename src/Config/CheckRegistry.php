<?php

namespace Goodport\AiModerator\Config;

use Goodport\AiModerator\Checks\CheckDefinition;
use Illuminate\Contracts\Config\Repository;

final class CheckRegistry
{
    /**
     * @var array<string, CheckDefinition>
     */
    private array $definitions = [];

    /**
     * @var array<string, bool>
     */
    private array $enabled = [];

    public function __construct(
        private readonly Repository $config,
        private readonly CheckFactory $factory,
    ) {}

    public function resolve(string $key): CheckDefinition
    {
        return $this->definitions[$key] ??= $this->factory->make($key);
    }

    public function isEnabled(string $key): bool
    {
        if (array_key_exists($key, $this->enabled)) {
            return $this->enabled[$key];
        }

        $entry = $this->config->get("ai-moderator.checks.{$key}");

        if ($entry === null) {
            throw InvalidConfigurationException::unknownCheck($key);
        }

        if (is_string($entry)) {
            return $this->enabled[$key] = $this->resolve($key)->enabled();
        }

        if (! is_array($entry)) {
            throw InvalidConfigurationException::invalid($key, 'checks', 'must be an array or a class name.');
        }

        return $this->enabled[$key] = ($entry['enabled'] ?? true);
    }

    public function has(string $key): bool
    {
        return $this->config->get("ai-moderator.checks.{$key}") !== null;
    }

    public function flush(): void
    {
        $this->definitions = [];
        $this->enabled = [];
    }
}
