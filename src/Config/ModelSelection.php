<?php

namespace Goodport\AiModerator\Config;

final readonly class ModelSelection
{
    public function __construct(
        public ?string $provider = null,
        public ?string $model = null,
        public ?int $timeout = null,
    ) {}
}
