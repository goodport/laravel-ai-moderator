<?php

namespace Goodport\AiModerator\Decision;

enum MergeStrategy: string
{
    case ApproveWins = 'approve_wins';
    case Strict = 'strict';
    case SecondDecides = 'second_decides';
}
