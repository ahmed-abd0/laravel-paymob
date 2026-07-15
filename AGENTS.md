# AGENTS.md

This file defines the rules for AI agents and contributors modifying this package. It applies to the entire repository unless a deeper `AGENTS.md` overrides a section.

## Mission

Maintain a small, predictable, Cashier-inspired Laravel integration for Paymob payments and subscriptions. Prefer correctness, explicit state handling, backward compatibility, and testability over abstraction or cleverness.

## Read First

Before changing code, read the files relevant to the task:

1. `README.md`
2. `docs/ARCHITECTURE.md`
3. `docs/API_REFERENCE.md`
4. `docs/SUBSCRIPTIONS.md` for billing changes
5. `docs/POSTMAN_MAPPING.md` for endpoint changes
6. `docs/MAINTENANCE.md`
7. Existing tests covering the affected area

Do not start from assumptions about Paymob behavior. Use the supplied official Postman collections or current official Paymob documentation as the contract, and record any discovered mismatch in `docs/POSTMAN_MAPPING.md`.

## Non-Negotiable Invariants

- Verified server callbacks are authoritative. Browser redirects never activate, renew, cancel, or otherwise finalize billing state.
- Every webhook must be authenticated when Paymob provides a verification mechanism.
- Webhook processing must remain idempotent and safe under duplicate and concurrent delivery.
- Card tokens, client secrets, and sensitive callback data must never be logged or stored unencrypted.
- Amounts are integers in the smallest currency unit. Never use floats for money.
- Remote IDs are treated as strings unless arithmetic is explicitly required.
- A canceled remote subscription is terminal. Re-enrollment creates a new local subscription record.
- Local state must be repairable through inquiry or synchronization APIs.
- Existing public methods, config keys, routes, database columns, events, and enum values are backward-compatible API surface.
- A successful HTTP response alone is not proof that a subscription payment succeeded. Persist state from verified callback or inquiry data.

## Architecture Boundaries

Keep responsibilities in their current layers:

- `src/Resources`: thin one-to-one Paymob endpoint wrappers. No Eloquent writes or business workflows.
- `src/Http`: authentication, request execution, retry policy, base URL selection, and response normalization.
- `src/Data`: request DTOs and local validation/serialization only.
- `src/Billing`: local orchestration, locking, persistence, and Cashier-style workflows.
- `src/Webhooks`: signature verification, idempotent callback processing, matching, and state transitions.
- `src/Models`: persistence, relationships, casts, and small state/query helpers.
- `src/Support`: immutable or near-immutable value/response helpers.
- `src/Console`: operational entry points that delegate to services.

Do not let controllers, commands, models, or resources duplicate business rules already owned by a manager or processor.

## Code Style

- Follow the Laravel Pint configuration in `pint.json`.
- Keep code compact, but never compress logic until it becomes hard to review.
- Avoid unnecessary blank lines and comments that merely repeat the code.
- Prefer constructor injection, typed properties, return types, enums, small methods, guard clauses, and final service/resource classes.
- Use one-line methods only for obvious delegation or trivial predicates.
- Use braces for control structures. Do not add deeply nested conditionals; return or throw early.
- Do not introduce repositories, interfaces, traits, base classes, or generic pipelines unless at least two real implementations require them.
- Do not add a dependency for behavior Laravel or PHP already provides cleanly.
- Never use `env()` outside configuration files.
- Never call the Paymob API directly outside `src/Resources` or `src/Http`.
- Do not leak raw Laravel HTTP responses. Return `PaymobResponse` or a documented higher-level object.
- Keep exception messages actionable and free of secrets.

## Public API and Backward Compatibility

Treat the following as public API:

- Methods reachable from `Paymob` or the `Paymob` facade
- `Billable` trait methods
- Public methods on package models, DTOs, builders, support classes, and enums
- Config keys and environment variables
- Published migrations and table/column names
- Named routes, commands, events, and exception types

For a public API change:

1. Prefer adding an optional argument or a new method over changing existing behavior.
2. Add regression tests for old behavior and tests for new behavior.
3. Update `README.md`, `docs/API_REFERENCE.md`, and `CHANGELOG.md`.
4. Mark intentional breaking changes clearly and reserve them for a major release.

Never silently rename a method, enum case, config key, event, route, or database column.

## Adding or Updating a Paymob Endpoint

Follow this order:

1. Verify method, path, authentication type, payload encoding, required fields, response shape, regional differences, and error behavior.
2. Update or create the resource method as a thin wrapper.
3. Add a DTO only when it prevents invalid requests or materially improves discoverability.
4. Add HTTP-fake tests asserting method, URL, authorization, encoding, and exact payload.
5. Add response and failure tests, including `401` refresh behavior for bearer endpoints when relevant.
6. Update `docs/POSTMAN_MAPPING.md` and `docs/API_REFERENCE.md`.
7. Add a `CHANGELOG.md` entry.

Do not implement undocumented payload transformations. Preserve unknown response fields in `PaymobResponse` or model `payload` fields for diagnostics.

## Subscription Rules

Subscription changes require extra care:

- Preserve the state graph documented in `docs/ARCHITECTURE.md`.
- Use `SubscriptionStatus::fromPaymob()` for remote state normalization.
- Protect creation and mutation workflows with appropriate locks and database transactions.
- Do not create two non-terminal subscriptions with the same billable and name.
- Preserve `reference` as the stable local correlation key.
- Keep callback matching deterministic: reference, intention, remote subscription ID, then initial transaction.
- Store remote snapshots needed for reconciliation, but redact secrets.
- Dispatch domain events only after a successful commit when configured.
- Add tests for duplicate callbacks, out-of-order callbacks, missing local matches, retries, cancellation, suspension, resumption, past-due recovery, and grace-period behavior when affected.

Any new state must define:

- Remote values that map to it
- Whether it is terminal
- `active()`, `valid()`, trial, and grace-period semantics
- Allowed transitions
- Callback behavior
- Synchronization behavior
- Migration/backfill needs
- Tests and documentation

## Webhook and Security Rules

- Verify signatures before persistence or mutation.
- Use constant-time comparison through `hash_equals()`.
- Keep raw request handling separate from business processing.
- Generate a stable deduplication identity from trusted callback properties.
- Lock the webhook record before processing and safely ignore completed duplicates.
- Do not catch and hide processing failures. Persist failure information safely and allow retry.
- Redact tokens, client secrets, payment tokens, full billing data, PAN data, and authorization material.
- Never weaken verification defaults to make a test pass.
- Never include real credentials or real customer data in fixtures.

Changes to `SignatureVerifier`, `WebhookController`, `WebhookProcessor`, payment method storage, or encrypted casts require explicit security regression tests.

## HTTP and Retry Rules

- Secret-key endpoints use `Authorization: Token ...`.
- Management endpoints use cached bearer tokens generated from the API key.
- Public endpoints must be intentionally identified as public.
- Automatic retries are allowed only for idempotent methods by default.
- Never retry a POST, PUT, capture, refund, void, or subscription mutation automatically unless Paymob supports an idempotency mechanism and the package implements it.
- Bearer endpoints may retry once after `401` with a forced token refresh.
- Timeouts, retry counts, and base URLs remain configurable.
- Convert failed responses into `ApiException` while preserving safe diagnostic context.

## Models and Migrations

- New model attributes require casts where applicable.
- Sensitive fields require encrypted casts.
- Add indexes for remote IDs, correlation keys, state filters, and webhook deduplication keys used by queries.
- Migrations must work on SQLite for tests and on supported Laravel databases.
- Never edit an already released migration to change production behavior. Add a new migration.
- Package tables and models must remain configurable through `paymob.php`.
- Avoid database-specific SQL unless guarded and tested.

## Testing Requirements

Every behavior change requires tests. A change is incomplete when it only changes implementation.

Minimum test expectations:

- Resource endpoint: request method, URL, auth scheme, payload/encoding, success response, and API error.
- DTO: valid serialization plus each important validation failure.
- Billing workflow: persistence, remote request, lock/transaction behavior, state update, and failure path.
- Webhook: valid signature, invalid signature, duplicate delivery, state transition, redaction, event dispatch, and retryable failure.
- Model helper: every affected status, date boundary, trial, and grace-period edge.
- Command: selection criteria, batching, output, and failure isolation.
- Bug fix: a regression test that fails before the fix.

Use Laravel HTTP fakes. Tests must not make real network calls. Use fake credentials and deterministic timestamps/UUIDs where assertions depend on them.

Prefer behavior assertions over private implementation assertions. Do not reduce test strictness or delete a test merely because a refactor broke it.

## Required Commands

Run from the repository root:

```bash
composer validate --strict
composer install
composer format
composer check
```

During development, target the affected tests first:

```bash
vendor/bin/pest tests/Unit/RelevantTest.php
vendor/bin/pest --filter="descriptive test name"
```

Before completion, run the full supported test matrix through CI. If a command cannot be run, state exactly which command was skipped and why.

## Documentation and Release Discipline

Update documentation in the same change as code:

- `README.md`: primary user workflow
- `docs/API_REFERENCE.md`: every public method and argument
- `docs/POSTMAN_MAPPING.md`: endpoint coverage and contract source
- `docs/SUBSCRIPTIONS.md`: lifecycle or billing behavior
- `docs/ARCHITECTURE.md`: invariant or ownership changes
- `CHANGELOG.md`: user-visible change

Use semantic versioning:

- Patch: backward-compatible bug fix
- Minor: backward-compatible feature or public method
- Major: intentional breaking change

## Prohibited Changes

Do not:

- Trust redirects as payment confirmation
- Disable HMAC or webhook-secret verification by default
- Store raw card tokens, client secrets, or sensitive callbacks unencrypted
- Use floating-point money
- Add hidden remote calls in model accessors, casts, or status predicates
- Make resources persist local state
- Make webhook handlers depend on callback delivery order
- Swallow Paymob failures and return fake success
- Retry non-idempotent mutations automatically
- Change public behavior without tests and documentation
- Add speculative abstractions or unrelated cleanup to a focused change
- Commit credentials, vendor files, coverage output, or generated secrets

## Definition of Done

An agent may report completion only when:

- The change follows the architecture boundaries.
- New and affected behavior is tested.
- Pint passes.
- The full Pest suite passes.
- Composer metadata validates.
- Documentation and changelog are updated.
- No secrets or customer data were introduced.
- Backward compatibility was preserved or the break was explicitly documented for a major release.
- The final response lists changed files, validation commands, and any remaining risk or unverified assumption.
