<?php

namespace Goodport\AiModerator\Decision;

enum SecondPassTrigger: string
{
    case OnReject = 'on_reject';
    case Always = 'always';
}
