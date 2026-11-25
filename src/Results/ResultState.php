<?php

namespace Goodport\AiModerator\Results;

enum ResultState: string
{
    case Passed = 'passed';
    case Rejected = 'rejected';
    case Undecided = 'undecided';
    case Skipped = 'skipped';
}
