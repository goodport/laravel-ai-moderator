<?php

namespace Goodport\AiModerator\Policies;

use Goodport\AiModerator\Audit\ModerationAuditContext;
use Goodport\AiModerator\Audit\ModerationAuditRecorder;
use Goodport\AiModerator\Checks\PendingCheck;
use Goodport\AiModerator\Config\CheckRegistry;
use Goodport\AiModerator\Config\InvalidConfigurationException;
use Goodport\AiModerator\Decision\CheckRunner;
use Goodport\AiModerator\Decision\SecondPassMerger;
use Goodport\AiModerator\Input\InputNormalizer;
use Goodport\AiModerator\Input\InputResolver;
use Goodport\AiModerator\Results\Report;
use Goodport\AiModerator\Results\Result;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;

final class PolicyRunner
{
    public function __construct(
        private readonly Repository $config,
        private readonly CheckRegistry $registry,
        private readonly InputNormalizer $inputNormalizer,
        private readonly CheckRunner $runner,
        private readonly SecondPassMerger $merger,
        private readonly ModerationAuditRecorder $auditRecorder,
    ) {}

    public function run(
        string $policy,
        InputResolver|array $input,
        ?string $language,
        ?ModerationAuditContext $auditContext = null,
    ): Report {
        [$checks, $stopOnReject] = $this->definition($policy);

        $traceId = (string) Str::uuid();
        $auditContext = $auditContext?->withPolicy($policy) ?? new ModerationAuditContext(policy: $policy);
        $results = [];

        foreach ($checks as $check) {
            if (! $this->registry->has($check)) {
                throw InvalidConfigurationException::unknownCheck($check);
            }

            if (! $this->registry->isEnabled($check)) {
                $this->auditRecorder->record(
                    Result::skippedResult($check, $traceId),
                    $auditContext,
                    null,
                    null,
                );

                continue;
            }

            $result = (new PendingCheck(
                key: $check,
                registry: $this->registry,
                inputNormalizer: $this->inputNormalizer,
                runner: $this->runner,
                merger: $this->merger,
                auditRecorder: $this->auditRecorder,
                traceId: $traceId,
            ))
                ->input($input)
                ->language($language ?? 'auto')
                ->audit($auditContext)
                ->run();

            $results[$check] = $result;

            if ($stopOnReject && $result->failed()) {
                break;
            }
        }

        return new Report($results);
    }

    /**
     * @return array{0: array<int, string>, 1: bool}
     */
    private function definition(string $policy): array
    {
        $config = $this->config->get("ai-moderator.policies.{$policy}");

        if (! is_array($config)) {
            throw InvalidConfigurationException::invalidPolicy($policy, 'must be configured as an object.');
        }

        $unknown = array_diff(array_keys($config), ['checks', 'stop_on_reject']);

        if ($unknown !== []) {
            throw InvalidConfigurationException::invalidPolicy($policy, 'contains unknown keys.');
        }

        $checks = $config['checks'] ?? null;

        if (! is_array($checks) || $checks === []) {
            throw InvalidConfigurationException::invalidPolicy($policy, '`checks` must be a non-empty array.');
        }

        foreach ($checks as $check) {
            if (! is_string($check) || $check === '') {
                throw InvalidConfigurationException::invalidPolicy($policy, '`checks` must contain non-empty strings.');
            }
        }

        if (count($checks) !== count(array_unique($checks))) {
            throw InvalidConfigurationException::invalidPolicy($policy, '`checks` must not contain duplicates.');
        }

        $stopOnReject = $config['stop_on_reject'] ?? false;

        if (! is_bool($stopOnReject)) {
            throw InvalidConfigurationException::invalidPolicy($policy, '`stop_on_reject` must be a boolean.');
        }

        return [$checks, $stopOnReject];
    }
}
