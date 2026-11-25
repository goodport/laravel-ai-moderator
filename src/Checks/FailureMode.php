<?php

namespace Goodport\AiModerator\Checks;

enum FailureMode: string
{
    case Undecided = 'undecided';
    case Throw = 'throw';
}
