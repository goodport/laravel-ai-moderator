<?php

namespace Goodport\AiModerator\Checks;

abstract readonly class CheckDefinition
{
    public function __construct(public CheckConfig $config) {}

    public function key(): string
    {
        return $this->config->key;
    }

    public function kind(): CheckKind
    {
        return $this->config->kind;
    }

    public function enabled(): bool
    {
        return $this->config->enabled;
    }
}
