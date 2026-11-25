<?php

namespace Goodport\AiModerator\Input;

use Illuminate\Support\Str;

final class Truncator
{
    /**
     * Truncate a value mb-safely according to the configured strategy.
     */
    public function truncate(string $value, int $maxChars, TruncationStrategy $strategy): string
    {
        if ($strategy === TruncationStrategy::None || $maxChars <= 0) {
            return $value;
        }

        if (mb_strlen($value) <= $maxChars) {
            return $value;
        }

        if ($strategy === TruncationStrategy::HeadTail) {
            $part = intdiv(max(0, $maxChars - 1), 2);

            if ($part === 0) {
                return Str::substr($value, 0, $maxChars);
            }

            return Str::substr($value, 0, $part).' '.Str::substr($value, -$part);
        }

        return Str::substr($value, 0, $maxChars);
    }
}
