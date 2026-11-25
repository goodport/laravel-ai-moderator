<?php

namespace Goodport\AiModerator\Results;

final readonly class ResultMeta
{
    public function __construct(
        public string $check,
        public int $pass,
        public ?string $provider,
        public ?string $model,
        public ?string $invocationId,
        public int $durationMs,
        public ?string $error,
        public string $traceId,
    ) {}

    /**
     * @return array{
     *     check: string,
     *     pass: int,
     *     provider: ?string,
     *     model: ?string,
     *     invocation_id: ?string,
     *     duration_ms: int,
     *     error: ?string,
     *     trace_id: string
     * }
     */
    public function toArray(): array
    {
        return [
            'check' => $this->check,
            'pass' => $this->pass,
            'provider' => $this->provider,
            'model' => $this->model,
            'invocation_id' => $this->invocationId,
            'duration_ms' => $this->durationMs,
            'error' => $this->error,
            'trace_id' => $this->traceId,
        ];
    }
}
