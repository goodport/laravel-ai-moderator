# Laravel AI Moderator

Declarative AI moderation checks for Laravel applications.

The package turns a small check declaration into a complete moderation pipeline: prompt building, structured JSON output, validation, a decision rule, an optional second opinion, and one uniform result object. It uses the official [Laravel AI SDK](https://github.com/laravel/ai) for provider calls.

The package knows nothing about your domain models. Your application describes what to check and supplies the data; the package handles the rest.

## Requirements

- PHP 8.3 or newer
- Laravel 12.62+ or 13.x
- `laravel/ai` 1.4+
- A configured AI provider in `config/ai.php`

## Installation

```bash
composer require goodport/laravel-ai-moderator
```

Laravel discovers the service provider automatically. Publish the configuration:

```bash
php artisan vendor:publish --tag=ai-moderator-config
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
$result->skipped();      // true for a disabled check
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

1. `->language('en')` on the call
2. the check's `language`
3. the profile's `language`
4. `defaults.language` (`auto` means "not set")
5. `App::getLocale()`

```php
$result = AiModerator::check('post_body')
    ->input(new PostInput($post))
    ->language('en')
    ->run();
```

The package appends a final instruction to write `reason` and human-readable messages in the resolved language. It does not keep separate prompt sets per locale. Use violation codes for your UI translations instead of relying on model text.

In queued jobs, `App::getLocale()` is the worker locale. Set the locale in the job or pass it explicitly with `->language()`.

## Profiles

Share platform context, model, middleware, and second-pass settings across checks:

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
- If provider or model is omitted, the first pass values are used.

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
- `agent` — a custom `Agent` that owns its instructions and schema. The package still builds the user prompt and attachments. The check's `prompt_builder` and middleware are ignored for that pass.
- `details_schema` — one level of additional structured output for class-based checks.

## Call logging and caching

The package does not store AI calls. Any `laravel/ai` agent middleware can be attached through the `middleware` option, for example to log calls or reuse a cached response:

```php
'defaults' => [
    'middleware' => [
        \App\Ai\Middleware\LogCalls::class,
    ],
],
```

The package only passes the middleware to the agent. It does not depend on a particular logging or caching implementation.

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
