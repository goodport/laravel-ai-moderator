<?php

namespace Goodport\AiModerator\Results;

use Goodport\AiModerator\Checks\Severity;

final readonly class Violation
{
    public function __construct(
        public string $code,
        public string $message,
        public Severity $severity,
        public bool $blocking,
    ) {}

    /**
     * @return array{code: string, message: string, severity: string, blocking: bool}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'severity' => $this->severity->value,
            'blocking' => $this->blocking,
        ];
    }
}
