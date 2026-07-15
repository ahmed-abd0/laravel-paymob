# Laravel Paymob

A community-built, Cashier-inspired Paymob package for Laravel 11, 12, and 13. It wraps the current Paymob Intention, saved-card, subscription, refund/void/capture, transaction inquiry, and Quick Link APIs while keeping a reliable local billing state.

> This package is not an official Paymob package. Replace the Composer vendor name before publishing under your own organization.

## Why this design

The package has two layers:

1. **Raw API resources** for direct access to every endpoint.
2. **Billing models and workflows** for subscriptions, idempotent callbacks, local access checks, trials, grace periods, payment methods, and synchronization.

Redirect responses are never treated as proof of payment. Signed backend callbacks and Paymob inquiry APIs are the source of truth.

## Installation

```bash
composer require paymob-community/laravel-paymob
php artisan vendor:publish --tag=paymob-config
php artisan migrate
```

Add `Paymob\Laravel\Concerns\Billable` to the model that owns subscriptions:

```php
use Paymob\Laravel\Concerns\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

Configure `.env`:

```dotenv
PAYMOB_REGION=egypt
PAYMOB_CURRENCY=EGP
PAYMOB_SECRET_KEY=
PAYMOB_PUBLIC_KEY=
PAYMOB_API_KEY=
PAYMOB_HMAC_SECRET=
PAYMOB_CARD_3DS_INTEGRATION_ID=
PAYMOB_MOTO_INTEGRATION_ID=
PAYMOB_SUBSCRIPTION_WEBHOOK_SECRET=a-long-random-secret
PAYMOB_REDIRECTION_URL=https://app.example.com/billing/return
```

Supported regions: `egypt`, `ksa`, `uae`, and `oman`.

## Create a Paymob subscription plan

```php
use Paymob\Laravel\Data\SubscriptionPlanData;
use Paymob\Laravel\Enums\PlanFrequency;
use Paymob\Laravel\Facades\Paymob;

$plan = Paymob::plans()->create(new SubscriptionPlanData(
    name: 'Clinic Pro Monthly',
    amountCents: 49900,
    integration: (int) config('paymob.integrations.moto'),
    frequency: PlanFrequency::MONTHLY,
    reminderDays: 2,
    retrialDays: 2,
    webhookUrl: route('paymob.webhooks.subscription', [
        'secret' => config('paymob.webhooks.subscription_secret')
    ])
));
```

Plans are stored locally in `paymob_plans` and keep the full remote response for diagnostics.

## Start a subscription checkout

```php
$checkout = $user->newSubscription('default', $plan)
    ->billing([
        'first_name' => 'Ahmed',
        'last_name' => 'Abdo',
        'email' => 'ahmed@example.com',
        'phone_number' => '+201000000000',
        'country' => 'EG'
    ])
    ->metadata(['tenant_id' => $tenant->id])
    ->checkout();

return $checkout->redirect();
```

For a trial:

```php
$checkout = $user->newSubscription('default', $plan)
    ->trialDays(14)
    ->amount(100)
    ->checkout();
```

When Paymob provides a dedicated 3DS verification integration, pass it explicitly for verification-only enrollment:

```php
$checkout = $user->newSubscription('default', $plan)
    ->trialDays(14)
    ->amount(100)
    ->paymentMethods([(int) config('paymob.integrations.verification_3ds')])
    ->checkout();
```

The local subscription starts as `incomplete`. It becomes `active` only after a verified successful callback.

## Subscription checks

```php
$user->subscribed('default');
$user->subscription('default')?->active();
$user->subscription('default')?->onTrial();
$user->subscription('default')?->onGracePeriod();
$user->subscription('default')?->pastDue();
```

`valid()` returns true while active, on trial, or in a cancellation/suspension grace period.

## Manage a subscription

```php
$subscription = $user->subscription('default');

$subscription->suspend();
$subscription->resume(nextBilling: '2026-08-15');
$subscription->updateBilling([
    'amount_cents' => 59900,
    'ends_at' => '2027-07-15',
    'next_billing' => '2026-08-15'
]);
$subscription->cancel();
$subscription->sync();
$subscription->sync(withRelations: true);
```

Paymob cancellation is permanent. Creating a fresh subscription is required after cancellation.

## Manage cards

```php
$cards = Paymob::subscriptions()->cards($subscription->paymob_id);
Paymob::subscriptions()->changePrimaryCard($subscription->paymob_id, $cardId);
Paymob::subscriptions()->deleteCard($subscription->paymob_id, $cardId);
```

To add a secondary card, create an intention with `subscriptionv2_id`:

```php
use Paymob\Laravel\Data\BillingData;
use Paymob\Laravel\Data\IntentionData;
use Paymob\Laravel\Data\Item;

$data = new IntentionData(
    amount: 100,
    billingData: BillingData::fromArray($user->paymobBillingData()),
    paymentMethods: [(int) config('paymob.integrations.card_3ds')],
    subscriptionId: (int) $subscription->paymob_id
);

$data->items(new Item('Card verification', 100));
$response = Paymob::subscriptions()->addCard($data);
```

Card tokens are encrypted using Laravel's application key. Webhook payloads are also encrypted at rest.

## One-off intentions

```php
$data = new IntentionData(
    amount: 10000,
    billingData: BillingData::fromArray($user->paymobBillingData()),
    paymentMethods: [(int) config('paymob.integrations.default')],
    specialReference: 'ORDER-1001'
);

$data->items(new Item('Order 1001', 10000));
$response = Paymob::intentions()->create($data);
$url = Paymob::intentions()->checkoutUrl($response['client_secret']);
```

## Saved cards

Customer-initiated payment:

```php
$response = Paymob::savedCards()->customerInitiated($data, [$storedToken]);
```

Merchant-initiated payment:

```php
$intention = Paymob::savedCards()->merchantInitiatedIntention($motoIntention);
$paymentToken = data_get($intention->toArray(), 'payment_keys.0.key');
$charge = Paymob::savedCards()->payMoto($storedCardToken, $paymentToken);
```

Never expose saved tokens to frontend code.

## Refund, void, and capture

```php
Paymob::payments()->refund($transactionId, 5000);
Paymob::payments()->void($transactionId);
Paymob::payments()->capture($transactionId, 10000);
```

## Transaction inquiry

```php
Paymob::transactions()->byOrder(orderId: $paymobOrderId);
Paymob::transactions()->byOrder(merchantOrderId: 'ORDER-1001');
Paymob::transactions()->find($transactionId);
```

## Quick Links

```php
$link = Paymob::quickLinks()->create([
    'amount_cents' => 10000,
    'payment_methods' => config('paymob.integrations.default'),
    'full_name' => 'Ahmed Abdo',
    'email' => 'ahmed@example.com',
    'phone_number' => '+201000000000',
    'description' => 'Invoice 1001',
    'reference_id' => 'INV-1001',
    'is_live' => false,
    'notification_url' => route('paymob.webhooks.transaction')
]);

Paymob::quickLinks()->cancel($link['id']);
```

## Webhooks

The package registers:

```text
POST /paymob/webhooks
POST /paymob/webhooks/transaction
POST /paymob/webhooks/token
POST /paymob/webhooks/subscription?secret=...
```

Transaction and token callbacks use HMAC-SHA512 validation. Subscription callbacks use a secret embedded in the registered callback URL because the supplied subscription collection does not define a subscription-specific HMAC calculation.

Processing is:

- HMAC/secret verified before state changes.
- Payload hash deduplicated.
- Webhook row locked before processing.
- Transactions and card tokens upserted.
- Subscription state changed atomically.
- Events dispatched after commit.
- Failed callbacks return an error so Paymob can retry.

Available events:

```php
Paymob\Laravel\Events\WebhookHandled
Paymob\Laravel\Events\TransactionUpdated
Paymob\Laravel\Events\SubscriptionUpdated
Paymob\Laravel\Events\PaymentMethodUpdated
```

If the callbacks use Laravel's `web` middleware, exclude these paths from CSRF validation. The default package routes use the `api` middleware group.

## Synchronization and cleanup

```bash
php artisan paymob:sync-subscriptions --plans
php artisan paymob:sync-subscriptions --with-relations
php artisan paymob:prune-webhooks
php artisan paymob:prune-webhooks --days=30
```

Schedule synchronization as a reconciliation fallback:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('paymob:sync-subscriptions --plans')->hourly();
Schedule::command('paymob:prune-webhooks')->daily();
```

## Raw endpoint coverage

| Area | Methods |
|---|---|
| Intentions | create, update, retrieve, checkout URL |
| Subscription plans | create, list, update, suspend, resume |
| Subscriptions | create, retrieve, update, suspend, resume, cancel |
| Subscription details | last transaction, transactions, cards |
| Subscription cards | add, delete, change primary |
| Saved cards | token enrollment, CIT, MIT/MOTO |
| Post-payment | refund, void, capture |
| Inquiry | order/merchant reference, transaction ID |
| Quick Links | create, cancel |

## Important production rules

- Use integers in minor currency units (`10000` means `100.00`).
- Ensure the intention amount equals the sum of `items[].amount`.
- Test keys only work with test integration IDs; live keys only work with live integration IDs.
- A recurring plan requires a MOTO integration; enrollment normally requires an online 3DS integration.
- Treat callbacks and inquiry responses as authoritative, not browser redirects.
- Keep `APP_KEY`, Paymob keys, HMAC secret, and tokenized cards outside source control.
- Use HTTPS for every callback and redirect URL.
- Only GET requests are retried automatically; non-idempotent payment operations are not replayed by default.
- Add your own authorization around customer billing-management endpoints.

## Custom models

Override model classes in `config/paymob.php`. Custom models should extend the package model so the relationships, casts, and lifecycle methods remain available.

## Testing

```bash
composer install
composer test
composer lint
```
