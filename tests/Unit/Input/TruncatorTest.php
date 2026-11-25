<?php

namespace Goodport\AiModerator\Tests\Unit\Input;

use Goodport\AiModerator\Input\TruncationStrategy;
use Goodport\AiModerator\Input\Truncator;
use Goodport\AiModerator\Tests\TestCase;

final class TruncatorTest extends TestCase
{
    /**
     * Short text is not changed.
     */
    public function test_keeps_short_text(): void
    {
        $truncator = new Truncator;

        $this->assertSame('short', $truncator->truncate('short', 10, TruncationStrategy::Head));
    }

    /**
     * The head strategy keeps only the beginning.
     */
    public function test_keeps_head_only(): void
    {
        $truncator = new Truncator;

        $this->assertSame('abcdef', $truncator->truncate('abcdefghij', 6, TruncationStrategy::Head));
    }

    /**
     * The head_tail strategy keeps the beginning and the end.
     */
    public function test_keeps_head_and_tail(): void
    {
        $truncator = new Truncator;

        $this->assertSame('abc hij', $truncator->truncate('abcdefghij', 7, TruncationStrategy::HeadTail));
    }

    /**
     * The none strategy does not truncate long text.
     */
    public function test_none_keeps_everything(): void
    {
        $truncator = new Truncator;

        $this->assertSame('abcdefghij', $truncator->truncate('abcdefghij', 3, TruncationStrategy::None));
    }

    /**
     * head_tail does not exceed the limit for an even length.
     */
    public function test_head_and_tail_does_not_exceed_limit(): void
    {
        $truncator = new Truncator;

        $truncated = $truncator->truncate('abcdefghij', 8, TruncationStrategy::HeadTail);

        $this->assertLessThanOrEqual(8, mb_strlen($truncated));
    }

    /**
     * Truncation works with multibyte characters.
     */
    public function test_truncates_multibyte_text(): void
    {
        $truncator = new Truncator;

        $this->assertSame('приве', $truncator->truncate('привет', 5, TruncationStrategy::Head));
    }
}
