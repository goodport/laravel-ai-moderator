<?php

namespace Goodport\AiModerator\Decision;

use Goodport\AiModerator\Checks\CheckDefinition;
use Goodport\AiModerator\Checks\VerdictCheckDefinition;
use Goodport\AiModerator\Responses\ParsedResponse;
use Goodport\AiModerator\Results\ResultState;
use Goodport\AiModerator\Results\Violation;
use LogicException;

final class ModelVerdictRule implements DecisionRule
{
    public function decide(CheckDefinition $check, ParsedResponse $response): Decision
    {
        if (! $check instanceof VerdictCheckDefinition) {
            throw new LogicException('ModelVerdictRule requires a verdict check.');
        }

        $violations = [];

        foreach ($response->get('violations', []) as $item) {
            $definition = $check->violations[$item['code']];

            $violations[] = new Violation(
                code: $definition->code,
                message: $item['message'] !== '' ? $item['message'] : ($definition->description ?? $definition->code),
                severity: $definition->severity,
                blocking: $definition->blocking,
            );
        }

        $state = $response->get('verdict') === 'reject'
            ? ResultState::Rejected
            : ResultState::Passed;

        $reason = $this->reason($response->get('reason'), $violations, $state);

        return new Decision($state, $reason, $violations);
    }

    /**
     * @param  array<int, Violation>  $violations
     */
    private function reason(?string $reason, array $violations, ResultState $state): ?string
    {
        if ($reason !== null && $reason !== '') {
            return $reason;
        }

        if ($state !== ResultState::Rejected) {
            return null;
        }

        $messages = array_map(
            fn (Violation $violation): string => $violation->message,
            array_filter($violations, fn (Violation $violation): bool => $violation->blocking),
        );

        return $messages === [] ? null : implode(' ', $messages);
    }
}
