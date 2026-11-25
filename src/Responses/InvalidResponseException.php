<?php

namespace Goodport\AiModerator\Responses;

use RuntimeException;

final class InvalidResponseException extends RuntimeException
{
    public static function forCheck(string $check, string $message): self
    {
        return new self("Invalid AI response for check [{$check}]: {$message}");
    }
}
