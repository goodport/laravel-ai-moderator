<?php

namespace Goodport\AiModerator\Results;

final readonly class PassResult
{
    /**
     * @param  array<int, Violation>  $violations
     * @param  array<string, string>  $answers
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public ResultState $state,
        public ?string $reason,
        public array $violations,
        public ?string $label,
        public array $answers,
        public array $details,
        public ResultMeta $meta,
    ) {}

    /**
     * @param  array<int, Violation>  $violations
     * @param  array<string, string>  $answers
     * @param  array<string, mixed>  $details
     */
    public static function undecided(
        string $check,
        int $pass,
        ?string $provider,
        ?string $model,
        ?string $invocationId,
        int $durationMs,
        string $error,
        string $traceId,
    ): self {
        return new self(
            state: ResultState::Undecided,
            reason: null,
            violations: [],
            label: null,
            answers: [],
            details: [],
            meta: new ResultMeta($check, $pass, $provider, $model, $invocationId, $durationMs, $error, $traceId),
        );
    }
}
