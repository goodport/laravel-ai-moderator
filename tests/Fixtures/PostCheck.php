<?php

namespace Goodport\AiModerator\Tests\Fixtures;

enum PostCheck: string
{
    case Body = 'post_body';
    case Cover = 'post_cover';
}
