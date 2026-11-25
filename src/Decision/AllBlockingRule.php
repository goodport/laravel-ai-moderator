<?php

namespace Goodport\AiModerator\Decision;

use Goodport\AiModerator\Checks\CheckDefinition;
use Goodport\AiModerator\Checks\ChecklistCheckDefinition;
use Goodport\AiModerator\Responses\ParsedResponse;
use Goodport\AiModerator\Results\ResultState;
use Goodport\AiModerator\Results\Violation;
use LogicException;

final class AllBlockingRule implements DecisionRule
{
    public function decide(CheckDefinition $check, ParsedResponse $response): Decision
    {
        if (! $check instanceof ChecklistCheckDefinition) {
            throw new LogicException('AllBlockingRule requires a checklist check.');
        }

        $answers = $response->get('answers', []);
        $violations = [];
        $blockingMessages = [];
        $hasBlockingFailure = false;

        foreach ($check->questions as $question) {
            $answer = $answers[$question->key] ?? null;

            if ($answer === $question->expected) {
                continue;
            }

            $violations[] = new Violation(
                code: $question->key,
                message: $question->label,
                severity: $question->severity,
                blocking: $question->blocking,
            );

            if ($question->blocking) {
                $hasBlockingFailure = true;
                $blockingMessages[] = $question->label;
            }
        }

        $state = $hasBlockingFailure ? ResultState::Rejected : ResultState::Passed;
        $reason = $response->get('reason');

        if ($state === ResultState::Rejected && ($reason === null || $reason === '')) {
            $reason = $blockingMessages === [] ? null : implode(' ', $blockingMessages);
        }

        if ($state === ResultState::Passed) {
            $reason = $reason !== null && $reason !== '' ? $reason : null;
        }

        return new Decision($state, $reason, $violations);
    }
}
