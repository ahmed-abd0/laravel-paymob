# Testing Strategy

## Goals

The suite must prove remote request correctness, subscription lifecycle safety, callback security, local persistence, and backward compatibility without contacting Paymob.

## Test groups

### Unit tests

Use for:

- Money and value helpers
- Enums and remote-state mapping
- DTO validation and serialization
- Signature construction and verification
- Model status/date predicates
- Response helpers

### Feature/package tests

Use Orchestra Testbench for:

- Service provider registration
- Config publishing assumptions
- Routes and controllers
- Migrations and model relationships
- HTTP resource calls through Laravel HTTP fakes
- Billing workflows
- Commands
- Events and database transactions

### Contract fixtures

Store sanitized callback or API response fixtures under `tests/Fixtures` when a payload is too large to keep readable in a test. Fixtures must:

- Contain no real customer or credential data
- State their source and collection/API version in a nearby README
- Preserve fields important to signature order and state mapping
- Be immutable unless the upstream contract changed

## Naming

Use behavior-first descriptions:

```php
it('refreshes the bearer token once after an unauthorized response');
it('does not activate a subscription from a browser redirect');
it('processes a duplicate transaction callback only once');
it('keeps a canceled subscription valid until the grace period ends');
```

Avoid names tied only to implementation, such as `calls private method` or `sets property x`.

## HTTP tests

Use `Http::fake()` and assert:

- Exact HTTP method
- Final URL and region base URL
- Correct authorization scheme
- Query/JSON/form/multipart encoding
- Required payload values
- User agent when relevant
- Failure conversion to `ApiException`
- Token refresh behavior on management API `401`
- No retry for non-idempotent methods

Always call `Http::preventStrayRequests()` in a shared test setup once the existing suite is ready for it.

## Database tests

Test against SQLite in memory for fast package feedback. Keep migrations portable. For database-sensitive migrations or locking behavior, add CI jobs against the databases the package officially claims to support before documenting that support.

Use `travelTo()` or `freezeTime()` for trials, grace periods, expirations, retry dates, and retention tests.

## Webhook tests

Webhook tests should cover a matrix rather than one happy path:

| Case | Expected result |
|---|---|
| Valid transaction HMAC | Accepted and processed |
| Invalid HMAC | Rejected before mutation |
| Duplicate payload | One mutation and one event |
| First processing fails | Stored as failed and retryable |
| Retry succeeds | Marked processed once |
| Unknown subscription | Explicit failure, no guessed match |
| Sensitive data present | Encrypted or redacted |
| Success after `past_due` | Returns to `active` |
| Older failure after newer success | Does not incorrectly downgrade state |

## Subscription tests

At minimum, cover:

- New checkout
- Duplicate named subscription rejection
- Expired incomplete checkout replacement
- Activation from verified callback
- Trial validity
- Grace-period boundary
- Failed renewal and recovery
- Suspend/resume
- Cancel and re-enroll
- Transaction synchronization
- Payment-method synchronization
- Remote/API failure recovery
- Concurrent creation protection

## Commands

```bash
composer test
composer lint
composer check
```

Targeted execution:

```bash
vendor/bin/pest tests/Feature/Subscriptions/CreateSubscriptionTest.php
vendor/bin/pest --filter="duplicate callback"
```

Optional local coverage report:

```bash
composer test:coverage
```

Coverage percentage is a signal, not a substitute for lifecycle and failure-path assertions. New critical billing and webhook behavior should be fully exercised even when the repository-wide percentage is lower.

## Regression policy

Every fixed defect must add a test that reproduces the defect before the fix. Do not weaken or remove the test in a later refactor unless the supported behavior intentionally changes in a major release.
