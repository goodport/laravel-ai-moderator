<?php

namespace Goodport\AiModerator\Decision;

use Goodport\AiModerator\Checks\CheckDefinition;
use Goodport\AiModerator\Checks\ClassifyCheckDefinition;
use Goodport\AiModerator\Responses\ParsedResponse;
use Goodport\AiModerator\Results\ResultState;
use Goodport\AiModerator\Results\Violation;
use LogicException;

final class RejectOnRule implements DecisionRule
{
    public function decide(CheckDefinition $check, ParsedResponse $response): Decision
    {
        if (! $check instanceof ClassifyCheckDefinition) {
            throw new LogicException('RejectOnRule requires a classify check.');
        }

        $label = $response->get('label');
        $reason = $response->get('reason');

        if (! in_array($label, $check->rejectOn, true)) {
            return new Decision(ResultState::Passed, $reason !== '' ? $reason : null, []);
        }

        $violation = new Violation(
            code: $label,
            message: $label,
            severity: $check->rejectSeverity,
            blocking: true,
        );

        return new Decision(
            state: ResultState::Rejected,
            reason: $reason !== '' ? $reason : $label,
            violations: [$violation],
        );
    }
}
