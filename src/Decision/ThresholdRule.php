<?php

namespace Goodport\AiModerator\Decision;

use Goodport\AiModerator\Checks\CheckDefinition;
use Goodport\AiModerator\Checks\Severity;
use Goodport\AiModerator\Responses\InvalidResponseException;
use Goodport\AiModerator\Responses\ParsedResponse;
use Goodport\AiModerator\Results\ResultState;
use Goodport\AiModerator\Results\Violation;

final readonly class ThresholdRule implements DecisionRule
{
    public function __construct(
        private string $field,
        private float $threshold,
        private bool $rejectWhenAbove = true,
        private Severity $severity = Severity::High,
    ) {}

    public function decide(CheckDefinition $check, ParsedResponse $response): Decision
    {
        $value = $response->get($this->field);

        if (! is_numeric($value)) {
            throw InvalidResponseException::forCheck($check->key(), "`{$this->field}` must be numeric.");
        }

        $rejected = $this->rejectWhenAbove
            ? (float) $value >= $this->threshold
            : (float) $value <= $this->threshold;

        if (! $rejected) {
            return new Decision(ResultState::Passed, $this->reason($response), []);
        }

        $violation = new Violation(
            code: $this->field,
            message: "{$this->field} is {$value}.",
            severity: $this->severity,
            blocking: true,
        );

        return new Decision(
            state: ResultState::Rejected,
            reason: $this->reason($response) ?? $violation->message,
            violations: [$violation],
        );
    }

    private function reason(ParsedResponse $response): ?string
    {
        $reason = $response->get('reason');

        return is_string($reason) && $reason !== '' ? $reason : null;
    }
}
