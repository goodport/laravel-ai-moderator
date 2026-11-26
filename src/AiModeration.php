<?php

namespace Goodport\AiModerator;

use BackedEnum;
use Goodport\AiModerator\Audit\ModerationAuditContext;
use Goodport\AiModerator\Results\Result;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class AiModeration
{
    public function __construct(private readonly AiModeratorManager $manager) {}

    public function text(
        string|BackedEnum $check,
        string $text,
        ?Model $actor = null,
        ?Model $subject = null,
        ?string $requestUrl = null,
        ?string $language = null,
    ): Result {
        return $this->run($check, ['text' => $text], $actor, $subject, $requestUrl, $language);
    }

    public function image(
        string|BackedEnum $check,
        string $imageUrl,
        ?Model $actor = null,
        ?Model $subject = null,
        ?string $requestUrl = null,
        ?string $language = null,
    ): Result {
        return $this->run($check, ['image' => $imageUrl], $actor, $subject, $requestUrl, $language);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function checklist(
        string|BackedEnum $check,
        array $input,
        ?Model $actor = null,
        ?Model $subject = null,
        ?string $requestUrl = null,
        ?string $language = null,
    ): Result {
        return $this->run($check, $input, $actor, $subject, $requestUrl, $language);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function classify(
        string|BackedEnum $check,
        array $input,
        ?Model $actor = null,
        ?Model $subject = null,
        ?string $requestUrl = null,
        ?string $language = null,
    ): Result {
        return $this->run($check, $input, $actor, $subject, $requestUrl, $language);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function run(
        string|BackedEnum $check,
        array $input,
        ?Model $actor = null,
        ?Model $subject = null,
        ?string $requestUrl = null,
        ?string $language = null,
    ): Result {
        $pending = $this->manager->check($this->key($check))->input($input);

        if ($language !== null) {
            $pending->language($language);
        }

        $auditContext = $this->auditContext($actor, $subject, $requestUrl);

        if ($auditContext !== null) {
            $pending->audit($auditContext);
        }

        return $pending->run();
    }

    private function key(string|BackedEnum $check): string
    {
        if ($check instanceof BackedEnum) {
            $value = $check->value;

            if (! is_string($value) || $value === '') {
                throw new InvalidArgumentException('A moderation check enum must have a non-empty string value.');
            }

            return $value;
        }

        if ($check === '') {
            throw new InvalidArgumentException('A moderation check key must be a non-empty string.');
        }

        return $check;
    }

    private function auditContext(
        ?Model $actor,
        ?Model $subject,
        ?string $requestUrl,
    ): ?ModerationAuditContext {
        if ($actor === null && $subject === null && $requestUrl === null) {
            return null;
        }

        return new ModerationAuditContext(
            actorType: $actor?->getMorphClass(),
            actorId: $actor === null ? null : (string) $actor->getKey(),
            subjectType: $subject?->getMorphClass(),
            subjectId: $subject === null ? null : (string) $subject->getKey(),
            requestUrl: $requestUrl,
        );
    }
}
