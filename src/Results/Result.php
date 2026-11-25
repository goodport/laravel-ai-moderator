<?php

namespace Goodport\AiModerator\Results;

final readonly class Result
{
    /**
     * @param  array<int, Violation>  $violations
     * @param  array<string, string>  $answers
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public ResultState $state,
        public string $check,
        public ?string $reason,
        public array $violations,
        public ?string $label,
        public array $answers,
        public array $details,
        public ResultMeta $meta,
    ) {}

    public static function skippedResult(string $check, string $traceId): self
    {
        return new self(
            state: ResultState::Skipped,
            check: $check,
            reason: null,
            violations: [],
            label: null,
            answers: [],
            details: [],
            meta: new ResultMeta($check, 0, null, null, null, 0, null, $traceId),
        );
    }

    public function passed(): ?bool
    {
        return match ($this->state) {
            ResultState::Passed => true,
            ResultState::Rejected => false,
            ResultState::Undecided, ResultState::Skipped => null,
        };
    }

    public function state(): ResultState
    {
        return $this->state;
    }

    public function check(): string
    {
        return $this->check;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    /**
     * @return array<int, Violation>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    /**
     * @return array<string, string>
     */
    public function answers(): array
    {
        return $this->answers;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }

    public function meta(): ResultMeta
    {
        return $this->meta;
    }

    public function failed(): bool
    {
        return $this->state === ResultState::Rejected;
    }

    public function undecided(): bool
    {
        return $this->state === ResultState::Undecided;
    }

    public function skipped(): bool
    {
        return $this->state === ResultState::Skipped;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'check' => $this->check,
            'state' => $this->state->value,
            'passed' => $this->passed(),
            'reason' => $this->reason,
            'violations' => array_map(fn (Violation $violation): array => $violation->toArray(), $this->violations),
            'label' => $this->label,
            'answers' => $this->answers,
            'details' => $this->details,
            'meta' => $this->meta->toArray(),
        ];
    }
}
