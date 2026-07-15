# AI Agent Update Prompt

Copy this prompt when asking an AI coding agent to modify the package. Replace the task and contract sections.

```text
You are updating the `paymob-community/laravel-paymob` Laravel package.

Read and obey these files before editing:
- AGENTS.md
- docs/ARCHITECTURE.md
- docs/MAINTENANCE.md
- docs/TESTING.md
- docs/API_REFERENCE.md
- docs/POSTMAN_MAPPING.md
- docs/SUBSCRIPTIONS.md when billing is affected

Task:
[Describe the exact feature, bug, or refactor.]

Verified Paymob contract:
[Attach or paste the official Postman request, official documentation, callback payload, method, path, authentication, and response examples. State the region and whether behavior is test/live/account-specific.]

Constraints:
- Preserve backward compatibility unless I explicitly approve a breaking release.
- Keep code compact and readable with minimal unnecessary blank lines.
- Respect the current architecture boundaries.
- Do not trust browser redirects as proof of payment.
- Keep webhook processing authenticated, idempotent, concurrent-safe, and order-tolerant.
- Keep money as integer minor units.
- Encrypt/redact tokens, secrets, card data, and sensitive callbacks.
- Never make real HTTP calls in tests.
- Do not add speculative abstractions or unrelated refactors.

Required workflow:
1. Inspect the affected source, tests, migrations, config, and docs.
2. Explain the current behavior and identify the smallest safe design.
3. Implement the change in the correct layer.
4. Add behavior-focused tests for success, failure, edge cases, and regression.
5. Update API_REFERENCE, relevant workflow docs, POSTMAN_MAPPING when applicable, and CHANGELOG.
6. Run composer validate --strict, composer format, and composer check.
7. Review for duplicate charges, incorrect state transitions, callback duplication/order, retry safety, secret exposure, and backward compatibility.

Return:
- A concise summary of behavior changed
- Exact files changed
- Tests added and what they prove
- Commands run with results
- Any assumption or behavior that could not be verified
- Any migration or upgrade action required by package users
```

## Prompt for reviewing an existing AI change

```text
Review this change as a payment-package maintainer. Use AGENTS.md as the acceptance standard.

Prioritize findings in this order:
1. Duplicate charge or non-idempotent retry risk
2. Unauthorized/forged webhook risk
3. Incorrect subscription state or access activation
4. Secret/card/customer-data exposure
5. Concurrency and out-of-order callback defects
6. Breaking public API or migration behavior
7. Incorrect Paymob request contract
8. Missing failure-path/regression tests
9. Maintainability and compact Laravel style

For every finding, cite the file and line, explain the real failure scenario, and propose the smallest safe fix. Do not approve based only on happy-path tests.
```
