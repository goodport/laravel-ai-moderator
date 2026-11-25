<?php

namespace Goodport\AiModerator;

use Goodport\AiModerator\Checks\PendingCheck;
use Goodport\AiModerator\Config\CheckRegistry;
use Goodport\AiModerator\Decision\CheckRunner;
use Goodport\AiModerator\Decision\SecondPassMerger;
use Goodport\AiModerator\Input\InputNormalizer;
use Goodport\AiModerator\Policies\PendingPolicy;
use Goodport\AiModerator\Policies\PolicyRunner;

final class AiModeratorManager
{
    public function __construct(
        private readonly CheckRegistry $registry,
        private readonly InputNormalizer $inputNormalizer,
        private readonly CheckRunner $runner,
        private readonly SecondPassMerger $merger,
        private readonly PolicyRunner $policyRunner,
    ) {}

    public function check(string $key): PendingCheck
    {
        return new PendingCheck(
            key: $key,
            registry: $this->registry,
            inputNormalizer: $this->inputNormalizer,
            runner: $this->runner,
            merger: $this->merger,
        );
    }

    public function policy(string $key): PendingPolicy
    {
        return new PendingPolicy($key, $this->policyRunner);
    }
}
