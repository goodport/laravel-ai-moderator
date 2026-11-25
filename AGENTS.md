# AGENTS.md

Technical description of `goodport/laravel-ai-moderator`.

## Purpose

The package turns declarative checks into a complete AI moderation pipeline on top of `laravel/ai`:

1. resolve a check declaration or a class-based check;
2. normalize input from an `InputResolver` or an array;
3. build the system and user prompts;
4. build a JSON schema for the check kind;
5. call the model through an internal agent;
6. parse and validate the structured response;
7. apply a decision rule;
8. optionally run a second pass and merge both results;
9. return one `Result`.

The package does not know domain models, does not query the database, and does not persist AI calls.

## Directory map

```text
config/ai-moderator.php        publishable configuration
src/
  AiModeratorServiceProvider.php
  AiModeratorManager.php
  Facades/AiModerator.php
  Agents/                      internal agent and agent runner
  Checks/                      check contracts, definitions, pending check
  Config/                      config merge, validation, registry
  Decision/                    decision rules, merger, check runner
  Input/                       input resolver, normalization, truncation
  Policies/                    policy runner and pending policy
  Prompt/                      prompt contracts and default builder
  Responses/                   structured response parsing
  Results/                     Result, Report, Violation, metadata
  Schema/                      JSON schema builder
tests/
  Unit/                        pure logic and config resolution
  Feature/                     facade-level flows with fake agents
  Fixtures/                    custom check/agent/builder/rule fixtures
```

## Extension seams

- `Checks\Check` — a class that returns a `CheckDefinition`.
- `Input\InputResolver` — supplies named fields for a check.
- `Prompt\PromptBuilder` — custom system prompt, user prompt, and attachments.
- `Decision\DecisionRule` — custom decision logic.
- `Laravel\Ai\Contracts\Agent` + `HasStructuredOutput` — a custom agent that owns its instructions and schema.

`AiModeratorManager` is the only container binding that callers use through the `AiModerator` facade. Internal collaborators are constructed by the container or passed explicitly.

## Rules

- The moderator never depends on external logging or caching packages. It exposes Laravel `Context` keys `ai.trace_id`, `ai.check`, and `ai.pass` so any listener can correlate calls.
- A provider or response failure becomes `undecided` unless `on_error` is `throw`. It is never treated as approved.
- A missing required input field or an invalid configuration throws immediately.
- Configuration is validated on resolve, not on boot.
- The package has no migrations, models, jobs, or UI.
- Public API is stable within the major version.

## Tests

```bash
composer test
composer format
```

Feature tests use `ModerationAgent::fake()` and `ModerationAgent::assertPrompted()`. They do not use a database.
