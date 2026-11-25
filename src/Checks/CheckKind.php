<?php

namespace Goodport\AiModerator\Checks;

enum CheckKind: string
{
    case Verdict = 'verdict';
    case Checklist = 'checklist';
    case Classify = 'classify';
}
