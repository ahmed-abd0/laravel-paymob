# Laravel Paymob Public API Reference

This document describes the public methods available to applications using `paymob-community/laravel-paymob`.

The package exposes two main layers:

1. **Cashier-style billing API** — recommended for plans, subscriptions, local state, access checks, synchronization, and card records.
2. **Raw Paymob resources** — thin wrappers around the official Paymob endpoints when direct API access is required.

## Contents

- [Conventions](#conventions)
- [Facade entry points](#facade-entry-points)
- [Cashier-style billing API](#cashier-style-billing-api)
- [Subscription plan API](#subscription-plan-api)
- [Raw subscription API](#raw-subscription-api)
- [Intention API](#intention-api)
- [Saved-card API](#saved-card-api)
- [Payments API](#payments-api)
- [Transaction inquiry API](#transaction-inquiry-api)
- [Quick Link API](#quick-link-api)
- [Data objects](#data-objects)
- [Responses and utility classes](#responses-and-utility-classes)
- [Enums](#enums)
- [Events](#events)
- [Webhook endpoints](#webhook-endpoints)
- [Artisan commands](#artisan-commands)
- [Exceptions](#exceptions)
- [Recommended workflows](#recommended-workflows)

---

## Conventions

### Amounts

All Paymob amounts are integers in the smallest currency unit:

```php
10000; // 100.00 EGP
49900; // 499.00 EGP
```

You can use the package helper:

```php
use Paymob\Laravel\Support\Money;

$amountCents = Money::cents('499.50'); // 49950
$display = Money::decimal(49950);      // "499.50"
```

### Authentication

The package selects authentication automatically:

- Secret-key endpoints use `Authorization: Token {secret-key}`.
- Management endpoints use a cached bearer token generated from `PAYMOB_API_KEY`.
- Public checkout retrieval uses `PAYMOB_PUBLIC_KEY`.

### Return types

Raw Paymob resource methods return:

```php
Paymob\Laravel\Support\PaymobResponse
```

High-level billing methods return local Eloquent models such as:

```php
Paymob\Laravel\Models\Plan
Paymob\Laravel\Models\Subscription
```

### Recommended imports

```php
use Paymob\Laravel\Facades\Paymob;
use Paymob\Laravel\Data\BillingData;
use Paymob\Laravel\Data\IntentionData;
use Paymob\Laravel\Data\Item;
use Paymob\Laravel\Data\SubscriptionPlanData;
```

---

# Facade entry points

The `Paymob` facade is the main package entry point.

```php
use Paymob\Laravel\Facades\Paymob;
```

## `Paymob::intentions(): Intentions`

Returns the raw Intention API resource.

```php
Paymob::intentions()->create($data);
```

## `Paymob::subscriptionPlans(): SubscriptionPlans`

Returns the raw remote subscription-plan API resource.

```php
Paymob::subscriptionPlans()->all();
```

## `Paymob::subscriptions(): Subscriptions`

Returns the raw remote subscriptions API resource.

```php
Paymob::subscriptions()->find($subscriptionId);
```

## `Paymob::transactions(): Transactions`

Returns transaction-inquiry methods.

```php
Paymob::transactions()->find($transactionId);
```

## `Paymob::payments(): Payments`

Returns refund, void, and capture methods.

```php
Paymob::payments()->refund($transactionId, 5000);
```

## `Paymob::quickLinks(): QuickLinks`

Returns Quick Link creation and cancellation methods.

```php
Paymob::quickLinks()->create($payload);
```

## `Paymob::savedCards(): SavedCards`

Returns saved-card enrollment, CIT, and MIT/MOTO methods.

```php
Paymob::savedCards()->customerInitiated($data, [$cardToken]);
```

## `Paymob::plans(): PlanManager`

Returns the high-level plan manager. It calls Paymob and keeps `paymob_plans` synchronized locally.

```php
$plan = Paymob::plans()->create($planData);
```

## `Paymob::billing(): SubscriptionManager`

Returns the high-level subscription manager. It calls Paymob and updates local subscription, transaction, and payment-method records.

```php
Paymob::billing()->sync($subscription, withRelations: true);
```

---

# Cashier-style billing API

## Adding `Billable` to a model

Add the trait to the Eloquent model that owns subscriptions:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Paymob\Laravel\Concerns\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

The owner can be any Eloquent model, not only `User`.

## `paymobSubscriptions(): MorphMany`

Returns all local Paymob subscriptions owned by the model.

```php
$subscriptions = $user->paymobSubscriptions()->latest()->get();
```

Use this relationship when you need history, multiple named subscriptions, filtering, or eager loading.

## `paymobTransactions(): MorphMany`

Returns all local transactions associated with the billable model.

```php
$transactions = $user->paymobTransactions()
    ->latest('occurred_at')
    ->get();
```

## `paymobPaymentMethods(): MorphMany`

Returns all locally stored Paymob card tokens/payment methods owned by the model.

```php
$cards = $user->paymobPaymentMethods()
    ->orderByDesc('primary')
    ->get();
```

The `token` attribute is encrypted and hidden from array/JSON serialization.

## `subscription(string $name = 'default'): ?Subscription`

Returns the newest local subscription with the requested name.

```php
$subscription = $user->subscription();
$pro = $user->subscription('pro');
```

Returns `null` when no matching subscription exists.

## `subscribed(string $name = 'default'): bool`

Checks whether the newest named subscription is currently valid.

```php
if ($user->subscribed('default')) {
    // Grant subscribed access.
}
```

This calls the subscription model's `valid()` method. It returns `true` when the subscription is active, on trial, or within the package's grace-period rule.

## `newSubscription(string $name, Plan|int|string $plan): SubscriptionBuilder`

Starts a fluent subscription-enrollment builder.

The `$plan` argument can be:

- a local `Plan` model;
- a remote Paymob plan ID as an integer;
- a remote Paymob plan ID as a string.

```php
$builder = $user->newSubscription('default', $plan);
```

No Paymob request is made until `checkout()` is called.

## `paymobBillingData(): array`

Builds default customer billing data from common model attributes:

- `first_name` and `last_name`, or `name`;
- `email`;
- `phone` or `phone_number`;
- country defaults to `EG`.

```php
$data = $user->paymobBillingData();
```

Override this method on your billable model when your column names or address rules differ:

```php
public function paymobBillingData(): array
{
    return [
        'first_name' => $this->profile->first_name,
        'last_name' => $this->profile->last_name,
        'email' => $this->billing_email,
        'phone_number' => $this->mobile,
        'city' => $this->billing_city,
        'country' => 'EG'
    ];
}
```

---

## Subscription builder methods

Create a builder through the billable model:

```php
$builder = $user->newSubscription('default', $plan);
```

### `billing(BillingData|array $data): self`

Overrides billing data used during subscription enrollment.

```php
$builder->billing([
    'first_name' => 'Ahmed',
    'last_name' => 'Abdo',
    'email' => 'ahmed@example.com',
    'phone_number' => '+201000000000',
    'city' => 'Cairo',
    'country' => 'EG'
]);
```

When omitted, the builder calls the billable model's `paymobBillingData()` method.

### `amount(int $amountCents): self`

Sets the initial enrollment transaction amount.

```php
$builder->amount(49900);
```

When a local `Plan` model is supplied, the plan amount is used by default. An explicit amount is useful for verification charges, discounted enrollment, or `use_transaction_amount` plans.

The value must be positive.

### `currency(string $currency): self`

Overrides the checkout currency.

```php
$builder->currency('EGP');
```

The value is converted to uppercase. When omitted, the local plan currency or `paymob.currency` configuration is used.

### `paymentMethods(array $integrationIds): self`

Sets the Paymob integration IDs available during enrollment.

```php
$builder->paymentMethods([
    (int) config('paymob.integrations.card_3ds')
]);
```

For subscription enrollment, this should normally contain an online 3DS integration. The MOTO integration belongs on the Paymob subscription plan and is used for later automatic renewals.

### `metadata(array $metadata): self`

Stores application metadata locally and sends it inside intention `extras`.

```php
$builder->metadata([
    'tenant_id' => $tenant->id,
    'sales_channel' => 'web'
]);
```

The builder also adds its own subscription reference and billable identity. Do not place secrets or sensitive card information in metadata.

### `trialUntil(DateTimeInterface $date): self`

Sets a local trial end and uses the same date as the requested subscription start date.

```php
$builder->trialUntil(now()->addDays(14));
```

### `trialDays(int $days): self`

Convenience wrapper around `trialUntil()`.

```php
$builder->trialDays(14);
```

A trial does not automatically make the enrollment transaction zero. Pass an explicit supported verification/enrollment amount and, when available, a Paymob verification integration.

### `startAt(DateTimeInterface|string $date): self`

Sets `subscription_start_date` for Paymob.

```php
$builder->startAt('2026-08-01');
$builder->startAt(now()->addMonth());
```

Date objects are formatted as `Y-m-d`.

### `callbackUrls(?string $notificationUrl, ?string $redirectionUrl): self`

Overrides callback URLs for this checkout.

```php
$builder->callbackUrls(
    notificationUrl: route('paymob.webhooks.handle'),
    redirectionUrl: route('billing.return')
);
```

The notification URL receives server callbacks. The redirection URL is only a browser UX destination and must not be treated as proof of payment.

### `expiresIn(int $seconds): self`

Overrides the intention expiration time.

```php
$builder->expiresIn(3600);
```

When omitted, `paymob.checkout.expiration` is used.

### `description(string $description): self`

Sets the description of the generated subscription enrollment item.

```php
$builder->description('Clinic Pro annual enrollment');
```

### `checkout(): Checkout`

Creates the local `incomplete` subscription, sends the intention to Paymob, and returns a checkout object.

```php
$checkout = $user->newSubscription('default', $plan)
    ->billing($billingData)
    ->metadata(['tenant_id' => $tenant->id])
    ->checkout();

return $checkout->redirect();
```

Important behavior:

- A cache lock prevents concurrent duplicate creation for the same billable model and subscription name.
- A non-terminal subscription with the same name causes an `InvalidArgumentException`.
- An expired incomplete checkout is marked `incomplete_expired` before a replacement is created.
- The new local subscription starts as `incomplete`.
- A verified webhook activates it later.
- The remote response and encrypted client secret are stored locally.

### `for(Model $billable, string $name, Plan|int|string $plan): self`

Initializes the builder. Applications normally use `newSubscription()` instead of calling `for()` directly.

```php
$builder = app(SubscriptionBuilder::class)->for($user, 'default', $plan);
```

---

## Checkout object

`checkout()` returns `Paymob\Laravel\Support\Checkout`.

### Public properties

```php
$checkout->url;          // Unified Checkout URL
$checkout->clientSecret; // Paymob intention client secret
$checkout->response;     // PaymobResponse from intention creation/retrieval
$checkout->subscription; // Local Subscription for subscription checkout
```

### `redirect(): RedirectResponse`

Redirects the browser to Paymob Unified Checkout.

```php
return $checkout->redirect();
```

For an API frontend, return the URL instead:

```php
return response()->json([
    'checkout_url' => $checkout->url,
    'subscription_id' => $checkout->subscription?->id
]);
```

---

## Local subscription model

The default model is `Paymob\Laravel\Models\Subscription`.

### Relationships

#### `billable(): MorphTo`

Returns the model that owns the subscription.

```php
$owner = $subscription->billable;
```

#### `plan(): BelongsTo`

Returns the local Paymob plan, when the enrollment used one.

```php
$plan = $subscription->plan;
```

#### `transactions(): HasMany`

Returns local transactions linked to the subscription.

```php
$transactions = $subscription->transactions()->latest('occurred_at')->get();
```

#### `paymentMethods(): HasMany`

Returns local saved cards linked to the subscription.

```php
$primary = $subscription->paymentMethods()->where('primary', true)->first();
```

### Status-check methods

#### `active(): bool`

Returns `true` when status is `active` and the subscription has not passed `ends_at`.

```php
$subscription->active();
```

#### `incomplete(): bool`

Returns `true` for both `incomplete` and `incomplete_expired` states.

```php
$subscription->incomplete();
```

#### `canceled(): bool`

Returns `true` when status is `canceled`.

```php
$subscription->canceled();
```

#### `suspended(): bool`

Returns `true` when status is `suspended`.

```php
$subscription->suspended();
```

#### `pastDue(): bool`

Returns `true` when a recurring payment failed and the local status is `past_due`.

```php
$subscription->pastDue();
```

#### `onTrial(): bool`

Returns `true` while `trial_ends_at` is in the future.

```php
$subscription->onTrial();
```

#### `onGracePeriod(): bool`

Returns `true` when the subscription is canceled or suspended but its `next_billing_at` date has not passed.

```php
$subscription->onGracePeriod();
```

This is the package's default access policy. Extend the model and override the method when your application must revoke access immediately.

#### `valid(): bool`

Returns `true` when any of the following is true:

- `active()`;
- `onTrial()`;
- `onGracePeriod()`.

```php
abort_unless($subscription->valid(), 403);
```

### Lifecycle methods

#### `suspend(): static`

Suspends the remote subscription and refreshes the local model.

```php
$subscription->suspend();
```

#### `resume(?string $nextBilling = null): static`

Resumes the remote subscription. Optionally updates the next billing date first.

```php
$subscription->resume();
$subscription->resume('2026-08-15');
```

#### `cancel(): static`

Cancels the remote subscription and refreshes local state.

```php
$subscription->cancel();
```

Paymob cancellation is treated as terminal. Re-enrollment should create a new local subscription.

#### `updateBilling(array $data): static`

Updates supported remote billing fields and refreshes local state.

Supported keys:

- `amount_cents`;
- `ends_at`;
- `next_billing`.

```php
$subscription->updateBilling([
    'amount_cents' => 59900,
    'ends_at' => '2027-07-15',
    'next_billing' => '2026-08-15'
]);
```

Unknown keys are ignored by the high-level manager.

#### `sync(bool $withRelations = false): static`

Refreshes the local subscription from Paymob.

```php
$subscription->sync();
$subscription->sync(withRelations: true);
```

When `$withRelations` is `true`, the package also imports remote transaction history and card tokens.

#### `syncTransactions(): int`

Imports/upserts remote subscription transactions and returns the number of remote rows read.

```php
$count = $subscription->syncTransactions();
```

#### `syncPaymentMethods(): int`

Imports/upserts remote subscription cards and returns the number of remote rows read.

```php
$count = $subscription->syncPaymentMethods();
```

---

## High-level subscription manager

Access it with:

```php
$billing = Paymob::billing();
```

The model convenience methods call this manager internally.

### `suspend(Subscription $subscription): Subscription`

Suspends a remote subscription and persists the returned state locally.

```php
$subscription = Paymob::billing()->suspend($subscription);
```

### `resume(Subscription $subscription, ?string $nextBilling = null): Subscription`

Optionally changes `next_billing`, resumes the remote subscription, and updates local state.

```php
$subscription = Paymob::billing()->resume($subscription, '2026-08-15');
```

### `cancel(Subscription $subscription): Subscription`

Cancels the remote subscription and persists the canceled state.

```php
$subscription = Paymob::billing()->cancel($subscription);
```

### `update(Subscription $subscription, array $data): Subscription`

Updates the allowed billing fields:

```php
$subscription = Paymob::billing()->update($subscription, [
    'amount_cents' => 59900,
    'next_billing' => '2026-08-15'
]);
```

Only `amount_cents`, `ends_at`, and `next_billing` are sent.

### `sync(Subscription $subscription, bool $withRelations = false): Subscription`

Retrieves the remote subscription and updates the local record.

```php
$subscription = Paymob::billing()->sync($subscription, withRelations: true);
```

### `syncTransactions(Subscription $subscription): int`

Imports transaction history.

```php
$count = Paymob::billing()->syncTransactions($subscription);
```

### `syncPaymentMethods(Subscription $subscription): int`

Imports saved cards.

```php
$count = Paymob::billing()->syncPaymentMethods($subscription);
```

### `expireIncomplete(): int`

Marks stale local `incomplete` subscriptions as `incomplete_expired` and returns the number updated.

```php
$expired = Paymob::billing()->expireIncomplete();
```

The timeout is taken from `paymob.checkout.expiration`.

### `syncAll(bool $withRelations = false): int`

Synchronizes every local subscription that has a remote Paymob ID.

```php
$count = Paymob::billing()->syncAll();
$count = Paymob::billing()->syncAll(withRelations: true);
```

Errors for individual subscriptions are reported through Laravel and do not stop the entire batch.

### `persist(Subscription $subscription, array $data): Subscription`

Maps a remote subscription payload into a local model inside a database transaction.

```php
$subscription = Paymob::billing()->persist($subscription, $remotePayload);
```

This method is mainly intended for package extensions, importers, or custom webhook handlers. Normal applications should use `sync()` or lifecycle methods.

---

# Subscription plan API

There are two ways to manage plans:

- `Paymob::plans()` — recommended high-level manager that stores plans locally.
- `Paymob::subscriptionPlans()` — raw remote API with no automatic local persistence.

## High-level plan manager

### `create(SubscriptionPlanData|array $data): Plan`

Creates a plan at Paymob and stores it in `paymob_plans`.

```php
use Paymob\Laravel\Data\SubscriptionPlanData;
use Paymob\Laravel\Enums\PlanFrequency;
use Paymob\Laravel\Enums\PlanType;

$plan = Paymob::plans()->create(new SubscriptionPlanData(
    name: 'Clinic Pro Monthly',
    amountCents: 49900,
    integration: (int) config('paymob.integrations.moto'),
    frequency: PlanFrequency::MONTHLY,
    planType: PlanType::RENT,
    reminderDays: 2,
    retrialDays: 2
));
```

When no `webhook_url` is supplied, the manager uses the package subscription webhook route when available.

### `update(Plan $plan, array $data): Plan`

Updates a remote plan and merges the result into the local plan.

```php
$plan = Paymob::plans()->update($plan, [
    'amount_cents' => 59900,
    'number_of_deductions' => 12,
    'integration' => (int) config('paymob.integrations.moto')
]);
```

### `suspend(Plan $plan): Plan`

Prevents new enrollments in the plan and marks it inactive locally.

```php
$plan = Paymob::plans()->suspend($plan);
```

Existing remote subscriptions continue according to Paymob's plan behavior.

### `resume(Plan $plan): Plan`

Reactivates a suspended plan.

```php
$plan = Paymob::plans()->resume($plan);
```

### `sync(Plan $plan): Plan`

Loads all remote plans, finds the matching Paymob plan ID, and refreshes the local record.

```php
$plan = Paymob::plans()->sync($plan);
```

When the remote plan is not present in the returned list, the current local model is returned unchanged.

### `syncAll(): int`

Imports or updates every remote plan and returns the number found.

```php
$count = Paymob::plans()->syncAll();
```

## Local plan model

The default model is `Paymob\Laravel\Models\Plan`.

### `subscriptions(): HasMany`

Returns local subscriptions linked to the plan.

```php
$subscriptions = $plan->subscriptions()->get();
```

### `suspend(): static`

High-level model shortcut:

```php
$plan->suspend();
```

### `resume(): static`

High-level model shortcut:

```php
$plan->resume();
```

### `sync(): static`

High-level model shortcut:

```php
$plan->sync();
```

## Raw subscription-plan resource

Access with:

```php
$plans = Paymob::subscriptionPlans();
```

### `create(SubscriptionPlanData|array $data): PaymobResponse`

Creates a remote plan without storing it locally.

```php
$response = Paymob::subscriptionPlans()->create($planData);
```

### `all(array $query = []): PaymobResponse`

Lists remote plans.

```php
$response = Paymob::subscriptionPlans()->all();
$response = Paymob::subscriptionPlans()->all(['page' => 2]);
```

Query parameters are forwarded unchanged.

### `update(int|string $planId, array $data): PaymobResponse`

Updates a remote plan.

```php
$response = Paymob::subscriptionPlans()->update($planId, [
    'amount_cents' => 59900,
    'number_of_deductions' => 12,
    'integration' => $motoIntegrationId
]);
```

### `suspend(int|string $planId): PaymobResponse`

Suspends a remote plan.

```php
$response = Paymob::subscriptionPlans()->suspend($planId);
```

### `resume(int|string $planId): PaymobResponse`

Resumes a remote plan.

```php
$response = Paymob::subscriptionPlans()->resume($planId);
```

---

# Raw subscription API

Access with:

```php
$subscriptions = Paymob::subscriptions();
```

These methods call Paymob directly and do not automatically update local models, except when you later process callbacks or call the billing synchronization methods.

## `create(IntentionData|array $data): PaymobResponse`

Creates a subscription enrollment intention. The payload must include `subscription_plan_id`.

```php
$response = Paymob::subscriptions()->create($intentionData);
```

For normal application enrollment, prefer `$billable->newSubscription(...)->checkout()` because it creates and tracks the local subscription before contacting Paymob.

## `find(int|string $subscriptionId, array $query = []): PaymobResponse`

Retrieves a remote subscription.

```php
$response = Paymob::subscriptions()->find($subscription->paymob_id);
$response = Paymob::subscriptions()->find($subscriptionId, ['expand' => 'plan']);
```

Query parameters are forwarded unchanged.

## `update(int|string $subscriptionId, array $data): PaymobResponse`

Updates a remote subscription.

```php
$response = Paymob::subscriptions()->update($subscriptionId, [
    'amount_cents' => 59900,
    'ends_at' => '2027-07-15',
    'next_billing' => '2026-08-15'
]);
```

## `suspend(int|string $subscriptionId): PaymobResponse`

Suspends a remote subscription.

```php
$response = Paymob::subscriptions()->suspend($subscriptionId);
```

## `resume(int|string $subscriptionId): PaymobResponse`

Resumes a remote subscription.

```php
$response = Paymob::subscriptions()->resume($subscriptionId);
```

## `cancel(int|string $subscriptionId): PaymobResponse`

Cancels a remote subscription.

```php
$response = Paymob::subscriptions()->cancel($subscriptionId);
```

## `lastTransaction(int|string $subscriptionId): PaymobResponse`

Retrieves the subscription's latest remote transaction.

```php
$response = Paymob::subscriptions()->lastTransaction($subscriptionId);
```

## `transactions(int|string $subscriptionId, array $query = []): PaymobResponse`

Lists remote transactions for a subscription.

```php
$response = Paymob::subscriptions()->transactions($subscriptionId);
$response = Paymob::subscriptions()->transactions($subscriptionId, ['page' => 2]);
```

## `cards(int|string $subscriptionId): PaymobResponse`

Lists the remote saved cards attached to a subscription.

```php
$response = Paymob::subscriptions()->cards($subscriptionId);
```

## `addCard(IntentionData|array $data): PaymobResponse`

Creates a 3DS intention to add another card to an existing subscription. Set `subscriptionId`, which serializes as `subscriptionv2_id`.

```php
$data = new IntentionData(
    amount: 100,
    billingData: BillingData::fromArray($user->paymobBillingData()),
    paymentMethods: [(int) config('paymob.integrations.card_3ds')],
    subscriptionId: (int) $subscription->paymob_id
);

$data->items(new Item('Card verification', 100));
$response = Paymob::subscriptions()->addCard($data);
```

After the customer completes checkout, the token callback stores the card locally.

## `deleteCard(int|string $subscriptionId, int|string $cardId): PaymobResponse`

Deletes a card from the remote subscription.

```php
$response = Paymob::subscriptions()->deleteCard($subscriptionId, $cardId);
```

## `changePrimaryCard(int|string $subscriptionId, int|string $cardId): PaymobResponse`

Makes a card the primary renewal card.

```php
$response = Paymob::subscriptions()->changePrimaryCard($subscriptionId, $cardId);
```

## `registerWebhook(int|string $planId, string $url): PaymobResponse`

Registers a webhook URL for subscriptions associated with the plan.

```php
$response = Paymob::subscriptions()->registerWebhook(
    $planId,
    route('paymob.webhooks.subscription', [
        'secret' => config('paymob.webhooks.subscription_secret')
    ])
);
```

---

# Intention API

Access with:

```php
$intentions = Paymob::intentions();
```

## `create(IntentionData|array $data): PaymobResponse`

Creates a payment intention.

```php
$data = new IntentionData(
    amount: 10000,
    billingData: BillingData::fromArray($user->paymobBillingData()),
    paymentMethods: [(int) config('paymob.integrations.default')],
    currency: 'EGP',
    specialReference: 'ORDER-1001'
);

$data->items(new Item('Order 1001', 10000));
$response = Paymob::intentions()->create($data);
```

The total intention amount must equal the sum of `items[].amount` when items are supplied.

## `update(string $clientSecret, array $data): PaymobResponse`

Updates an existing intention by client secret.

```php
$response = Paymob::intentions()->update($clientSecret, [
    'expiration' => 7200,
    'redirection_url' => route('payments.return')
]);
```

The package forwards the update payload unchanged. Use fields supported by the current Paymob Intention API.

## `retrieve(string $clientSecret, ?string $publicKey = null): PaymobResponse`

Retrieves public intention/checkout data.

```php
$response = Paymob::intentions()->retrieve($clientSecret);
$response = Paymob::intentions()->retrieve($clientSecret, $publicKey);
```

When `$publicKey` is omitted, `PAYMOB_PUBLIC_KEY` is used.

## `checkout(string $clientSecret, ?string $publicKey = null): Checkout`

Retrieves the intention and returns a checkout object containing its URL and response.

```php
$checkout = Paymob::intentions()->checkout($clientSecret);
return $checkout->redirect();
```

For one-off intentions, `$checkout->subscription` is `null`.

## `checkoutUrl(string $clientSecret, ?string $publicKey = null): string`

Builds the Unified Checkout URL without making a retrieval request.

```php
$url = Paymob::intentions()->checkoutUrl($clientSecret);
```

---

# Saved-card API

Access with:

```php
$savedCards = Paymob::savedCards();
```

Never send stored card tokens to frontend code or include them in logs.

## `createTokenIntention(IntentionData|array $data): PaymobResponse`

Creates an intention used to complete a 3DS payment and receive a token callback.

```php
$response = Paymob::savedCards()->createTokenIntention($data);
```

This delegates to the Intention API. Ensure the configured integration supports card tokenization.

## `customerInitiated(IntentionData|array $data, array $cardTokens): PaymobResponse`

Creates a customer-initiated transaction using one or more saved tokens. The customer is present and may be required to provide CVV or complete additional authentication.

```php
$response = Paymob::savedCards()->customerInitiated(
    $data,
    [$paymentMethod->token]
);
```

For an `IntentionData` object, the method adds the card tokens before serialization. For an array, it adds the `card_tokens` key.

## `merchantInitiatedIntention(IntentionData|array $data): PaymobResponse`

Creates the payment intention used for a merchant-initiated/MOTO charge.

```php
$intention = Paymob::savedCards()->merchantInitiatedIntention($motoData);
```

The intention must use the integration and fields required by Paymob for MIT/MOTO payments.

## `payMoto(string $cardToken, string $paymentToken): PaymobResponse`

Completes a MOTO payment with the saved card token and payment token from the preceding intention.

```php
$intention = Paymob::savedCards()->merchantInitiatedIntention($motoData);
$paymentToken = (string) $intention->get('payment_keys.0.key');

$charge = Paymob::savedCards()->payMoto(
    $paymentMethod->token,
    $paymentToken
);
```

This method posts to `paymob.moto_base_url` and sends the token as a Paymob `TOKEN` payment source.

---

# Payments API

Access with:

```php
$payments = Paymob::payments();
```

## `refund(int|string $transactionId, int $amountCents): PaymobResponse`

Requests a full or partial refund.

```php
$response = Paymob::payments()->refund($transactionId, 5000);
```

The amount must be positive and cannot exceed the refundable amount. Paymob may apply refund transaction fees.

## `void(int|string $transactionId): PaymobResponse`

Voids a transaction when Paymob permits voiding it, normally on the same business day.

```php
$response = Paymob::payments()->void($transactionId);
```

Use refund rather than void when the transaction is no longer eligible for voiding.

## `capture(int|string $transactionId, int $amountCents): PaymobResponse`

Captures an authorized transaction for the specified amount.

```php
$response = Paymob::payments()->capture($transactionId, 10000);
```

The amount may be the full authorized amount or a smaller supported amount. Only authorized transactions can be captured.

---

# Transaction inquiry API

Access with:

```php
$transactions = Paymob::transactions();
```

## `byOrder(int|string|null $orderId = null, ?string $merchantOrderId = null): PaymobResponse`

Retrieves the latest transaction associated with a Paymob order ID or your merchant reference.

By Paymob order ID:

```php
$response = Paymob::transactions()->byOrder(orderId: $paymobOrderId);
```

By merchant order ID/special reference:

```php
$response = Paymob::transactions()->byOrder(
    merchantOrderId: 'ORDER-1001'
);
```

At least one identifier is required. Calling the method with both values `null` throws `InvalidArgumentException`.

## `find(int|string $transactionId): PaymobResponse`

Retrieves a transaction by Paymob transaction ID.

```php
$response = Paymob::transactions()->find($transactionId);
```

This is useful for reconciliation, support tools, and verification after a delayed or missing callback.

---

# Quick Link API

Access with:

```php
$quickLinks = Paymob::quickLinks();
```

Quick Link requests are sent as multipart form data.

## `create(array $data): PaymobResponse`

Creates a shareable payment link.

```php
$response = Paymob::quickLinks()->create([
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

$url = $response->get('client_url');
```

The package converts booleans to `true`/`false` strings and supports repeated multipart fields when an array is passed.

To upload an image, pass either a local path or `SplFileInfo`:

```php
$response = Paymob::quickLinks()->create([
    'amount_cents' => 10000,
    'payment_methods' => $integrationId,
    'is_live' => false,
    'payment_link_image' => storage_path('app/invoice.png')
]);
```

## `cancel(int|string $linkId): PaymobResponse`

Cancels a Quick Link.

```php
$response = Paymob::quickLinks()->cancel($linkId);
```

---

# Data objects

## `BillingData`

Represents Paymob billing information.

### Constructor

```php
new BillingData(
    firstName: string,
    lastName: string,
    email: string,
    phoneNumber: string,
    apartment: string = 'NA',
    floor: string = 'NA',
    street: string = 'NA',
    building: string = 'NA',
    city: string = 'NA',
    country: string = 'EG',
    state: string = 'NA',
    postalCode: string = 'NA'
);
```

Example:

```php
$billing = new BillingData(
    firstName: 'Ahmed',
    lastName: 'Abdo',
    email: 'ahmed@example.com',
    phoneNumber: '+201000000000',
    city: 'Cairo',
    country: 'EG'
);
```

### `BillingData::fromArray(array $data): BillingData`

Creates the object from snake_case or supported camelCase keys.

```php
$billing = BillingData::fromArray([
    'first_name' => 'Ahmed',
    'last_name' => 'Abdo',
    'email' => 'ahmed@example.com',
    'phone_number' => '+201000000000'
]);
```

Supported aliases include:

- `first_name` / `firstName`;
- `last_name` / `lastName`;
- `phone_number` / `phoneNumber`;
- `postal_code` / `postalCode`.

### `toArray(): array`

Validates and serializes billing data.

```php
$payload = $billing->toArray();
```

Validation rules enforced by the object:

- first name is required;
- last name is required;
- phone number is required;
- email must be valid.

## `Item`

Represents an intention item.

### Constructor

```php
new Item(
    name: string,
    amount: int,
    quantity: int = 1,
    description: ?string = null,
    image: ?string = null
);
```

Example:

```php
$item = new Item(
    name: 'Clinic Pro',
    amount: 49900,
    quantity: 1,
    description: 'Monthly subscription'
);
```

### `toArray(): array`

Serializes the item and removes `null` optional values.

```php
$payload = $item->toArray();
```

## `IntentionData`

Builds and validates an intention request.

### Constructor

```php
new IntentionData(
    amount: int,
    billingData: BillingData,
    paymentMethods: array,
    currency: string = 'EGP',
    specialReference: ?string = null,
    notificationUrl: ?string = null,
    redirectionUrl: ?string = null,
    expiration: ?int = null,
    subscriptionPlanId: ?int = null,
    subscriptionId: ?int = null,
    subscriptionStartDate: ?string = null
);
```

When `specialReference` is omitted, a UUID is generated automatically.

### `items(Item|array ...$items): self`

Sets all intention items.

```php
$data->items(
    new Item('Product A', 5000),
    new Item('Product B', 5000)
);
```

This method replaces the previously configured item list.

### `extras(array $extras): self`

Sets merchant metadata returned by Paymob in callback claims.

```php
$data->extras([
    'order_id' => 1001,
    'tenant_id' => 15
]);
```

### `cardTokens(array $tokens): self`

Sets saved-card tokens for a customer-initiated transaction.

```php
$data->cardTokens([$paymentMethod->token]);
```

### `toArray(): array`

Validates and serializes the request.

```php
$payload = $data->toArray();
```

Validation rules:

- `amount` must be greater than zero;
- at least one positive numeric integration ID is required;
- when items are supplied, the sum of item amounts must equal the intention amount;
- billing data must pass `BillingData::toArray()` validation.

Field mapping includes:

- `subscriptionPlanId` → `subscription_plan_id`;
- `subscriptionId` → `subscriptionv2_id`;
- `subscriptionStartDate` → `subscription_start_date`.

## `SubscriptionPlanData`

Builds and validates a remote subscription plan request.

### Constructor

```php
new SubscriptionPlanData(
    name: string,
    amountCents: int,
    integration: int,
    frequency: PlanFrequency|int = PlanFrequency::MONTHLY,
    planType: PlanType|string = PlanType::RENT,
    reminderDays: int = 2,
    retrialDays: int = 2,
    numberOfDeductions: ?int = null,
    useTransactionAmount: bool = false,
    active: bool = true,
    webhookUrl: ?string = null
);
```

### `toArray(): array`

Validates and serializes the plan.

```php
$payload = $planData->toArray();
```

Validation rules:

- frequency must be one of `7`, `15`, `30`, `60`, `90`, `180`, or `360`;
- amount must be positive;
- MOTO integration ID must be positive.

---

# Responses and utility classes

## `PaymobResponse`

All raw resources return an immutable `PaymobResponse`.

### Public properties

```php
$response->status;  // HTTP status code
$response->headers; // Response headers
```

### `successful(): bool`

Returns `true` for HTTP status codes from 200 through 299.

```php
if ($response->successful()) {
    // Continue.
}
```

Failed HTTP responses normally throw `ApiException` before a `PaymobResponse` is returned.

### `get(string $key, mixed $default = null): mixed`

Reads a nested value using Laravel dot notation.

```php
$clientSecret = $response->get('client_secret');
$paymentToken = $response->get('payment_keys.0.key');
```

### `toArray(): array`

Returns the complete decoded JSON body.

```php
$payload = $response->toArray();
```

### `jsonSerialize(): array`

Allows direct JSON serialization.

```php
return response()->json($response);
```

### Array access

The object can be read like an array:

```php
$id = $response['id'];
$clientSecret = $response['client_secret'];
```

It is immutable. Writing or unsetting offsets throws `LogicException`.

## `Money`

### `Money::cents(int|float|string $amount): int`

Converts a major-unit decimal amount to cents using half-up rounding.

```php
Money::cents(100);      // 10000
Money::cents('100.25'); // 10025
```

Non-numeric values throw `InvalidArgumentException`.

### `Money::decimal(int $cents): string`

Formats cents as a two-decimal string.

```php
Money::decimal(10025); // "100.25"
```

---

# Local supporting models

## `Transaction`

### `subscription(): BelongsTo`

Returns the local subscription related to the transaction.

```php
$subscription = $transaction->subscription;
```

### `billable(): MorphTo`

Returns the owning billable model.

```php
$owner = $transaction->billable;
```

### `succeeded(): bool`

Returns `true` when local status is `succeeded`.

```php
$transaction->succeeded();
```

### `failed(): bool`

Returns `true` when local status is `failed`.

```php
$transaction->failed();
```

## `PaymentMethod`

### `subscription(): BelongsTo`

Returns the subscription associated with the saved card.

```php
$subscription = $paymentMethod->subscription;
```

### `billable(): MorphTo`

Returns the owning billable model.

```php
$owner = $paymentMethod->billable;
```

The `token` cast is encrypted and hidden from normal array/JSON output.

## `WebhookCall`

The webhook model stores deduplicated callback records. Its payload is encrypted at rest.

```php
$failedCalls = config('paymob.models.webhook_call')::query()
    ->where('status', 'failed')
    ->get();
```

Applications normally inspect this model for diagnostics rather than calling package methods on it.

---

# Enums

## `Region`

```php
Region::EGYPT; // egypt
Region::KSA;   // ksa
Region::UAE;   // uae
Region::OMAN;  // oman
```

### `baseUrl(): string`

Returns the official regional Paymob base URL.

```php
$url = Region::EGYPT->baseUrl();
```

## `Currency`

Available values:

```php
Currency::EGP;
Currency::SAR;
Currency::AED;
Currency::OMR;
Currency::USD;
```

## `PlanFrequency`

```php
PlanFrequency::WEEKLY;     // 7
PlanFrequency::BIWEEKLY;   // 15
PlanFrequency::MONTHLY;    // 30
PlanFrequency::BIMONTHLY;  // 60
PlanFrequency::QUARTERLY;  // 90
PlanFrequency::SEMIANNUAL; // 180
PlanFrequency::ANNUAL;     // 360
```

## `PlanType`

```php
PlanType::RENT;
PlanType::INSTALLMENT;
PlanType::PURCHASE;
PlanType::BUNDLE;
PlanType::MERCHANT_SUBSCRIPTION;
PlanType::OTHER;
```

## `SubscriptionStatus`

```php
SubscriptionStatus::INCOMPLETE;
SubscriptionStatus::INCOMPLETE_EXPIRED;
SubscriptionStatus::ACTIVE;
SubscriptionStatus::PAST_DUE;
SubscriptionStatus::SUSPENDED;
SubscriptionStatus::CANCELED;
SubscriptionStatus::EXPIRED;
```

### `SubscriptionStatus::fromPaymob(?string $state): SubscriptionStatus`

Maps common Paymob state names to package statuses.

```php
$status = SubscriptionStatus::fromPaymob('activated'); // ACTIVE
$status = SubscriptionStatus::fromPaymob('failed');    // PAST_DUE
```

Unknown values map to `INCOMPLETE`.

## `TransactionStatus`

```php
TransactionStatus::PENDING;
TransactionStatus::SUCCEEDED;
TransactionStatus::FAILED;
TransactionStatus::REFUNDED;
TransactionStatus::VOIDED;
TransactionStatus::CAPTURED;
```

### `TransactionStatus::fromPayload(array $payload): TransactionStatus`

Maps Paymob transaction booleans to a local status.

```php
$status = TransactionStatus::fromPayload($paymobPayload);
```

Precedence is refunded, voided, captured, pending, then succeeded/failed.

## `WebhookType`

```php
WebhookType::TRANSACTION;
WebhookType::TOKEN;
WebhookType::SUBSCRIPTION;
WebhookType::UNKNOWN;
```

### `WebhookType::detect(array $payload): WebhookType`

Detects the callback type from common Paymob payload fields.

```php
$type = WebhookType::detect($payload);
```

This method is mainly useful for custom webhook routing or testing.

---

# Events

The package dispatches events after the webhook database transaction commits successfully.

## `WebhookHandled`

Dispatched after a webhook call has been processed.

```php
use Paymob\Laravel\Events\WebhookHandled;

Event::listen(WebhookHandled::class, function (WebhookHandled $event) {
    $call = $event->webhookCall;
});
```

## `TransactionUpdated`

Dispatched when a transaction callback creates or updates a local transaction.

```php
use Paymob\Laravel\Events\TransactionUpdated;

Event::listen(TransactionUpdated::class, function (TransactionUpdated $event) {
    $transaction = $event->transaction;
});
```

## `SubscriptionUpdated`

Dispatched when a callback updates local subscription state.

```php
use Paymob\Laravel\Events\SubscriptionUpdated;

Event::listen(SubscriptionUpdated::class, function (SubscriptionUpdated $event) {
    $subscription = $event->subscription;
});
```

## `PaymentMethodUpdated`

Dispatched when a token callback creates or updates a local payment method.

```php
use Paymob\Laravel\Events\PaymentMethodUpdated;

Event::listen(PaymentMethodUpdated::class, function (PaymentMethodUpdated $event) {
    $paymentMethod = $event->paymentMethod;
});
```

---

# Webhook endpoints

When `paymob.webhooks.enabled` is `true`, the package registers these routes under the configured prefix, which defaults to `paymob/webhooks`.

## Automatic detection

```text
POST /paymob/webhooks
```

Route name:

```php
route('paymob.webhooks.handle');
```

The controller detects transaction, token, or subscription payloads.

## Transaction callback

```text
POST /paymob/webhooks/transaction
```

Route name:

```php
route('paymob.webhooks.transaction');
```

The callback HMAC can be supplied through:

- `?hmac=...`;
- `X-Paymob-Hmac` header;
- payload `hmac` field.

## Token callback

```text
POST /paymob/webhooks/token
```

Route name:

```php
route('paymob.webhooks.token');
```

The token callback is HMAC verified and the card token is encrypted before local storage.

## Subscription callback

```text
POST /paymob/webhooks/subscription?secret=...
```

Route name:

```php
route('paymob.webhooks.subscription', [
    'secret' => config('paymob.webhooks.subscription_secret')
]);
```

The secret can be supplied through:

- `?secret=...`;
- `X-Paymob-Webhook-Secret` header.

## Processing guarantees

Webhook processing performs the following:

- verifies HMAC or subscription secret before mutations;
- hashes and deduplicates callbacks;
- encrypts callback payloads at rest;
- locks the webhook row before processing;
- upserts transactions, subscriptions, and payment methods;
- dispatches events after commit;
- returns `204 No Content` after successful processing;
- throws `InvalidSignatureException` and returns `401` for invalid signatures.

The default route middleware is `api`. Add authentication or throttling only when it does not block Paymob from reaching the callback.

---

# Artisan commands

## `paymob:sync-subscriptions`

Synchronizes local subscriptions with Paymob.

```bash
php artisan paymob:sync-subscriptions
```

Before synchronization, the command expires stale incomplete checkouts.

### `--plans`

Synchronizes remote plans first.

```bash
php artisan paymob:sync-subscriptions --plans
```

### `--with-relations`

Also imports transaction history and saved cards for each subscription.

```bash
php artisan paymob:sync-subscriptions --with-relations
```

Both options can be combined:

```bash
php artisan paymob:sync-subscriptions --plans --with-relations
```

## `paymob:prune-webhooks`

Deletes old processed webhook records.

```bash
php artisan paymob:prune-webhooks
```

The default retention period comes from `paymob.webhooks.retain_days`.

### `--days=`

Overrides retention for the current run.

```bash
php artisan paymob:prune-webhooks --days=30
```

## Recommended scheduling

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('paymob:sync-subscriptions --plans')->hourly();
Schedule::command('paymob:sync-subscriptions --with-relations')->daily();
Schedule::command('paymob:prune-webhooks')->daily();
```

---

# Exceptions

## `PaymobException`

Base runtime exception for package-specific failures.

```php
use Paymob\Laravel\Exceptions\PaymobException;

try {
    $subscription->sync();
} catch (PaymobException $e) {
    report($e);
}
```

## `ApiException`

Thrown when Paymob returns a failed HTTP response.

Public properties:

```php
$exception->status;       // HTTP status code
$exception->responseBody; // Decoded array, text body, or null
```

Example:

```php
use Paymob\Laravel\Exceptions\ApiException;

try {
    Paymob::payments()->refund($transactionId, 5000);
} catch (ApiException $e) {
    logger()->error('Paymob refund failed', [
        'status' => $e->status,
        'response' => $e->responseBody
    ]);
}
```

## `ConfigurationException`

Thrown when a required key or configuration value is missing, such as:

- `PAYMOB_SECRET_KEY`;
- `PAYMOB_PUBLIC_KEY`;
- `PAYMOB_API_KEY`;
- `PAYMOB_HMAC_SECRET`.

## `InvalidSignatureException`

Thrown for an invalid Paymob webhook HMAC or subscription secret. Its Laravel render method returns:

```json
{
    "message": "Invalid Paymob webhook signature."
}
```

with HTTP status `401`.

## Standard PHP exceptions

Some validation methods throw `InvalidArgumentException`, including:

- invalid billing data;
- non-positive intention amounts;
- missing/invalid integration IDs;
- mismatched item totals;
- unsupported plan frequencies;
- duplicate non-terminal named subscriptions;
- transaction inquiry without an order identifier.

---

# Recommended workflows

## 1. Create and store a subscription plan

```php
$plan = Paymob::plans()->create(new SubscriptionPlanData(
    name: 'Clinic Pro Monthly',
    amountCents: 49900,
    integration: (int) config('paymob.integrations.moto'),
    frequency: PlanFrequency::MONTHLY
));
```

Use the high-level manager so the remote and local plan are created together.

## 2. Start enrollment

```php
$checkout = $user->newSubscription('default', $plan)
    ->billing($user->paymobBillingData())
    ->metadata(['tenant_id' => $tenant->id])
    ->checkout();

return response()->json([
    'checkout_url' => $checkout->url,
    'subscription' => [
        'id' => $checkout->subscription->id,
        'status' => $checkout->subscription->status->value
    ]
]);
```

Do not activate access based on this response or the later browser redirect.

## 3. Receive verified callbacks

Configure Paymob to call the package routes. The package validates and processes the callbacks, then changes the subscription from `incomplete` to `active` after a verified successful payment.

## 4. Check access locally

```php
if (!$user->subscribed('default')) {
    return response()->json(['message' => 'An active subscription is required.'], 403);
}
```

This avoids a remote Paymob request on every application request.

## 5. Manage the lifecycle

```php
$subscription = $user->subscription('default');

$subscription->suspend();
$subscription->resume('2026-08-15');
$subscription->updateBilling(['amount_cents' => 59900]);
$subscription->cancel();
```

Authorize these actions in your application before exposing them through controllers.

## 6. Reconcile regularly

```bash
php artisan paymob:sync-subscriptions --plans
php artisan paymob:sync-subscriptions --with-relations
```

Callbacks remain the normal real-time path. Synchronization repairs missed, delayed, or manually changed remote state.

## 7. Handle one-off payments separately

```php
$response = Paymob::intentions()->create($data);
$url = Paymob::intentions()->checkoutUrl($response['client_secret']);
```

Store your own order/payment attempt before redirecting, and use the verified transaction callback or transaction inquiry as the source of truth.

---

# Advanced/internal services

The following public classes exist primarily to support package internals and extensions. Most applications should use the facade resources and billing managers instead.

## `PaymobHttpClient`

Methods:

```php
secret(string $method, string $path, array $data = [], array $options = []): PaymobResponse
bearer(string $method, string $path, array $data = [], array $options = []): PaymobResponse
public(string $method, string $path, array $data = [], array $options = []): PaymobResponse
baseUrl(): string
```

Use these only when adding a Paymob endpoint that the package does not yet wrap. `bearer()` automatically refreshes and retries once after an HTTP `401`. Automatic transport retries are only enabled for configured safe methods, which default to `GET`.

## `TokenManager`

Methods:

```php
token(bool $fresh = false): string
forget(): void
```

`token()` returns the cached management bearer token. Pass `true` to force a new token. `forget()` removes the current cached token.

## `SignatureVerifier`

Methods:

```php
transaction(array $payload, ?string $signature): bool
token(array $payload, ?string $signature): bool
subscription(?string $providedSecret): bool
```

Use these when implementing custom webhook routes. Prefer the package routes unless custom routing is required.

## `WebhookProcessor`

```php
process(WebhookCall $call): void
```

Processes a previously validated and stored webhook call. Do not call it with an unverified payload.
