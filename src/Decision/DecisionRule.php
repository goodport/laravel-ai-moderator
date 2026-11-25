<?php

namespace Goodport\AiModerator\Decision;

use Goodport\AiModerator\Checks\CheckDefinition;
use Goodport\AiModerator\Responses\ParsedResponse;

interface DecisionRule
{
    public function decide(CheckDefinition $check, ParsedResponse $response): Decision;
}
