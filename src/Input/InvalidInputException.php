<?php

namespace Goodport\AiModerator\Input;

use InvalidArgumentException;

final class InvalidInputException extends InvalidArgumentException
{
    public static function missingInput(string $check): self
    {
        return new self("No input was provided for check [{$check}].");
    }

    public static function missingPolicyInput(string $policy): self
    {
        return new self("No input was provided for policy [{$policy}].");
    }

    public static function missingField(string $check, string $field): self
    {
        return new self("Missing required input field [{$field}] for check [{$check}].");
    }

    public static function invalidImage(string $check, string $field): self
    {
        return new self("Input field [{$field}] for check [{$check}] must be an image URL, path, upload, or Laravel AI image.");
    }

    public static function invalidText(string $check, string $field): self
    {
        return new self("Input field [{$field}] for check [{$check}] must be a string or stringable value.");
    }
}
