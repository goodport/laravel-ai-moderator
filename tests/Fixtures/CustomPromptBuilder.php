<?php

namespace Goodport\AiModerator\Tests\Fixtures;

use Goodport\AiModerator\Prompt\PromptBuilder;
use Goodport\AiModerator\Prompt\PromptContext;

final class CustomPromptBuilder implements PromptBuilder
{
    public function system(PromptContext $context): string
    {
        return 'Custom system.';
    }

    public function user(PromptContext $context): string
    {
        return 'Custom user.';
    }

    public function attachments(PromptContext $context): array
    {
        return [];
    }
}
