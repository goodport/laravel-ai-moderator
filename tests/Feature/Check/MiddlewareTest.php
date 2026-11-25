<?php

namespace Goodport\AiModerator\Tests\Feature\Check;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Tests\Fixtures\RecordingMiddleware;
use Goodport\AiModerator\Tests\TestCase;

final class MiddlewareTest extends TestCase
{
    /**
     * Middleware from the check config wraps the agent step.
     */
    public function test_configured_middleware_is_applied(): void
    {
        RecordingMiddleware::$calls = 0;

        $this->setCheck('post_body', [
            'kind' => 'verdict',
            'input' => [
                'fields' => [
                    'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 100],
                ],
            ],
            'instructions' => 'Reject bad content.',
            'violations' => [
                'bad' => ['severity' => 'high', 'blocking' => true],
            ],
            'middleware' => [RecordingMiddleware::class],
        ]);

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        AiModerator::check('post_body')
            ->input(['body' => 'Hello'])
            ->run();

        $this->assertSame(1, RecordingMiddleware::$calls);
    }
}
