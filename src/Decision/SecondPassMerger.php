<?php

namespace Goodport\AiModerator\Decision;

use Goodport\AiModerator\Results\PassResult;
use Goodport\AiModerator\Results\ResultState;

final class SecondPassMerger
{
    public function merge(PassResult $first, PassResult $second, MergeStrategy $strategy): PassResult
    {
        return match ($strategy) {
            MergeStrategy::ApproveWins => $this->approveWins($first, $second),
            MergeStrategy::Strict => $this->strict($first, $second),
            MergeStrategy::SecondDecides => $second,
        };
    }

    private function approveWins(PassResult $first, PassResult $second): PassResult
    {
        if ($second->state === ResultState::Passed) {
            return $second;
        }

        if ($first->state === ResultState::Passed) {
            return $first;
        }

        if ($second->state === ResultState::Undecided) {
            return $second;
        }

        if ($first->state === ResultState::Undecided) {
            return $first;
        }

        return $second;
    }

    private function strict(PassResult $first, PassResult $second): PassResult
    {
        if ($second->state === ResultState::Rejected) {
            return $second;
        }

        if ($first->state === ResultState::Rejected) {
            return $first;
        }

        if ($second->state === ResultState::Undecided) {
            return $second;
        }

        if ($first->state === ResultState::Undecided) {
            return $first;
        }

        return $second;
    }
}
