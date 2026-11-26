<?php

namespace Goodport\AiModerator\Checks;

use Closure;
use Goodport\AiModerator\Decision\DecisionRule;
use Goodport\AiModerator\Input\InputField;

final readonly class CheckConfig
{
    /**
     * @param  array<string, InputField>  $fields
     * @param  array<int, mixed>  $middleware
     */
    public function __construct(
        public string $key,
        public CheckKind $kind,
        public bool $enabled,
        public array $fields,
        public string $instructions,
        public ?string $context,
        public ?string $language,
        public string $role,
        public ?string $provider,
        public ?string $model,
        public ?int $timeout,
        public array $middleware,
        public ?string $agent,
        public ?SecondPassConfig $secondPass,
        public int $reasonMaxChars,
        public FailureMode $onError,
        public ?string $promptBuilder,
        public DecisionRule|string|null $decisionRule,
        public ?Closure $detailsSchema,
        public bool $strict = true,
    ) {}
}
