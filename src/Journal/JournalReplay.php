<?php

namespace Goodport\AiModerator\Journal;

use Goodport\AiJournal\Journal\Replay\ReplayRecordedStep;
use Goodport\AiModerator\Checks\CheckDefinition;
use Goodport\AiModerator\Config\InvalidConfigurationException;
use Illuminate\Contracts\Config\Repository;

final class JournalReplay
{
    private bool $enabled;

    private int $ttl;

    private ?string $scope;

    /** @var array<int, string> */
    private array $alwaysFreshChecks;

    public function __construct(private readonly Repository $config)
    {
        $replayEnabled = $this->config->get('ai-moderator.journal.replay.enabled', true);
        $ttl = $this->config->get('ai-moderator.journal.replay.ttl', 86400);
        $scope = $this->config->get('ai-moderator.journal.replay.scope');
        $alwaysFresh = $this->config->get('ai-moderator.journal.replay.always_fresh_checks', []);

        if (! is_bool($replayEnabled)) {
            throw InvalidConfigurationException::invalidSetting('ai-moderator.journal.replay.enabled', 'must be a boolean.');
        }

        if (! is_int($ttl) || $ttl < 0) {
            throw InvalidConfigurationException::invalidSetting('ai-moderator.journal.replay.ttl', 'must be an integer greater than or equal to 0.');
        }

        if ($scope !== null && (! is_string($scope) || $scope === '')) {
            throw InvalidConfigurationException::invalidSetting('ai-moderator.journal.replay.scope', 'must be null or a non-empty string.');
        }

        if (! is_array($alwaysFresh) || ! array_is_list($alwaysFresh)) {
            throw InvalidConfigurationException::invalidSetting('ai-moderator.journal.replay.always_fresh_checks', 'must be a list of check keys.');
        }

        foreach ($alwaysFresh as $check) {
            if (! is_string($check) || $check === '') {
                throw InvalidConfigurationException::invalidSetting('ai-moderator.journal.replay.always_fresh_checks', 'must contain non-empty check keys.');
            }
        }

        $this->enabled = $replayEnabled && $ttl > 0;
        $this->ttl = $ttl;
        $this->scope = $scope;
        $this->alwaysFreshChecks = $alwaysFresh;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array<int, class-string>
     */
    public function middlewareFor(CheckDefinition $check): array
    {
        if (! $this->enabled || in_array($check->key(), $this->alwaysFreshChecks, true)) {
            return [];
        }

        return [ReplayRecordedStep::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        if (! $this->enabled) {
            return [];
        }

        $context = ['ai.journal.ttl' => $this->ttl];

        if ($this->scope !== null) {
            $context['ai.journal.scope'] = $this->scope;
        }

        return $context;
    }
}
