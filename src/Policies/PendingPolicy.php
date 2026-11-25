<?php

namespace Goodport\AiModerator\Policies;

use Goodport\AiModerator\Input\InputResolver;
use Goodport\AiModerator\Input\InvalidInputException;
use Goodport\AiModerator\Results\Report;

final class PendingPolicy
{
    private InputResolver|array|null $input = null;

    private ?string $language = null;

    public function __construct(
        private readonly string $key,
        private readonly PolicyRunner $runner,
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

    public function run(): Report
    {
        if ($this->input === null) {
            throw InvalidInputException::missingPolicyInput($this->key);
        }

        return $this->runner->run($this->key, $this->input, $this->language);
    }
}
