<?php

namespace Goodport\AiModerator\Input;

interface InputResolver
{
    /**
     * Return named values for the check.
     *
     * @return array<string, mixed>
     */
    public function fields(): array;
}
