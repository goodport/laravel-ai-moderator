# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.2.0] - 2026-11-26

### Added

- `AiModeration` application entry point for text, image, checklist, classification, and arbitrary input runs, with explicit actor, subject, request URL, and language.

## [2.1.0] - 2026-11-26

### Added

- Global `ai-moderator.enabled` switch; when disabled, checks and policies return `skipped` without provider calls, journal entries, or audit rows.
- Configurable `model_role` support in defaults, profiles, checks, and second passes, resolved from `ai-moderator.model_roles`.
- Explicit language coverage for both moderation passes and documented background-job behavior.

### Changed

- Profile `enabled` is now inherited by checks that do not override it.
- Provider and model defaults no longer use `env()` fallbacks in the package config.


## [2.0.0] - 2026-11-26

### Added

- `goodport/laravel-ai-resilience` dependency and automatic rate limiting for built-in agents.
- Automatic retries around every moderation `prompt()` call.
- `StrictModerationAgent` and the default `strict` setting.
- `ai_moderation_audits` table, audit model, writer, and filtered read repository.
- `PendingCheck::audit()` and `PendingPolicy::audit()` for actor, subject, policy, and request context.
- Audit rows for thrown failures and for skipped checks inside policies.

### Changed

- **Breaking:** every moderation run writes an audit row; the audit migration and database connection are now required.
- **Breaking:** custom agents must implement `HasMiddleware` and attach replay and rate limiting themselves.
- **Breaking:** strict structured output is enabled by default for built-in agents.
- Middleware order is now replay, rate limiter, then configured middleware.
- The package now owns the moderation audit migration and model.

## [1.1.0] - 2026-11-26

### Added

- `goodport/laravel-ai-journal` dependency with automatic journaling of moderation calls.
- Automatic replay of successful moderation steps, enabled by default for built-in agents.
- `journal.replay.always_fresh_checks` for checks that must always call the provider.
- `PendingCheck::fresh()` for a one-off forced provider call.
- Journal TTL and scope configuration, plus `ai.check`/`ai.pass` fingerprint separation.

### Changed

- Journal replay is now part of the default moderation flow.
- The package now persists AI calls through the journal dependency.

## [1.0.0] - 2026-11-25

### Added

- Declarative moderation checks with `verdict`, `checklist`, and `classify` kinds.
- Input resolvers, text truncation, and image attachments by URL, path, upload, or Laravel AI image.
- Structured JSON output built from the check definition.
- Configurable second pass with `approve_wins`, `strict`, and `second_decides` merge strategies.
- Named profiles for shared platform context, model, middleware, and second-pass settings.
- Response language resolution from the call, check, profile, defaults, or application locale.
- Policies that run several checks and return one report.
- `undecided` failure semantics with `on_error` set to `undecided` or `throw`.
- Class-based extension points: `Check`, `PromptBuilder`, `DecisionRule`, and custom agents.
- Optional `details` schema for class-based checks.
