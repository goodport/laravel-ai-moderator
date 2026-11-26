<?php

namespace Goodport\AiModerator\Tests\Fixtures;

use Goodport\AiResilience\Limits\RateLimitStep;

final class RateLimitedCustomAgent extends CustomAgent
{
    public function middleware(): array
    {
        return [RateLimitStep::class];
    }
}
