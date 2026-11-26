<?php

namespace Goodport\AiModerator\Policies;

use Goodport\AiModerator\Audit\ModerationAuditContext;
use Goodport\AiModerator\Config\ModerationSettings;
use Goodport\AiModerator\Input\InputResolver;
use Goodport\AiModerator\Input\InvalidInputException;
use Goodport\AiModerator\Results\Report;

final class PendingPolicy
{
    private InputResolver|array|null $input = null;

    private ?string $language = null;

    private ?ModerationAuditContext $auditContext = null;

    public function __construct(
        private readonly string $key,
        private readonly PolicyRunner $runner,
        private readonly ModerationSettings $settings,
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

    public function audit(ModerationAuditContext $context): self
    {
        $this->auditContext = $context;

        return $this;
    }

    public function run(): Report
    {
        if (! $this->settings->enabled()) {
            return $this->runner->skipped($this->key);
        }

        if ($this->input === null) {
            throw InvalidInputException::missingPolicyInput($this->key);
        }

        return $this->runner->run($this->key, $this->input, $this->language, $this->auditContext);
    }
}
