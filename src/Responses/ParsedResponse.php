<?php

namespace Goodport\AiModerator\Responses;

final readonly class ParsedResponse
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->payload);
    }
}
