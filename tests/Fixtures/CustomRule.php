<?php

namespace Goodport\AiModerator\Tests\Fixtures;

use Goodport\AiModerator\Checks\CheckDefinition;
use Goodport\AiModerator\Decision\Decision;
use Goodport\AiModerator\Decision\DecisionRule;
use Goodport\AiModerator\Responses\ParsedResponse;
use Goodport\AiModerator\Results\ResultState;

final class CustomRule implements DecisionRule
{
    public function decide(CheckDefinition $check, ParsedResponse $response): Decision
    {
        return new Decision(ResultState::Passed, 'Custom rule.', []);
    }
}
