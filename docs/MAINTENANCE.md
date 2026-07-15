# Package Maintenance Guide

This guide gives maintainers and AI agents a repeatable process for safely updating the package.

## 1. Classify the change

Choose the smallest matching category:

| Change | Main area | Required tests |
|---|---|---|
| New Paymob endpoint | `src/Resources` | HTTP contract and failure tests |
| Existing payload/response change | Resource + DTO/support | Regression and serialization tests |
| Subscription workflow | `src/Billing` | Persistence, locking, state, and failure tests |
| Callback format/state change | `src/Webhooks` | Signature, idempotency, matching, transition tests |
| Local query/helper | `src/Models` | Status/date boundary tests |
| Configuration | `config/paymob.php` | Default and override tests |
| Schema change | `database/migrations` | Migration and model persistence tests |
| Laravel/PHP support | Composer + CI | Full version matrix |
| Documentation only | `README.md`/`docs` | Link/example review |

Avoid changing multiple categories unless the feature genuinely crosses those boundaries.

## 2. Establish the remote contract

For Paymob API work, record:

- Region or regions
- HTTP method and path
- Authentication: secret token, bearer token, or public
- JSON, query, form, or multipart encoding
- Required and optional fields
- Money units and currency restrictions
- Success status and important response fields
- Error statuses and retry safety
- Callback or reconciliation implications

Update `docs/POSTMAN_MAPPING.md` when the package's understanding changes. Do not infer a field from a dashboard screenshot when an official request example is available.

## 3. Choose the correct layer

### Endpoint wrapper

Add a method under `src/Resources`. The method should normally do only three things:

1. Build the documented path.
2. Normalize a DTO or simple arguments into the remote payload.
3. Call the correct `PaymobHttpClient` authentication method.

### DTO

Add or extend a class under `src/Data` when callers would otherwise repeatedly build a complex payload or when local validation can prevent an invalid remote request.

DTO rules:

- Constructor arguments represent meaningful public fields.
- Fluent methods may add optional collections or metadata.
- `toArray()` emits only the remote contract.
- Validation failures throw an actionable local exception.
- DTOs do not call HTTP, Eloquent, config writes, events, or queues.

### Billing workflow

Use `src/Billing` when remote calls must coordinate local models, locks, transactions, or subscription semantics.

### Webhook behavior

Use `src/Webhooks` for callback verification, deduplication, correlation, redaction, persistence, and events. Keep the HTTP controller thin.

## 4. Implement tests before declaring completion

### Resource request example

```php
use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Facades\Paymob;

it('sends the documented request', function () {
    config()->set('paymob.keys.secret', 'test-secret');
    Http::fake(['*/v1/example/*' => Http::response(['id' => 10], 201)]);

    $response = Paymob::example()->create(['amount_cents' => 1000]);

    expect($response->status())->toBe(201);
    Http::assertSent(fn($request) =>
        $request->method() === 'POST' &&
        $request->hasHeader('Authorization', 'Token test-secret') &&
        $request['amount_cents'] === 1000
    );
});
```

Also test a failed remote response and exact encoding where the endpoint uses form or multipart data.

### Subscription workflow checklist

Test all affected items:

- A local record is created or updated correctly.
- The stable `reference` is sent to Paymob.
- Duplicate active/incomplete records are rejected.
- Locks prevent concurrent duplication.
- A remote failure leaves recoverable local diagnostic state.
- Verified success activates the subscription.
- Failed renewal maps to `past_due`.
- Recovered renewal maps back to `active`.
- Cancel, suspend, resume, expiration, trial, and grace periods remain correct.

### Webhook checklist

- Valid signature accepted
- Missing or invalid signature rejected
- Duplicate callback returns safely without a second mutation
- Concurrent processing cannot apply twice
- Unknown local correlation fails visibly and remains retryable
- Secret fields are redacted or encrypted
- Events dispatch once and after commit
- Out-of-order delivery converges to the correct state

## 5. Handle migrations safely

For a new column or index:

1. Add a new migration; never rewrite a released migration.
2. Make rollout order safe for applications upgrading without downtime where practical.
3. Add casts/fill behavior after the column exists.
4. Define a backfill if old rows require a value.
5. Test fresh migration and upgrade assumptions.
6. Document custom-model impact.

Use nullable columns during staged rollouts when old package and new package versions may run briefly together.

## 6. Keep the public API synchronized

When adding a public method, update:

- `src/Paymob.php` if it exposes a new resource or manager
- The facade PHPDoc if facade annotations are introduced
- `docs/API_REFERENCE.md`
- The relevant README workflow
- `CHANGELOG.md`

Examples in documentation must use actual method names and parameter shapes from the source.

## 7. Run validation

```bash
composer validate --strict
composer format
composer check
```

Then confirm CI passes on every supported PHP/Laravel pair. For callback and subscription changes, run the relevant test file repeatedly to catch hidden time/order dependencies.

## 8. Review risk before merge

Answer these questions:

- Could this charge twice?
- Could this activate access before verified payment?
- Could a duplicate callback produce a second event or mutation?
- Could callbacks arriving in another order corrupt state?
- Could a secret be logged, serialized, or stored in plain text?
- Could a retry repeat a non-idempotent request?
- Does an old application using the public API still work?
- Can inquiry/sync repair a missed callback?
- Are region-specific assumptions documented?

A "yes" or "unknown" requires more design work or an explicit documented limitation.

## 9. Release workflow

1. Merge only with a green test matrix.
2. Move changelog entries into a versioned section.
3. Select patch, minor, or major using semantic versioning.
4. Tag the exact reviewed commit.
5. Publish from CI or a clean checkout.
6. Verify package installation in a small Laravel fixture application.
7. Monitor webhook and API errors after release.

## 10. Useful AI-agent task template

Use `docs/AI_AGENT_PROMPT.md` as the starting prompt. Give the agent the official Paymob contract or collection file and explicitly identify any account-specific behavior that cannot be tested publicly.
