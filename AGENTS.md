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

The package does not know domain models and does not query domain tables. It depends on `goodport/laravel-ai-journal`, which records AI calls and can replay successful steps.

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
  Journal/                     journal replay integration
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

## Journal integration

- `JournalReplay` reads `ai-moderator.journal.replay` configuration.
- `AgentRunner` appends `ReplayRecordedStep` to built-in `ModerationAgent` middleware when replay is enabled and the check is not in `always_fresh_checks`.
- `CheckRunner` adds `ai.journal.ttl` and the configured scope to `Context` for each pass.
- `PendingCheck::fresh()` adds `ai.journal.fresh` for one run.
- Custom agents supplied through the `agent` option keep their own middleware; automatic replay is not injected into them.
- Journal logging itself is registered by the journal service provider and works for all agent calls.

## Rules

- The moderator depends on `goodport/laravel-ai-journal` for recording and replay. It exposes Laravel `Context` keys `ai.trace_id`, `ai.check`, `ai.pass`, and journal replay keys for correlation.
- A provider or response failure becomes `undecided` unless `on_error` is `throw`. It is never treated as approved.
- A missing required input field or an invalid configuration throws immediately.
- Configuration is validated on resolve, not on boot.
- The moderator itself has no migrations, models, jobs, or UI; the journal dependency owns its tables and models.
- Public API is stable within the major version.

## Tests

```bash
composer test
composer format
```

Feature tests use `ModerationAgent::fake()` and `ModerationAgent::assertPrompted()`. Journal integration tests use an in-memory SQLite database.
