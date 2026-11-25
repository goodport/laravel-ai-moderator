# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
