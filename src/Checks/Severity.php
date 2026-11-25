<?php

namespace Goodport\AiModerator\Checks;

enum Severity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
