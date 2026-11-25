<?php

namespace Goodport\AiModerator\Prompt;

interface PromptBuilder
{
    public function system(PromptContext $context): string;

    public function user(PromptContext $context): string;

    /**
     * @return array<int, mixed>
     */
    public function attachments(PromptContext $context): array;
}
