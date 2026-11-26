<?php

namespace Goodport\AiModerator\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AiModerationAudit extends Model
{
    protected $table = 'ai_moderation_audits';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'violations' => 'array',
            'answers' => 'array',
            'details' => 'array',
            'meta' => 'array',
            'pass' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeForTrace(Builder $query, string $traceId): void
    {
        $query->where('trace_id', $traceId);
    }

    public function scopeForCheck(Builder $query, string $check): void
    {
        $query->where('check', $check);
    }

    public function scopeForOutcome(Builder $query, string $outcome): void
    {
        $query->where('outcome', $outcome);
    }

    public function scopeForPolicy(Builder $query, string $policy): void
    {
        $query->where('policy', $policy);
    }

    public function scopeForSubject(Builder $query, string $type, string $id): void
    {
        $query->where('subject_type', $type)->where('subject_id', $id);
    }

    public function scopeForActor(Builder $query, string $type, string $id): void
    {
        $query->where('actor_type', $type)->where('actor_id', $id);
    }

    public function scopeCreatedFrom(Builder $query, DateTimeInterface|string $from): void
    {
        $query->where('created_at', '>=', $from);
    }

    public function scopeCreatedTo(Builder $query, DateTimeInterface|string $to): void
    {
        $query->where('created_at', '<=', $to);
    }
}
