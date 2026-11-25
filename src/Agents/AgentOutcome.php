<?php

namespace Goodport\AiModerator\Agents;

use Laravel\Ai\Responses\AgentResponse;
use Throwable;

final readonly class AgentOutcome
{
    public function __construct(
        public ?AgentResponse $response,
        public ?Throwable $error,
        public ?string $provider,
        public ?string $model,
        public ?string $invocationId,
        public int $durationMs,
    ) {}

    public function errorMessage(): ?string
    {
        return $this->error === null
            ? null
            : $this->error::class.': '.$this->error->getMessage();
    }
}
