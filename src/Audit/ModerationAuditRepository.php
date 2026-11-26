<?php

namespace Goodport\AiModerator\Audit;

use Goodport\AiModerator\Models\AiModerationAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ModerationAuditRepository
{
    /**
     * @param  array{
     *     check?: string,
     *     outcome?: string,
     *     policy?: string,
     *     subject_type?: string,
     *     subject_id?: string,
     *     actor_type?: string,
     *     actor_id?: string,
     *     trace_id?: string,
     *     from?: \DateTimeInterface|string,
     *     to?: \DateTimeInterface|string
     * }  $filters
     */
    public function getPage(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = AiModerationAudit::query()->latest('id');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['check'])) {
            $query->forCheck($filters['check']);
        }

        if (isset($filters['outcome'])) {
            $query->forOutcome($filters['outcome']);
        }

        if (isset($filters['policy'])) {
            $query->forPolicy($filters['policy']);
        }

        if (isset($filters['trace_id'])) {
            $query->forTrace($filters['trace_id']);
        }

        if (isset($filters['subject_type'], $filters['subject_id'])) {
            $query->forSubject($filters['subject_type'], $filters['subject_id']);
        }

        if (isset($filters['actor_type'], $filters['actor_id'])) {
            $query->forActor($filters['actor_type'], $filters['actor_id']);
        }

        if (isset($filters['from'])) {
            $query->createdFrom($filters['from']);
        }

        if (isset($filters['to'])) {
            $query->createdTo($filters['to']);
        }
    }
}
