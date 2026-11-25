<?php

namespace Goodport\AiModerator\Decision;

use Goodport\AiModerator\Results\ResultState;
use Goodport\AiModerator\Results\Violation;

final readonly class Decision
{
    /**
     * @param  array<int, Violation>  $violations
     */
    public function __construct(
        public ResultState $state,
        public ?string $reason,
        public array $violations,
    ) {}
}
