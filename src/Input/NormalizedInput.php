<?php

namespace Goodport\AiModerator\Input;

final readonly class NormalizedInput
{
    /**
     * @param  array<string, string>  $text
     * @param  array<string, mixed>  $images
     */
    public function __construct(
        public array $text,
        public array $images,
    ) {}

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return array_values($this->images);
    }
}
