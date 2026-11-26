<?php

namespace Goodport\AiModerator\Agents;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class ModerationAgent implements Agent, HasMiddleware, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  array<int, mixed>  $middleware
     */
    public function __construct(
        private readonly string $instructions,
        private readonly Closure $schema,
        private readonly array $middleware = [],
    ) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return ($this->schema)($schema);
    }

    /**
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return $this->middleware;
    }
}
