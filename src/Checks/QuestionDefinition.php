<?php

namespace Goodport\AiModerator\Checks;

final readonly class QuestionDefinition
{
    /**
     * @param  array<int, string>  $values
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $values,
        public string $expected,
        public bool $blocking,
        public Severity $severity,
    ) {}
}
