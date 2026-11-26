<?php

namespace Goodport\AiModerator\Config;

use InvalidArgumentException;

final class InvalidConfigurationException extends InvalidArgumentException
{
    public static function invalid(string $check, string $field, string $message): self
    {
        return new self("Invalid configuration for check [{$check}] at [{$field}]: {$message}");
    }

    public static function invalidSetting(string $key, string $message): self
    {
        return new self("Invalid configuration for [{$key}]: {$message}");
    }

    public static function unknownCheck(string $key): self
    {
        return new self("Check [{$key}] is not configured.");
    }

    public static function unknownProfile(string $profile): self
    {
        return new self("Profile [{$profile}] is not configured.");
    }

    public static function invalidPolicy(string $policy, string $message): self
    {
        return new self("Invalid configuration for policy [{$policy}]: {$message}");
    }
}
