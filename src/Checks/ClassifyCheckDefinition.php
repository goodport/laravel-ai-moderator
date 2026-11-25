<?php

namespace Goodport\AiModerator\Checks;

final readonly class ClassifyCheckDefinition extends CheckDefinition
{
    /**
     * @param  array<int, string>  $labels
     * @param  array<int, string>  $rejectOn
     */
    public function __construct(
        CheckConfig $config,
        public array $labels,
        public array $rejectOn,
        public Severity $rejectSeverity,
    ) {
        parent::__construct($config);
    }
}
