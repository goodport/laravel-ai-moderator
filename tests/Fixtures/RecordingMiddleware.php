<?php

namespace Goodport\AiModerator\Tests\Fixtures;

use Closure;
use Laravel\Ai\PendingStep;

final class RecordingMiddleware
{
    public static int $calls = 0;

    public function handle(PendingStep $step, Closure $next): mixed
    {
        self::$calls++;

        return $next($step);
    }
}
