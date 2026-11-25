<?php

namespace Goodport\AiModerator\Input;

final readonly class InputField
{
    public function __construct(
        public string $name,
        public InputType $type,
        public string $label,
        public bool $required = true,
        public int $maxChars = 0,
        public TruncationStrategy $strategy = TruncationStrategy::Head,
    ) {}
}
