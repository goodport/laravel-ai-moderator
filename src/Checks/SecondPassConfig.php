<?php

namespace Goodport\AiModerator\Checks;

use Goodport\AiModerator\Decision\MergeStrategy;
use Goodport\AiModerator\Decision\SecondPassTrigger;

final readonly class SecondPassConfig
{
    public function __construct(
        public bool $enabled,
        public SecondPassTrigger $trigger,
        public MergeStrategy $merge,
        public ?string $provider,
        public ?string $model,
        public ?int $timeout,
        public ?string $agent,
    ) {}
}
