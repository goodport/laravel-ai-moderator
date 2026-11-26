<?php

namespace Goodport\AiModerator\Support;

use Closure;
use Illuminate\Support\Facades\Context;

final class CorrelationContext
{
    /**
     * @var array<int, string>
     */
    private const KEYS = [
        'ai.trace_id',
        'ai.check',
        'ai.pass',
        'ai.journal.fresh',
        'ai.journal.ttl',
        'ai.journal.scope',
    ];

    /**
     * Run a callback with AI correlation keys and restore the previous context afterwards.
     *
     * @param  array<string, mixed>  $values
     */
    public static function run(array $values, Closure $callback): mixed
    {
        $previous = [];
        $existed = [];

        foreach (self::KEYS as $key) {
            $existed[$key] = Context::has($key);
            $previous[$key] = $existed[$key] ? Context::get($key) : null;
        }

        Context::add($values);

        try {
            return $callback();
        } finally {
            foreach (self::KEYS as $key) {
                if ($existed[$key]) {
                    Context::add($key, $previous[$key]);

                    continue;
                }

                Context::forget($key);
            }
        }
    }
}
