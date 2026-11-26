# Laravel AI Moderator

Declarative AI moderation checks for Laravel applications.

The package turns a small check declaration into a complete moderation pipeline: prompt building, structured JSON output, validation, a decision rule, an optional second opinion, and one uniform result object. It uses the official [Laravel AI SDK](https://github.com/laravel/ai) for provider calls.

The package knows nothing about your domain models. Your application describes what to check and supplies the data; the package handles the rest.

## Requirements

- PHP 8.3 or newer
- Laravel 12.62+ or 13.x
- `laravel/ai` 1.4+
- A configured AI provider in `config/ai.php`
- A database connection for moderation audits

## Installation

```bash
composer require goodport/laravel-ai-moderator
```

Laravel discovers the service provider automatically. Run migrations and publish the configuration:

```bash
php artisan migrate
php artisan vendor:publish --tag=ai-moderator-config
```

## OpenRouter

OpenRouter uses the official OpenAI-compatible API:

- base URL: `https://openrouter.ai/api/v1`
- chat completions: `https://openrouter.ai/api/v1/chat/completions`

Configure it as the `openrouter` provider in `config/ai.php`. The SDK already defaults to this URL; the key belongs in `OPENROUTER_API_KEY` and must not be hardcoded.

```php
'openrouter' => [
    'driver' => 'openrouter',
    'key' => env('OPENROUTER_API_KEY'),
    'url' => env('OPENROUTER_URL', 'https://openrouter.ai/api/v1'),
],
```

## Quick start

Define a check in `config/ai-moderator.php`:

```php
'checks' => [
    'post_body' => [
        'kind' => 'verdict',
        'input' => [
            'fields' => [
                'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 4000],
            ],
        ],
        'instructions' => 'Reject profanity, contact details, and advertising.',
        'violations' => [
            'profanity' => ['severity' => 'high', 'blocking' => true, 'description' => 'Profanity or insults'],
            'contacts' => ['severity' => 'high', 'blocking' => true, 'description' => 'Phone numbers, emails, links'],
            'ads' => ['severity' => 'medium', 'blocking' => false, 'description' => 'Advertising'],
        ],
    ],
],
```

Run it:

```php
use Goodport\AiModerator\Facades\AiModerator;

$result = AiModerator::check('post_body')
    ->input(['body' => $post->body])
    ->run();

if ($result->passed() === false) {
    // Rejected: $result->reason(), $result->violations()
}

if ($result->passed() === null) {
    // Undecided: provider error, invalid response, or another failure.
    // The application decides what to do.
}
```

`Result` exposes:

```php
$result->state();        // passed | rejected | undecided | skipped
$result->passed();       // true | false | null
$result->failed();       // true only for rejected
$result->undecided();    // true when the AI could not produce a valid decision
$result->skipped();      // true for a disabled check or the global switch
$result->reason();
$result->violations();   // Violation[]: code, message, severity, blocking
$result->label();        // classify checks
$result->answers();      // checklist checks
$result->details();      // custom check payload
$result->meta();         // model, provider, pass, invocation id, duration, trace id, error
$result->toArray();
```

## Input resolvers

For anything beyond a simple array, implement `InputResolver`:

```php
use Goodport\AiModerator\Input\InputResolver;
use Laravel\Ai\Files\Image;

final class PostInput implements InputResolver
{
    public function __construct(private readonly Post $post) {}

    public function fields(): array
    {
        return [
            'body' => $this->post->body,
            'cover' => Image::fromUrl($this->post->coverUrl()),
        ];
    }
}
```

```php
$result = AiModerator::check('post_body')
    ->input(new PostInput($post))
    ->run();
```

The package never reads Eloquent attributes, relations, or storage by itself. Missing required fields throw `InvalidInputException` before any AI call.

## Check kinds

### Verdict

The model decides `approve` or `reject`. Rejections must include at least one blocking violation; approvals cannot include blocking violations. An inconsistent response becomes `undecided`.

### Checklist

The model answers every declared question. The package compares the answers with the expected values:

```php
'review_questions' => [
    'kind' => 'checklist',
    'input' => [
        'fields' => [
            'body' => ['type' => 'text', 'label' => 'Review', 'max_chars' => 10000],
        ],
    ],
    'instructions' => 'Answer the questions about the review.',
    'questions' => [
        'insults' => [
            'label' => 'Does it contain insults?',
            'values' => ['yes', 'no'],
            'expected' => 'no',
            'blocking' => true,
            'severity' => 'high',
        ],
        'ads' => [
            'label' => 'Does it contain advertising?',
            'values' => ['yes', 'no'],
            'expected' => 'no',
            'blocking' => false,
            'severity' => 'medium',
        ],
    ],
],
```

A blocking mismatch rejects the check. A non-blocking mismatch is returned as a warning in `violations()` and does not reject.

### Classify

The model returns one label from a fixed list:

```php
'age_rating' => [
    'kind' => 'classify',
    'input' => [
        'fields' => [
            'body' => ['type' => 'text', 'label' => 'Body', 'max_chars' => 30000, 'strategy' => 'head_tail'],
        ],
    ],
    'instructions' => 'Classify the content.',
    'labels' => ['0+', '6+', '12+', '16+', '18+'],
    'decision' => ['reject_on' => [], 'severity' => 'high'],
],
```

`reject_on` is the list of labels that reject the content. An empty list makes the check label-only.

## Language

The response language is resolved in this order:

1. `->language('en')` on the check or policy call
2. the check's `language`
3. the profile's `language`
4. `defaults.language` (`auto` means "not set")
5. the application locale
5. `App::getLocale()`

```php
$result = AiModerator::check('post_body')
    ->input(new PostInput($post))
    ->language('en')
    ->run();
```

The package appends a final instruction to write `reason` and human-readable messages in the resolved language. It does not keep separate prompt sets per locale. Use violation codes for your UI translations instead of relying on model text.

The same language is passed to the first and second pass. In queued jobs, `App::getLocale()` is the worker locale. Set the locale in the job or pass it explicitly with `->language()`; do not rely on the process locale.

## Global switch

`ai-moderator.enabled` is the top-level switch. It is read only from the package config and has no environment fallback.

When it is `false`, every check and policy returns `skipped` without a provider call, journal entry, or moderation audit row. This takes priority over `checks.*.enabled` and profile-level settings. When it is `true`, per-check enablement works as usual.

## Profiles

Share platform context, model, strict mode, middleware, and second-pass settings across checks:

```php
'profiles' => [
    'reviews' => [
        'context' => 'A platform where authors publish creative work and readers leave reviews.',
        'language' => 'auto',
        'model' => 'gpt-5-mini',
        'middleware' => [],
        'second_pass' => [
            'enabled' => true,
            'trigger' => 'on_reject',
            'merge' => 'approve_wins',
        ],
    ],
],

'checks' => [
    'review_quality' => [
        'profile' => 'reviews',
        'kind' => 'verdict',
        // ...
    ],
],
```

Merge order: `defaults` → `profile` → `check`. Middleware is merged; `second_pass` is merged deeply; `input` and kind-specific blocks belong to the check.

## Model roles

The package does not hardcode role names. The application supplies a role map from its own config:

```php
'ai-moderator.model_roles' => [
    'light' => ['provider' => 'openai', 'model' => 'gpt-5-mini', 'timeout' => 30],
    'standard' => ['provider' => 'openai', 'model' => 'gpt-5'],
    'vision' => ['provider' => 'openai', 'model' => 'gpt-5-vision'],
],
```

The map is normally filled from an application-level `ai-models.php`. The package only reads `ai-moderator.model_roles` and does not know that file.

Use `model_role` in `defaults`, a profile, or a check. Resolution works level by level:

1. start with inherited `provider`, `model`, and `timeout`;
2. apply `model_role` at the level;
3. apply explicit `provider`, `model`, and `timeout` at the same level;
4. move from defaults to profile to check.

Only fields that are present are overridden, so a role can provide the provider and an explicit model can replace only the model. A more specific level wins over a less specific one. Exact role names are required; an unknown role is a configuration error.

For `second_pass`, start from the final first-pass selection, apply `second_pass.model_role`, then explicit `second_pass.provider`, `second_pass.model`, and `second_pass.timeout`. Without any of them, the second pass uses the first-pass model. There is no global second-pass switch.

## Second opinion

```php
'second_pass' => [
    'enabled' => true,
    'trigger' => 'on_reject',      // on_reject | always
    'provider' => 'anthropic',
    'model' => 'claude-sonnet-4-5',
    // 'agent' => SecondOpinionAgent::class, // optional
    'merge' => 'approve_wins',     // approve_wins | strict | second_decides
],
```

- `on_reject` runs the second pass only after a rejection.
- `always` also runs after approvals.
- `undecided` never triggers a second pass.
- If provider, model, role, or timeout is omitted, the corresponding first-pass value is used.

Merge strategies:

| First | Second | `approve_wins` | `strict` | `second_decides` |
|---|---|---|---|---|
| pass | pass | pass | pass | pass |
| pass | reject | pass | reject | reject |
| pass | undecided | pass | undecided | undecided |
| reject | pass | pass | reject | pass |
| reject | reject | reject | reject | reject |
| reject | undecided | undecided | reject | undecided |

## Policies

Run several checks over one input and get one report:

```php
'policies' => [
    'post' => [
        'checks' => ['post_body', 'post_cover'],
        'stop_on_reject' => false,
    ],
],
```

```php
$report = AiModerator::policy('post')
    ->input(new PostInput($post))
    ->run();

$report->passed();      // true | false | null
$report->rejected();
$report->undecided();
$report->results();     // key => Result
$report->messages();    // key => reason or error
$report->violations();  // key => Violation[]
$report->toArray();
```

Disabled checks are omitted from the report. With `stop_on_reject`, the run stops after the first rejection.

## Errors and `undecided`

- Provider errors, timeouts, invalid structured output, and inconsistent decisions become `undecided`.
- `undecided` is never treated as approved.
- `Result::meta()->error` contains a diagnostic string.
- Set `on_error` to `throw` in defaults or on a check to rethrow the original exception instead.
- Configuration and input errors always throw.
- The resilience retry handler is applied automatically. It retries only temporary provider errors; a final failure still follows `on_error`.

## Custom checks

A check is either a declaration or a class implementing `Check`:

```php
use Goodport\AiModerator\Checks\Check;
use Goodport\AiModerator\Checks\CheckDefinition;

final class CustomCheck implements Check
{
    public function definition(): CheckDefinition
    {
        // Build a CheckConfig and a VerdictCheckDefinition,
        // ChecklistCheckDefinition, or ClassifyCheckDefinition.
    }
}
```

```php
'checks' => [
    'custom' => CustomCheck::class,
],
```

Extension points:

- `InputResolver` — named fields for the check.
- `PromptBuilder` — custom system prompt, user prompt, and attachments.
- `DecisionRule` — custom decision logic.
- `agent` — a custom `Agent` that owns its instructions and schema. It must implement `HasMiddleware`; the package builds the user prompt and attachments. The check's `prompt_builder` and middleware are ignored for that pass.
- `details_schema` — one level of additional structured output for class-based checks.

## Strict schema

Built-in moderation agents use `#[Strict]` and send the provider a strict JSON schema. This is the default. The parser still validates the response and rejects malformed or inconsistent data.

Set `strict` to `false` on a check or profile only for a provider that does not support strict structured output:

```php
'checks' => [
    'post_body' => [
        'strict' => false,
        // ...
    ],
],
```

A provider failure caused by an unsupported strict schema follows the normal `on_error` behavior. It is never treated as approved.

## Resilience, rate limits, and retries

The package depends on `goodport/laravel-ai-resilience`. Limits and retries are configured in `config/ai-resilience.php`; the moderator does not duplicate those settings.

```bash
php artisan vendor:publish --tag=ai-resilience-config
```

The built-in agent middleware order is:

1. journal replay;
2. `RateLimitStep`;
3. middleware from the check configuration.

A replayed response therefore returns before the limiter can wait or consume a limit. Every retry is a separate `prompt()` call and a separate journal invocation. The retry handler does not retry agents with tools or skills or approval continuations.

Custom agents supplied through `agent` must implement `HasMiddleware` and attach `ReplayRecordedStep` and `RateLimitStep` themselves. The retry handler still wraps their `prompt()` call. A custom agent without `HasMiddleware` is rejected during configuration.

## Moderation audit

Every completed moderation run writes one row to `ai_moderation_audits`, including skipped checks. The audit stores the final `Result`, not the raw model response. It keeps the final invocation and both pass invocation IDs when a second pass runs.

The package loads its migration automatically. It is required; there is no disabled audit mode and audit write failures are not swallowed.

Attach application context explicitly:

```php
use Goodport\AiModerator\Audit\ModerationAuditContext;

$result = AiModerator::check('post_body')
    ->audit(new ModerationAuditContext(
        actorType: 'user',
        actorId: (string) $user->id,
        subjectType: 'post',
        subjectId: (string) $post->id,
        requestUrl: request()->fullUrl(),
    ))
    ->input(['body' => $post->body])
    ->run();
```

The same `audit()` method is available on policies. A policy fills `policy` automatically.

Read audits through the model or the repository:

```php
use Goodport\AiModerator\Audit\ModerationAuditRepository;

$page = app(ModerationAuditRepository::class)->getPage([
    'check' => 'post_body',
    'outcome' => 'rejected',
    'actor_type' => 'user',
    'actor_id' => (string) $user->id,
    'from' => now()->subWeek(),
]);
```

Available filters: `check`, `outcome`, `policy`, `trace_id`, `subject_type`, `subject_id`, `actor_type`, `actor_id`, `from`, `to`.

## Journal and replay

The package depends on `goodport/laravel-ai-journal`. Composer installs both packages, Laravel registers both providers, and the journal migrations are loaded automatically. Every moderation call is recorded without application code.

Replay is enabled by default. For a built-in `ModerationAgent`, the package automatically attaches the journal replay middleware. When the same check receives the same fingerprint within the TTL, the successful step is reused without a provider call.

Configuration:

```php
'journal' => [
    'replay' => [
        'enabled' => true,
        'ttl' => 86400,
        'scope' => null,
        'always_fresh_checks' => [],
    ],
],
```

- `replay.enabled` controls automatic replay integration.
- `replay.ttl` is the number of seconds a stored response remains reusable. `0` disables replay.
- `replay.scope` isolates stored responses, for example per tenant. `null` keeps the application's `Context` scope.
- `always_fresh_checks` lists check keys that must always call the provider again.

To force a new request for one run, call `fresh()`:

```php
AiModerator::check('post_body')
    ->input(['body' => $body])
    ->fresh()
    ->run();
```

The journal fingerprint includes `ai.check` and `ai.pass`, so separate checks and the first/second passes do not reuse each other's responses.

Remote images are identified by their URL unless the journal owns a content hash. For files that can change at the same URL, version the URL or use `fresh()` for a forced check.

Custom agents must implement `HasMiddleware` and attach `ReplayRecordedStep` themselves. The journal still records their calls, but automatic replay is only attached to the built-in `ModerationAgent`.

The journal package also owns capture settings, redaction, cost calculation, retention, and its own read API. Publish its config when those settings need to be changed:

```bash
php artisan vendor:publish --tag=ai-journal-config
```

Any other `laravel/ai` agent middleware can still be attached through the check `middleware` option.

## Testing

The package uses `laravel/ai` fake agents:

```php
use Goodport\AiModerator\Agents\ModerationAgent;

ModerationAgent::fake([
    ['verdict' => 'approve', 'reason' => 'Fine.', 'violations' => []],
]);

$result = AiModerator::check('post_body')
    ->input(['body' => 'Hello'])
    ->run();

$this->assertTrue($result->passed());
```

Prompts and attachments can be asserted:

```php
use Laravel\Ai\Prompts\AgentPrompt;

ModerationAgent::assertPrompted(function (AgentPrompt $prompt): bool {
    return str_contains($prompt->prompt, 'Hello')
        && $prompt->attachments->count() === 1;
});
```

## Development

```bash
composer test
composer format
```

## License

MIT. See [LICENSE](LICENSE).
