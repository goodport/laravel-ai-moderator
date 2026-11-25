<?php

namespace Goodport\AiModerator\Checks;

use Goodport\AiModerator\Config\CheckRegistry;
use Goodport\AiModerator\Decision\CheckRunner;
use Goodport\AiModerator\Decision\SecondPassMerger;
use Goodport\AiModerator\Decision\SecondPassTrigger;
use Goodport\AiModerator\Input\InputNormalizer;
use Goodport\AiModerator\Input\InputResolver;
use Goodport\AiModerator\Input\InvalidInputException;
use Goodport\AiModerator\Input\NormalizedInput;
use Goodport\AiModerator\Results\PassResult;
use Goodport\AiModerator\Results\Result;
use Goodport\AiModerator\Results\ResultState;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

final class PendingCheck
{
    private InputResolver|array|null $input = null;

    private ?string $language = null;

    public function __construct(
        private readonly string $key,
        private readonly CheckRegistry $registry,
        private readonly InputNormalizer $inputNormalizer,
        private readonly CheckRunner $runner,
        private readonly SecondPassMerger $merger,
        private readonly ?string $traceId = null,
    ) {}

    public function input(InputResolver|array $input): self
    {
        $this->input = $input;

        return $this;
    }

    public function language(string $locale): self
    {
        $this->language = $locale;

        return $this;
    }

    public function run(): Result
    {
        $traceId = $this->traceId ?? (string) Str::uuid();

        if (! $this->registry->isEnabled($this->key)) {
            return Result::skippedResult($this->key, $traceId);
        }

        if ($this->input === null) {
            throw InvalidInputException::missingInput($this->key);
        }

        $check = $this->registry->resolve($this->key);

        if (! $check->enabled()) {
            return Result::skippedResult($this->key, $traceId);
        }

        $language = $this->resolveLanguage($check->config->language);
        $input = $this->inputNormalizer->normalize($check, $this->input);

        $first = $this->runner->run(
            $check,
            $input,
            $language,
            1,
            $check->config->agent,
            $check->config->provider,
            $check->config->model,
            $check->config->timeout,
            $traceId,
        );

        $final = $this->secondPass($check, $input, $language, $first, $traceId);

        return new Result(
            state: $final->state,
            check: $this->key,
            reason: $final->reason,
            violations: $final->violations,
            label: $final->label,
            answers: $final->answers,
            details: $final->details,
            meta: $final->meta,
        );
    }

    private function secondPass(
        CheckDefinition $check,
        NormalizedInput $input,
        string $language,
        PassResult $first,
        string $traceId,
    ): PassResult {
        $secondPass = $check->config->secondPass;

        if ($secondPass === null || ! $secondPass->enabled || ! $this->shouldRunSecondPass($secondPass, $first)) {
            return $first;
        }

        $second = $this->runner->run(
            $check,
            $input,
            $language,
            2,
            $secondPass->agent ?? $check->config->agent,
            $secondPass->provider ?? $check->config->provider ?? $first->meta->provider,
            $secondPass->model ?? $check->config->model ?? $first->meta->model,
            $secondPass->timeout ?? $check->config->timeout,
            $traceId,
        );

        return $this->merger->merge($first, $second, $secondPass->merge);
    }

    private function shouldRunSecondPass(SecondPassConfig $secondPass, PassResult $first): bool
    {
        return match ($secondPass->trigger) {
            SecondPassTrigger::OnReject => $first->state === ResultState::Rejected,
            SecondPassTrigger::Always => $first->state !== ResultState::Undecided,
        };
    }

    private function resolveLanguage(?string $configured): string
    {
        foreach ([$this->language, $configured] as $candidate) {
            if ($candidate !== null && $candidate !== '' && $candidate !== 'auto') {
                return $candidate;
            }
        }

        return App::getLocale();
    }
}
