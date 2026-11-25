<?php

namespace Goodport\AiModerator\Results;

final readonly class Report
{
    /**
     * @param  array<string, Result>  $results
     */
    public function __construct(public array $results) {}

    public function passed(): ?bool
    {
        $states = array_map(fn (Result $result): ResultState => $result->state, array_values($this->results));

        if (in_array(ResultState::Rejected, $states, true)) {
            return false;
        }

        if (in_array(ResultState::Undecided, $states, true)) {
            return null;
        }

        return true;
    }

    public function rejected(): bool
    {
        return $this->passed() === false;
    }

    public function undecided(): bool
    {
        foreach ($this->results as $result) {
            if ($result->undecided()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, Result>
     */
    public function results(): array
    {
        return $this->results;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach ($this->results as $key => $result) {
            if (! in_array($result->state, [ResultState::Rejected, ResultState::Undecided], true)) {
                continue;
            }

            $messages[$key] = $result->reason ?? $result->meta->error ?? $result->state->value;
        }

        return $messages;
    }

    /**
     * @return array<string, array<int, Violation>>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->results as $key => $result) {
            if ($result->violations !== []) {
                $violations[$key] = $result->violations;
            }
        }

        return $violations;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state' => match ($this->passed()) {
                true => ResultState::Passed->value,
                false => ResultState::Rejected->value,
                null => ResultState::Undecided->value,
            },
            'passed' => $this->passed(),
            'results' => array_map(fn (Result $result): array => $result->toArray(), $this->results),
            'messages' => $this->messages(),
        ];
    }
}
