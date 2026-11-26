<?php

namespace Goodport\AiModerator\Config;

use Illuminate\Contracts\Config\Repository;

final readonly class ModerationSettings
{
    public function __construct(private Repository $config) {}

    public function enabled(): bool
    {
        $enabled = $this->config->get('ai-moderator.enabled');

        if (! is_bool($enabled)) {
            throw InvalidConfigurationException::invalidSetting('ai-moderator.enabled', 'must be a boolean.');
        }

        return $enabled;
    }
}
