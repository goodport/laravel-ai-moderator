<?php

namespace Goodport\AiModerator\Checks;

final readonly class ViolationDefinition
{
    public function __construct(
        public string $code,
        public Severity $severity,
        public bool $blocking,
        public ?string $description = null,
    ) {}
}
