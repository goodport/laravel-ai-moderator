# AGENTS.md

Technical description of `goodport/laravel-ai-moderator`.

## Purpose

The package turns declarative checks into a complete AI moderation pipeline on top of `laravel/ai`:

1. resolve a check declaration or a class-based check;
2. normalize input from an `InputResolver` or an array;
3. build the system and user prompts;
4. build a JSON schema for the check kind;
5. call the model through an internal agent with resilience middleware and retries;
6. parse and validate the structured response;
7. apply a decision rule;
8. optionally run a second pass and merge both results;
9. write a moderation audit row;
10. return one `Result`.

The package does not know domain models and does not query domain tables. It depends on `goodport/laravel-ai-journal` for AI call recording and replay, and on `goodport/laravel-ai-resilience` for rate limits and retries.

## Directory map

```text
config/ai-moderator.php        publishable configuration
database/migrations/           moderation audit table
src/
  AiModeratorServiceProvider.php
  AiModeratorManager.php
  Facades/AiModerator.php
  Agents/                      internal agent, strict agent, and agent runner
  Audit/                       audit context, recorder, read repository
  Checks/                      check contracts, definitions, pending check
  Config/                      config merge, model resolution, validation, registry
  Decision/                    decision rules, merger, check runner
  Input/                       input resolver, normalization, truncation
  Journal/                     journal replay integration
  Models/                      moderation audit model
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
- `Laravel\Ai\Contracts\Agent` + `HasStructuredOutput` + `HasMiddleware` — a custom agent that owns its instructions, schema, and middleware.

`AiModeratorManager` is the only container binding that callers use through the `AiModerator` facade. Internal collaborators are constructed by the container or passed explicitly.

## Journal and resilience integration

- `JournalReplay` reads `ai-moderator.journal.replay` configuration.
- `AgentRunner` builds middleware in this order: journal replay, `RateLimitStep`, configured middleware. A replayed response therefore does not wait for or consume a rate limit.
- `CheckRunner` adds `ai.journal.ttl` and the configured scope to `Context` for each pass.
- `PendingCheck::fresh()` adds `ai.journal.fresh` for one run.
- `AgentRunner` calls `AgentCallRetry` around every `prompt()`. Each attempt is a separate journal invocation.
- `StrictModerationAgent` is selected when `strict` is enabled. It sends `#[Strict]` to the provider.
- Custom agents must implement `HasMiddleware`. The package does not inject replay or rate limiting into them; the agent must attach `ReplayRecordedStep` and `RateLimitStep` itself. The retry handler still wraps their `prompt()`.
- Journal logging itself is registered by the journal service provider and works for all agent calls.

## Moderation audit

- `PendingCheck::run()` writes a final audit row through `ModerationAuditRecorder` for every outcome, including `skipped` and thrown failures, unless the global switch is disabled.
- `ModerationAuditContext` carries policy, actor, subject, and request URL. A policy fills its own policy key.
- The audit keeps the selected `invocation_id`, both `first_invocation_id` and `second_invocation_id`, the final result fields, and the selected provider/model.
- Audit write failures are not swallowed. The table and migration are part of this package.
- `ModerationAuditRepository::getPage()` provides filters and pagination for an admin layer.
- A disabled check inside a policy is audited as `skipped` without being added to the report.

## Model roles

`ModelResolver` resolves the inherited provider/model/timeout for each check and second pass. At each level it applies `model_role`, then explicit `provider`, `model`, and `timeout`; check overrides profile overrides defaults. Roles are read from `ai-moderator.model_roles`, are exact names, and unknown or malformed roles throw `InvalidConfigurationException`.

## Global switch

`ai-moderator.enabled` is the top-level switch. When false, checks and policies return `skipped` before input resolution, provider calls, journal writes, and audit writes. It has priority over check and profile settings. The value is read only from package config, without an environment fallback.

## Rules

- The moderator depends on `goodport/laravel-ai-journal` for recording and replay and on `goodport/laravel-ai-resilience` for limits and retries. It exposes Laravel `Context` keys `ai.trace_id`, `ai.check`, `ai.pass`, and journal replay keys for correlation.
- A provider or response failure becomes `undecided` unless `on_error` is `throw`. It is never treated as approved.
- A missing required input field or an invalid configuration throws immediately.
- Configuration is validated on resolve, not on boot.
- The audit is the only moderator-owned persistent data. It is generic and does not know domain models.
- Public API is stable within the major version.

## Tests

```bash
composer test
composer format
```

Feature tests use `ModerationAgent::fake()` and `ModerationAgent::assertPrompted()`. The base test case uses an in-memory SQLite database for journal replay and moderation audits.
