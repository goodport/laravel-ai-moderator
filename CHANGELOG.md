# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
