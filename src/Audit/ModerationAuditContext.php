<?php

namespace Goodport\AiModerator\Audit;

final readonly class ModerationAuditContext
{
    public function __construct(
        public ?string $policy = null,
        public ?string $actorType = null,
        public ?string $actorId = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?string $requestUrl = null,
    ) {}

    public function withPolicy(string $policy): self
    {
        return new self(
            policy: $policy,
            actorType: $this->actorType,
            actorId: $this->actorId,
            subjectType: $this->subjectType,
            subjectId: $this->subjectId,
            requestUrl: $this->requestUrl,
        );
    }

    public function withActor(string $type, string $id): self
    {
        return new self(
            policy: $this->policy,
            actorType: $type,
            actorId: $id,
            subjectType: $this->subjectType,
            subjectId: $this->subjectId,
            requestUrl: $this->requestUrl,
        );
    }

    public function withSubject(string $type, string $id): self
    {
        return new self(
            policy: $this->policy,
            actorType: $this->actorType,
            actorId: $this->actorId,
            subjectType: $type,
            subjectId: $id,
            requestUrl: $this->requestUrl,
        );
    }

    public function withRequestUrl(string $requestUrl): self
    {
        return new self(
            policy: $this->policy,
            actorType: $this->actorType,
            actorId: $this->actorId,
            subjectType: $this->subjectType,
            subjectId: $this->subjectId,
            requestUrl: $requestUrl,
        );
    }
}
