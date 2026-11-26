<?php

namespace Goodport\AiModerator\Checks;

use Goodport\AiModerator\Audit\ModerationAuditContext;
use Goodport\AiModerator\Audit\ModerationAuditRecorder;
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
use Goodport\AiModerator\Support\CorrelationContext;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Throwable;

final class PendingCheck
{
    private InputResolver|array|null $input = null;

    private ?string $language = null;

    private bool $fresh = false;

    private ?ModerationAuditContext $auditContext = null;

    private ?PassResult $firstPass = null;

    private ?PassResult $secondPass = null;

    private ?string $currentTraceId = null;

    public function __construct(
        private readonly string $key,
        private readonly CheckRegistry $registry,
        private readonly InputNormalizer $inputNormalizer,
        private readonly CheckRunner $runner,
        private readonly SecondPassMerger $merger,
        private readonly ModerationAuditRecorder $auditRecorder,
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

    public function fresh(): self
    {
        $this->fresh = true;

        return $this;
    }

    public function audit(ModerationAuditContext $context): self
    {
        $this->auditContext = $context;

        return $this;
    }

    public function run(): Result
    {
        $context = $this->fresh ? ['ai.journal.fresh' => true] : [];

        return CorrelationContext::run($context, function (): Result {
            $this->firstPass = null;
            $this->secondPass = null;
            $this->currentTraceId = null;

            try {
                $result = $this->execute();
            } catch (Throwable $exception) {
                $this->recordFailure($exception);

                throw $exception;
            }

            $this->auditRecorder->record(
                $result,
                $this->auditContext,
                $this->firstPass,
                $this->secondPass,
            );

            return $result;
        });
    }

    private function recordFailure(Throwable $exception): void
    {
        try {
            $this->auditRecorder->recordFailure(
                $this->key,
                $this->currentTraceId ?? $this->traceId ?? (string) Str::uuid(),
                $exception,
                $this->auditContext,
                $this->firstPass,
                $this->secondPass,
            );
        } catch (Throwable $auditException) {
            report($auditException);
        }
    }

    private function execute(): Result
    {
        $this->currentTraceId = $this->traceId ?? (string) Str::uuid();
        $traceId = $this->currentTraceId;

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

        $this->firstPass = $first;

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

        $this->secondPass = $second;

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
