<?php

namespace Goodport\AiModerator\Responses;

use Goodport\AiModerator\Checks\CheckDefinition;
use Goodport\AiModerator\Checks\ChecklistCheckDefinition;
use Goodport\AiModerator\Checks\ClassifyCheckDefinition;
use Goodport\AiModerator\Checks\VerdictCheckDefinition;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\AgentResponse;

final class ResponseParser
{
    public function parse(CheckDefinition $check, AgentResponse $response): ParsedResponse
    {
        if (! isset($response->structured) || ! is_array($response->structured)) {
            throw InvalidResponseException::forCheck($check->key(), 'structured output is missing.');
        }

        $payload = $response->structured;

        return match (true) {
            $check instanceof VerdictCheckDefinition => $this->parseVerdict($check, $payload),
            $check instanceof ChecklistCheckDefinition => $this->parseChecklist($check, $payload),
            $check instanceof ClassifyCheckDefinition => $this->parseClassify($check, $payload),
            default => throw InvalidResponseException::forCheck($check->key(), 'unsupported check kind.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function parseVerdict(VerdictCheckDefinition $check, array $payload): ParsedResponse
    {
        $verdict = $payload['verdict'] ?? null;
        $reason = $payload['reason'] ?? null;
        $violations = $payload['violations'] ?? null;

        if (! in_array($verdict, ['approve', 'reject'], true)) {
            throw InvalidResponseException::forCheck($check->key(), '`verdict` must be "approve" or "reject".');
        }

        if (! is_string($reason)) {
            throw InvalidResponseException::forCheck($check->key(), '`reason` must be a string.');
        }

        if (! is_array($violations)) {
            throw InvalidResponseException::forCheck($check->key(), '`violations` must be an array.');
        }

        $codes = array_keys($check->violations);
        $blockingCodes = [];

        foreach ($check->violations as $code => $definition) {
            if ($definition->blocking) {
                $blockingCodes[] = $code;
            }
        }

        $normalizedViolations = [];

        foreach ($violations as $violation) {
            if (! is_array($violation)) {
                throw InvalidResponseException::forCheck($check->key(), 'each violation must be an object.');
            }

            $code = $violation['code'] ?? null;
            $message = $violation['message'] ?? null;

            if (! is_string($code) || ! in_array($code, $codes, true)) {
                throw InvalidResponseException::forCheck($check->key(), 'violation `code` is not declared.');
            }

            if (! is_string($message)) {
                throw InvalidResponseException::forCheck($check->key(), 'violation `message` must be a string.');
            }

            $normalizedViolations[] = ['code' => $code, 'message' => $message];
        }

        $returnedCodes = array_column($normalizedViolations, 'code');
        $returnedBlocking = array_values(array_intersect($returnedCodes, $blockingCodes));

        if ($verdict === 'reject' && $returnedBlocking === []) {
            throw InvalidResponseException::forCheck($check->key(), 'a rejection requires at least one blocking violation.');
        }

        if ($verdict === 'approve' && $returnedBlocking !== []) {
            throw InvalidResponseException::forCheck($check->key(), 'an approval cannot include blocking violations.');
        }

        return new ParsedResponse($this->withDetails([
            'verdict' => $verdict,
            'reason' => $this->reason($reason, $check),
            'violations' => $normalizedViolations,
        ], $payload, $check));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function parseChecklist(ChecklistCheckDefinition $check, array $payload): ParsedResponse
    {
        $answers = $payload['answers'] ?? null;
        $reason = $payload['reason'] ?? null;

        if (! is_array($answers)) {
            throw InvalidResponseException::forCheck($check->key(), '`answers` must be an object.');
        }

        $expectedKeys = array_keys($check->questions);
        $answerKeys = array_keys($answers);

        $missing = array_diff($expectedKeys, $answerKeys);
        $extra = array_diff($answerKeys, $expectedKeys);

        if ($missing !== [] || $extra !== []) {
            throw InvalidResponseException::forCheck($check->key(), '`answers` must contain exactly the declared question keys.');
        }

        foreach ($check->questions as $question) {
            $answer = $answers[$question->key] ?? null;

            if (! is_string($answer) || ! in_array($answer, $question->values, true)) {
                throw InvalidResponseException::forCheck($check->key(), "answer for [{$question->key}] is not allowed.");
            }
        }

        if (! is_string($reason)) {
            throw InvalidResponseException::forCheck($check->key(), '`reason` must be a string.');
        }

        return new ParsedResponse($this->withDetails([
            'answers' => $answers,
            'reason' => $this->reason($reason, $check),
        ], $payload, $check));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function parseClassify(ClassifyCheckDefinition $check, array $payload): ParsedResponse
    {
        $label = $payload['label'] ?? null;
        $reason = $payload['reason'] ?? null;

        if (! is_string($label) || ! in_array($label, $check->labels, true)) {
            throw InvalidResponseException::forCheck($check->key(), '`label` is not one of the declared labels.');
        }

        if (! is_string($reason)) {
            throw InvalidResponseException::forCheck($check->key(), '`reason` must be a string.');
        }

        return new ParsedResponse($this->withDetails([
            'label' => $label,
            'reason' => $this->reason($reason, $check),
        ], $payload, $check));
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withDetails(array $parsed, array $payload, CheckDefinition $check): array
    {
        if ($check->config->detailsSchema === null) {
            return $parsed;
        }

        if (! isset($payload['details']) || ! is_array($payload['details'])) {
            throw InvalidResponseException::forCheck($check->key(), '`details` must be an object.');
        }

        $parsed['details'] = $payload['details'];

        return $parsed;
    }

    private function reason(string $reason, CheckDefinition $check): string
    {
        return Str::substr($reason, 0, $check->config->reasonMaxChars);
    }
}
