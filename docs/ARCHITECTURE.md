# Architecture

## Layers

### API resources
Thin wrappers around Paymob endpoints:

- `Intentions`
- `SubscriptionPlans`
- `Subscriptions`
- `SavedCards`
- `Transactions`
- `Payments`
- `QuickLinks`

All requests pass through `PaymobHttpClient`. Secret-key APIs use `Authorization: Token ...`; management APIs use a cached bearer token generated from the API key.

### Billing layer
The Cashier-style layer consists of:

- `Billable`
- `SubscriptionBuilder`
- `PlanManager`
- `SubscriptionManager`
- local Eloquent models

It stores the local state required for authorization and customer account screens without requiring a Paymob request on every page load.

### Webhook layer
`WebhookController` validates callbacks before any mutation. `WebhookProcessor` then:

1. locks the webhook row;
2. ignores previously processed payloads;
3. upserts the transaction/card/subscription;
4. updates subscription state atomically;
5. dispatches package events after commit.

## Source-of-truth rules

- Browser redirects are UX signals only.
- Verified backend callbacks are authoritative.
- Inquiry and synchronization APIs repair missed or delayed callbacks.
- Raw remote responses remain available for diagnostics.
- Card tokens and webhook payloads are encrypted at rest.

## Subscription identity

A new local subscription gets a UUID `reference`. The same value is sent as:

- `special_reference`;
- `extras.subscription_reference`.

Callbacks are matched using, in order:

1. the merchant order/reference;
2. the intention ID;
3. the Paymob subscription ID;
4. the subscription callback's `initial_transaction`.

## State model

```text
incomplete
  ├─ successful enrollment → active
  ├─ failed payment → incomplete
  └─ checkout timeout → incomplete_expired

active
  ├─ failed renewal → past_due
  ├─ suspend → suspended
  ├─ cancel → canceled
  └─ end date passed → expired

past_due
  └─ successful retry → active

suspended
  └─ resume → active
```

`canceled` is terminal remotely. A new local subscription record is created for re-enrollment.
