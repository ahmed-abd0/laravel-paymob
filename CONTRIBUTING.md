# Contributing

Thank you for improving Laravel Paymob. Changes should remain small, testable, backward-compatible, and grounded in verified Paymob behavior.

## Development setup

```bash
git clone <repository-url>
cd laravel-paymob
composer install
cp .env.example .env
composer check
```

The test suite uses Orchestra Testbench and an in-memory SQLite database. It must not call real Paymob endpoints.

## Before changing code

Read `AGENTS.md`, `docs/ARCHITECTURE.md`, and the documentation for the affected feature. For endpoint work, confirm the request against an official Paymob collection or current official documentation.

Create focused branches such as:

```text
feature/add-subscription-endpoint
fix/duplicate-webhook-transition
docs/explain-saved-card-flow
```

## Implementation expectations

- Keep resource classes thin.
- Put orchestration in billing or webhook services.
- Preserve public APIs unless preparing a major release.
- Keep money in integer minor units.
- Keep secrets encrypted and redacted.
- Add a regression test for every bug fix.
- Add request-contract tests for every endpoint change.
- Update documentation in the same pull request.

## Local quality checks

```bash
composer validate --strict
composer format
composer check
```

Useful targeted commands:

```bash
vendor/bin/pest tests/Unit/MoneyTest.php
vendor/bin/pest --filter="verifies transaction callback hmac"
vendor/bin/pint src tests
```

`composer format` modifies files. `composer check` is read-only and runs formatting validation plus tests.

## Pull requests

A pull request should contain one coherent change. Complete `.github/pull_request_template.md` and include:

- The Paymob contract or behavior being implemented
- Public API impact
- Security and subscription-state impact
- Tests added
- Documentation updated
- Commands actually run
- Any unverified regional or account-specific behavior

Do not combine endpoint additions, broad formatting, migration redesign, and unrelated refactors in one pull request.

## Commits

Use clear imperative messages:

```text
Add subscription card synchronization
Fix duplicate transaction webhook handling
Document MOTO payment requirements
```

## Releases

Update `CHANGELOG.md` before release. Follow semantic versioning and verify the GitHub Actions matrix across supported PHP and Laravel versions. Never publish a release from a working tree with uncommitted generated changes.

## Security reports

Do not open public issues for exploitable vulnerabilities or leaked credentials. Follow `SECURITY.md`.
