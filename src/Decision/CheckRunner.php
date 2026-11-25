<?php

namespace Goodport\AiModerator\Decision;

use Goodport\AiModerator\Agents\AgentOutcome;
use Goodport\AiModerator\Agents\AgentRunner;
use Goodport\AiModerator\Checks\CheckDefinition;
use Goodport\AiModerator\Checks\CheckKind;
use Goodport\AiModerator\Checks\FailureMode;
use Goodport\AiModerator\Input\NormalizedInput;
use Goodport\AiModerator\Responses\InvalidResponseException;
use Goodport\AiModerator\Responses\ParsedResponse;
use Goodport\AiModerator\Responses\ResponseParser;
use Goodport\AiModerator\Results\PassResult;
use Goodport\AiModerator\Results\ResultMeta;
use Goodport\AiModerator\Support\CorrelationContext;
use Throwable;

final class CheckRunner
{
    public function __construct(
        private readonly AgentRunner $agentRunner,
        private readonly ResponseParser $responseParser,
    ) {}

    public function run(
        CheckDefinition $check,
        NormalizedInput $input,
        string $language,
        int $pass,
        ?string $agentClass,
        ?string $provider,
        ?string $model,
        ?int $timeout,
        string $traceId,
    ): PassResult {
        return CorrelationContext::run(
            [
                'ai.trace_id' => $traceId,
                'ai.check' => $check->key(),
                'ai.pass' => $pass,
            ],
            fn (): PassResult => $this->execute(
                $check,
                $input,
                $language,
                $pass,
                $agentClass,
                $provider,
                $model,
                $timeout,
                $traceId,
            ),
        );
    }

    private function execute(
        CheckDefinition $check,
        NormalizedInput $input,
        string $language,
        int $pass,
        ?string $agentClass,
        ?string $provider,
        ?string $model,
        ?int $timeout,
        string $traceId,
    ): PassResult {
        $outcome = $this->agentRunner->run(
            $check,
            $input,
            $language,
            $pass,
            $agentClass,
            $provider,
            $model,
            $timeout,
        );

        if ($outcome->error !== null) {
            return $this->failure($check, $outcome, $outcome->error, $pass, $traceId);
        }

        try {
            $response = $outcome->response;
            $parsed = $this->responseParser->parse($check, $response);
            $decision = $this->rule($check)->decide($check, $parsed);
        } catch (InvalidResponseException $exception) {
            return $this->failure($check, $outcome, $exception, $pass, $traceId);
        }

        return new PassResult(
            state: $decision->state,
            reason: $decision->reason,
            violations: $decision->violations,
            label: $this->label($parsed),
            answers: $this->answers($parsed),
            details: $this->details($parsed),
            meta: new ResultMeta(
                check: $check->key(),
                pass: $pass,
                provider: $outcome->provider,
                model: $outcome->model,
                invocationId: $outcome->invocationId,
                durationMs: $outcome->durationMs,
                error: null,
                traceId: $traceId,
            ),
        );
    }

    private function failure(
        CheckDefinition $check,
        AgentOutcome $outcome,
        Throwable $exception,
        int $pass,
        string $traceId,
    ): PassResult {
        if ($check->config->onError === FailureMode::Throw) {
            throw $exception;
        }

        return PassResult::undecided(
            check: $check->key(),
            pass: $pass,
            provider: $outcome->provider,
            model: $outcome->model,
            invocationId: $outcome->invocationId,
            durationMs: $outcome->durationMs,
            error: $exception::class.': '.$exception->getMessage(),
            traceId: $traceId,
        );
    }

    private function rule(CheckDefinition $check): DecisionRule
    {
        $rule = $check->config->decisionRule;

        if ($rule instanceof DecisionRule) {
            return $rule;
        }

        if (is_string($rule)) {
            return app($rule);
        }

        return match ($check->kind()) {
            CheckKind::Verdict => new ModelVerdictRule,
            CheckKind::Checklist => new AllBlockingRule,
            CheckKind::Classify => new RejectOnRule,
        };
    }

    private function label(ParsedResponse $response): ?string
    {
        $label = $response->get('label');

        return is_string($label) ? $label : null;
    }

    /**
     * @return array<string, string>
     */
    private function answers(ParsedResponse $response): array
    {
        $answers = $response->get('answers', []);

        return is_array($answers) ? $answers : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function details(ParsedResponse $response): array
    {
        $details = $response->get('details', []);

        return is_array($details) ? $details : [];
    }
}
