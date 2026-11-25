<?php

namespace Goodport\AiModerator\Checks;

final readonly class ChecklistCheckDefinition extends CheckDefinition
{
    /**
     * @param  array<string, QuestionDefinition>  $questions
     */
    public function __construct(CheckConfig $config, public array $questions)
    {
        parent::__construct($config);
    }
}
