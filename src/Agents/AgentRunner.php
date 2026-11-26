<?php

namespace Goodport\AiModerator\Agents;

use Goodport\AiModerator\Checks\CheckDefinition;
use Goodport\AiModerator\Config\InvalidConfigurationException;
use Goodport\AiModerator\Input\NormalizedInput;
use Goodport\AiModerator\Journal\JournalReplay;
use Goodport\AiModerator\Prompt\DefaultPromptBuilder;
use Goodport\AiModerator\Prompt\PromptBuilder;
use Goodport\AiModerator\Prompt\PromptContext;
use Goodport\AiModerator\Schema\SchemaBuilder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Throwable;

final class AgentRunner
{
    public function __construct(
        private readonly SchemaBuilder $schemaBuilder,
        private readonly DefaultPromptBuilder $defaultPromptBuilder,
        private readonly JournalReplay $journal,
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
    ): AgentOutcome {
        $context = new PromptContext($check, $input, $pass, $pass === 2, $language);

        $promptBuilder = $agentClass === null
            ? $this->promptBuilder($check)
            : $this->defaultPromptBuilder;

        $agent = $agentClass === null
            ? $this->moderationAgent($check, $promptBuilder, $context)
            : $this->customAgent($check, $agentClass);

        $userPrompt = $promptBuilder->user($context);
        $attachments = $promptBuilder->attachments($context);

        $startedAt = hrtime(true);

        try {
            $response = $agent->prompt(
                $userPrompt,
                $attachments,
                $provider,
                $model,
                $timeout,
            );
        } catch (Throwable $exception) {
            return new AgentOutcome(
                response: null,
                error: $exception,
                provider: $provider,
                model: $model,
                invocationId: null,
                durationMs: $this->elapsedMilliseconds($startedAt),
            );
        }

        return new AgentOutcome(
            response: $response,
            error: null,
            provider: $response->meta->provider ?? $provider,
            model: $response->meta->model ?? $model,
            invocationId: $response->invocationId,
            durationMs: $this->elapsedMilliseconds($startedAt),
        );
    }

    private function moderationAgent(
        CheckDefinition $check,
        PromptBuilder $promptBuilder,
        PromptContext $context,
    ): ModerationAgent {
        $schema = fn (JsonSchema $jsonSchema): array => $this->schemaBuilder->build($check, $jsonSchema);

        return new ModerationAgent(
            instructions: $promptBuilder->system($context),
            schema: $schema,
            middleware: $this->middleware($check),
        );
    }

    /**
     * @return array<int, mixed>
     */
    private function middleware(CheckDefinition $check): array
    {
        $middleware = $check->config->middleware;

        foreach ($this->journal->middlewareFor($check) as $item) {
            if (! in_array($item, $middleware, true)) {
                $middleware[] = $item;
            }
        }

        return $middleware;
    }

    private function customAgent(CheckDefinition $check, string $agentClass): Agent
    {
        $agent = app($agentClass);

        if (! $agent instanceof Agent || ! $agent instanceof HasStructuredOutput) {
            throw InvalidConfigurationException::invalid(
                $check->key(),
                'agent',
                'must implement Agent and HasStructuredOutput.',
            );
        }

        return $agent;
    }

    private function promptBuilder(CheckDefinition $check): PromptBuilder
    {
        if ($check->config->promptBuilder === null) {
            return $this->defaultPromptBuilder;
        }

        $builder = app($check->config->promptBuilder);

        if (! $builder instanceof PromptBuilder) {
            throw InvalidConfigurationException::invalid(
                $check->key(),
                'prompt_builder',
                'must implement PromptBuilder.',
            );
        }

        return $builder;
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
