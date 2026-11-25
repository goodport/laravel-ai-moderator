<?php

namespace Goodport\AiModerator\Prompt;

use Goodport\AiModerator\Checks\CheckDefinition;
use Goodport\AiModerator\Input\NormalizedInput;

final readonly class PromptContext
{
    public function __construct(
        public CheckDefinition $check,
        public NormalizedInput $input,
        public int $pass,
        public bool $isSecondPass,
        public string $language,
    ) {}
}
