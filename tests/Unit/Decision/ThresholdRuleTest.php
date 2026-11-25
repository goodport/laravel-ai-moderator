<?php

namespace Goodport\AiModerator\Tests\Unit\Decision;

use Goodport\AiModerator\Checks\CheckConfig;
use Goodport\AiModerator\Checks\CheckKind;
use Goodport\AiModerator\Checks\FailureMode;
use Goodport\AiModerator\Checks\VerdictCheckDefinition;
use Goodport\AiModerator\Decision\ThresholdRule;
use Goodport\AiModerator\Responses\InvalidResponseException;
use Goodport\AiModerator\Responses\ParsedResponse;
use Goodport\AiModerator\Results\ResultState;
use Goodport\AiModerator\Tests\TestCase;

final class ThresholdRuleTest extends TestCase
{
    /**
     * The threshold rejects a value above the boundary.
     */
    public function test_rejects_above_threshold(): void
    {
        $decision = (new ThresholdRule('score', 0.9))->decide(
            $this->check(),
            new ParsedResponse(['score' => 0.95, 'reason' => 'High similarity.']),
        );

        $this->assertSame(ResultState::Rejected, $decision->state);
        $this->assertCount(1, $decision->violations);
    }

    /**
     * A value below the threshold passes.
     */
    public function test_passes_below_threshold(): void
    {
        $decision = (new ThresholdRule('score', 0.9))->decide(
            $this->check(),
            new ParsedResponse(['score' => 0.5, 'reason' => 'Low similarity.']),
        );

        $this->assertSame(ResultState::Passed, $decision->state);
    }

    /**
     * A non-numeric value is an invalid response.
     */
    public function test_non_numeric_value_throws(): void
    {
        $this->expectException(InvalidResponseException::class);

        (new ThresholdRule('score', 0.9))->decide(
            $this->check(),
            new ParsedResponse(['score' => 'high', 'reason' => 'Broken.']),
        );
    }

    private function check(): VerdictCheckDefinition
    {
        return new VerdictCheckDefinition(
            new CheckConfig(
                key: 'check',
                kind: CheckKind::Verdict,
                enabled: true,
                fields: [],
                instructions: 'Check.',
                context: null,
                language: null,
                role: 'Role.',
                provider: null,
                model: null,
                timeout: null,
                middleware: [],
                agent: null,
                secondPass: null,
                reasonMaxChars: 100,
                onError: FailureMode::Undecided,
                promptBuilder: null,
                decisionRule: null,
                detailsSchema: null,
            ),
            [],
        );
    }
}
