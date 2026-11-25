<?php

namespace Goodport\AiModerator\Checks;

final readonly class VerdictCheckDefinition extends CheckDefinition
{
    /**
     * @param  array<string, ViolationDefinition>  $violations
     */
    public function __construct(CheckConfig $config, public array $violations)
    {
        parent::__construct($config);
    }
}
