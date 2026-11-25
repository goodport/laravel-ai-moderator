<?php

namespace Goodport\AiModerator\Input;

enum TruncationStrategy: string
{
    case Head = 'head';
    case HeadTail = 'head_tail';
    case None = 'none';
}
