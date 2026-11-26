<?php

namespace Goodport\AiModerator\Audit;

use Goodport\AiModerator\Models\AiModerationAudit;
use Goodport\AiModerator\Results\PassResult;
use Goodport\AiModerator\Results\Result;
use Goodport\AiModerator\Results\Violation;
use Throwable;

final class ModerationAuditRecorder
{
    public function record(
        Result $result,
        ?ModerationAuditContext $context,
        ?PassResult $firstPass,
        ?PassResult $secondPass,
    ): void {
        $audit = $this->base($result->meta->traceId, $result->check, $context, $firstPass, $secondPass);

        $audit->invocation_id = $result->meta->invocationId;
        $audit->outcome = $result->state->value;
        $audit->reason = $result->reason;
        $audit->violations = array_map(
            fn (Violation $violation): array => $violation->toArray(),
            $result->violations,
        );
        $audit->label = $result->label;
        $audit->answers = $result->answers;
        $audit->details = $result->details;
        $audit->provider = $result->meta->provider;
        $audit->model = $result->meta->model;
        $audit->pass = $result->meta->pass;
        $audit->error = $result->meta->error;
        $audit->meta = $result->meta->toArray();

        $audit->save();
    }

    public function recordFailure(
        string $check,
        string $traceId,
        Throwable $exception,
        ?ModerationAuditContext $context,
        ?PassResult $firstPass,
        ?PassResult $secondPass,
    ): void {
        $audit = $this->base($traceId, $check, $context, $firstPass, $secondPass);

        $audit->outcome = 'undecided';
        $audit->reason = null;
        $audit->violations = [];
        $audit->label = null;
        $audit->answers = [];
        $audit->details = [];
        $audit->provider = $secondPass?->meta->provider ?? $firstPass?->meta->provider;
        $audit->model = $secondPass?->meta->model ?? $firstPass?->meta->model;
        $audit->pass = $secondPass?->meta->pass ?? $firstPass?->meta->pass ?? 0;
        $audit->error = $exception::class.': '.$exception->getMessage();
        $audit->meta = null;

        $audit->save();
    }

    private function base(
        string $traceId,
        string $check,
        ?ModerationAuditContext $context,
        ?PassResult $firstPass,
        ?PassResult $secondPass,
    ): AiModerationAudit {
        $audit = new AiModerationAudit;

        $audit->trace_id = $traceId;
        $audit->invocation_id = $secondPass?->meta->invocationId ?? $firstPass?->meta->invocationId;
        $audit->first_invocation_id = $firstPass?->meta->invocationId;
        $audit->second_invocation_id = $secondPass?->meta->invocationId;
        $audit->policy = $context?->policy;
        $audit->check = $check;
        $audit->subject_type = $context?->subjectType;
        $audit->subject_id = $context?->subjectId;
        $audit->actor_type = $context?->actorType;
        $audit->actor_id = $context?->actorId;
        $audit->duration_ms = ($firstPass?->meta->durationMs ?? 0) + ($secondPass?->meta->durationMs ?? 0);
        $audit->request_url = $context?->requestUrl;

        return $audit;
    }
}
