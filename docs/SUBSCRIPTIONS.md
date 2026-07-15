# Subscription Implementation Notes

## Enrollment

Paymob subscription enrollment is an Intention request with `subscription_plan_id`. The customer completes 3DS checkout, Paymob tokenizes the card, and the subscription is activated.

The package writes an `incomplete` local subscription before calling Paymob. This ensures every remote attempt has a local reference and preserves failures for support.

## Integrations

A normal subscription commonly needs:

- an online 3DS integration for enrollment;
- a MOTO integration on the subscription plan for renewals.

A verification-only 3DS integration may be used for free trials when enabled by Paymob. The package does not pretend a zero-value transaction is universally supported; the enrollment amount is explicit.

## Access control

Use `valid()` or `$user->subscribed()` for application access. A subscription is considered valid when:

- active and not past its end date;
- within a local trial;
- canceled/suspended but still before the next billing boundary.

Adjust this policy in an extended model when your business revokes access immediately on suspension or cancellation.

## Renewal failures

Paymob owns the actual retrial schedule configured by `retrial_days`. A failed recurring transaction moves the local state to `past_due`. A later successful callback restores `active`.

## Reconciliation

Use:

```bash
php artisan paymob:sync-subscriptions --plans
php artisan paymob:sync-subscriptions --with-relations
```

The relation option imports subscription transactions and saved cards. It is more expensive and should run less frequently than basic status synchronization.

## Card changes

Adding a secondary card is another 3DS Intention using `subscriptionv2_id`. After the token callback, the card is stored locally. Use Paymob's card-management endpoints to change the primary card or delete a non-primary card.
