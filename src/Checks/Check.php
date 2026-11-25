<?php

namespace Goodport\AiModerator\Checks;

interface Check
{
    public function definition(): CheckDefinition;
}
