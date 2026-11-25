<?php

namespace Goodport\AiModerator\Tests\Feature;

use Goodport\AiModerator\Agents\ModerationAgent;
use Goodport\AiModerator\Facades\AiModerator;
use Goodport\AiModerator\Tests\TestCase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Events\PromptingAgent;

final class CorrelationTest extends TestCase
{
    /**
     * Correlation keys are available during the call and the previous context is restored afterwards.
     */
    public function test_context_is_set_and_restored(): void
    {
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
        ]);

        ModerationAgent::fake([
            ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
        ]);

        $captured = null;

        Event::listen(PromptingAgent::class, function () use (&$captured): void {
            $captured = Context::all();
        });

        Context::add('ai.check', 'outer');

        AiModerator::check('post_body')
            ->input(['body' => 'Hello'])
            ->run();

        $this->assertSame('post_body', $captured['ai.check']);
        $this->assertSame(1, $captured['ai.pass']);
        $this->assertArrayHasKey('ai.trace_id', $captured);
        $this->assertSame('outer', Context::get('ai.check'));
    }
}
