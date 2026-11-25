<?php

namespace Goodport\AiModerator\Tests\Unit\Decision;

use Goodport\AiModerator\Decision\MergeStrategy;
use Goodport\AiModerator\Decision\SecondPassMerger;
use Goodport\AiModerator\Results\PassResult;
use Goodport\AiModerator\Results\ResultMeta;
use Goodport\AiModerator\Results\ResultState;
use Goodport\AiModerator\Tests\TestCase;

final class SecondPassMergerTest extends TestCase
{
    /**
     * The approve_wins merge table.
     */
    public function test_approve_wins(): void
    {
        $merger = new SecondPassMerger;

        $this->assertSame(ResultState::Passed, $this->state($merger, ResultState::Passed, ResultState::Passed, MergeStrategy::ApproveWins));
        $this->assertSame(ResultState::Passed, $this->state($merger, ResultState::Passed, ResultState::Rejected, MergeStrategy::ApproveWins));
        $this->assertSame(ResultState::Passed, $this->state($merger, ResultState::Passed, ResultState::Undecided, MergeStrategy::ApproveWins));
        $this->assertSame(ResultState::Passed, $this->state($merger, ResultState::Rejected, ResultState::Passed, MergeStrategy::ApproveWins));
        $this->assertSame(ResultState::Rejected, $this->state($merger, ResultState::Rejected, ResultState::Rejected, MergeStrategy::ApproveWins));
        $this->assertSame(ResultState::Undecided, $this->state($merger, ResultState::Rejected, ResultState::Undecided, MergeStrategy::ApproveWins));
    }

    /**
     * The strict merge table.
     */
    public function test_strict(): void
    {
        $merger = new SecondPassMerger;

        $this->assertSame(ResultState::Passed, $this->state($merger, ResultState::Passed, ResultState::Passed, MergeStrategy::Strict));
        $this->assertSame(ResultState::Rejected, $this->state($merger, ResultState::Passed, ResultState::Rejected, MergeStrategy::Strict));
        $this->assertSame(ResultState::Undecided, $this->state($merger, ResultState::Passed, ResultState::Undecided, MergeStrategy::Strict));
        $this->assertSame(ResultState::Rejected, $this->state($merger, ResultState::Rejected, ResultState::Passed, MergeStrategy::Strict));
        $this->assertSame(ResultState::Rejected, $this->state($merger, ResultState::Rejected, ResultState::Undecided, MergeStrategy::Strict));
    }

    /**
     * The second_decides strategy always uses the second pass.
     */
    public function test_second_decides(): void
    {
        $merger = new SecondPassMerger;

        $this->assertSame(ResultState::Passed, $this->state($merger, ResultState::Rejected, ResultState::Passed, MergeStrategy::SecondDecides));
        $this->assertSame(ResultState::Rejected, $this->state($merger, ResultState::Passed, ResultState::Rejected, MergeStrategy::SecondDecides));
    }

    private function state(SecondPassMerger $merger, ResultState $first, ResultState $second, MergeStrategy $strategy): ResultState
    {
        return $merger->merge(
            $this->pass($first, 1),
            $this->pass($second, 2),
            $strategy,
        )->state;
    }

    private function pass(ResultState $state, int $pass): PassResult
    {
        return new PassResult(
            state: $state,
            reason: null,
            violations: [],
            label: null,
            answers: [],
            details: [],
            meta: new ResultMeta('check', $pass, null, null, null, 0, null, 'trace'),
        );
    }
}
