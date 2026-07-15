<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Paymob\Laravel\Billing\SubscriptionManager;
use Paymob\Laravel\Enums\SubscriptionStatus;
use Paymob\Laravel\Exceptions\PaymobException;
use Paymob\Laravel\Models\Subscription;
use Paymob\Laravel\Tests\Fixtures\Fixtures;

beforeEach(function () {
    $this->setUpConfig();
    Http::preventStrayRequests();
    $this->ensureUserTable();
});

function createSubscription(array $overrides = []): array
{
    static $counter = 0;
    $counter++;

    $billable = test()->createBillable([
        'name' => 'Test User',
        'email' => "test{$counter}@example.com",
        'phone' => '+20123456789',
    ]);
    $billable->save();

    $subscription = Subscription::create(array_merge([
        'billable_type' => $billable->getMorphClass(),
        'billable_id' => $billable->getKey(),
        'name' => 'default',
        'paymob_id' => '77777',
        'remote_plan_id' => '88888',
        'reference' => (string) Str::uuid(),
        'status' => SubscriptionStatus::ACTIVE,
        'amount_cents' => 49900,
        'currency' => 'EGP',
    ], $overrides));

    return ['billable' => $billable, 'subscription' => $subscription];
}

it('suspend updates status to SUSPENDED and sets suspended_at', function () {
    ['subscription' => $subscription] = createSubscription();

    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*/suspend' => Http::response(Fixtures::subscriptionRemoteResponse(['state' => 'suspended']), 200),
    ]);

    $result = app(SubscriptionManager::class)->suspend($subscription);

    expect($result->status)->toBe(SubscriptionStatus::SUSPENDED)
        ->and($result->suspended_at)->not->toBeNull();

    $result->refresh();
    expect($result->status)->toBe(SubscriptionStatus::SUSPENDED)
        ->and($result->suspended_at)->not->toBeNull();
});

it('resume updates status to ACTIVE and sets activated_at', function () {
    ['subscription' => $subscription] = createSubscription([
        'status' => SubscriptionStatus::SUSPENDED,
    ]);

    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*/resume' => Http::response(Fixtures::subscriptionRemoteResponse(['state' => 'activated']), 200),
    ]);

    $result = app(SubscriptionManager::class)->resume($subscription);

    expect($result->status)->toBe(SubscriptionStatus::ACTIVE)
        ->and($result->activated_at)->not->toBeNull();

    $result->refresh();
    expect($result->status)->toBe(SubscriptionStatus::ACTIVE);
});

it('resume with nextBilling sends update before resume', function () {
    ['subscription' => $subscription] = createSubscription([
        'status' => SubscriptionStatus::SUSPENDED,
    ]);

    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*' => Http::response(Fixtures::subscriptionRemoteResponse(['state' => 'activated']), 200),
    ]);

    app(SubscriptionManager::class)->resume($subscription, '2026-03-01');

    Http::assertSent(function ($request) {
        return $request->method() === 'PUT' && str_contains($request->url(), '/subscriptions/');
    });
    Http::assertSent(function ($request) {
        return $request->method() === 'POST' && str_contains($request->url(), '/resume');
    });
});

it('cancel updates status to CANCELED and sets canceled_at', function () {
    ['subscription' => $subscription] = createSubscription();

    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*/cancel' => Http::response(Fixtures::subscriptionRemoteResponse(['state' => 'cancelled']), 200),
    ]);

    $result = app(SubscriptionManager::class)->cancel($subscription);

    expect($result->status)->toBe(SubscriptionStatus::CANCELED)
        ->and($result->canceled_at)->not->toBeNull();

    $result->refresh();
    expect($result->status)->toBe(SubscriptionStatus::CANCELED)
        ->and($result->canceled_at)->not->toBeNull();
});

it('update only allows amount_cents, ends_at, next_billing', function () {
    ['subscription' => $subscription] = createSubscription();

    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*' => Http::response(Fixtures::subscriptionRemoteResponse(), 200),
    ]);

    app(SubscriptionManager::class)->update($subscription, [
        'amount_cents' => 59900,
        'currency' => 'USD',
        'name' => 'new name',
    ]);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return isset($data['amount_cents']) && $data['amount_cents'] === 59900
            && ! isset($data['currency'])
            && ! isset($data['name']);
    });
});

it('sync updates subscription from remote', function () {
    ['subscription' => $subscription] = createSubscription();

    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/*' => Http::response(Fixtures::subscriptionRemoteResponse([
            'state' => 'activated',
            'amount_cents' => 59900,
            'currency' => 'EGP',
        ]), 200),
    ]);

    $result = app(SubscriptionManager::class)->sync($subscription);

    expect($result->amount_cents)->toBe(59900)
        ->and($result->status)->toBe(SubscriptionStatus::ACTIVE);

    $result->refresh();
    expect($result->amount_cents)->toBe(59900);
});

it('syncAll syncs all subscriptions with paymob_id', function () {
    createSubscription(['paymob_id' => '77777']);
    createSubscription(['paymob_id' => '77778']);

    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/77777' => Http::response(Fixtures::subscriptionRemoteResponse(['id' => 77777]), 200),
        '*/api/acceptance/subscriptions/77778' => Http::response(Fixtures::subscriptionRemoteResponse(['id' => 77778]), 200),
    ]);

    $count = app(SubscriptionManager::class)->syncAll();

    expect($count)->toBe(2);
});

it('syncAll continues on individual failures', function () {
    createSubscription(['paymob_id' => '77777']);
    createSubscription(['paymob_id' => '77778']);

    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscriptions/77777' => Http::response(['message' => 'Not found'], 404),
        '*/api/acceptance/subscriptions/77778' => Http::response(Fixtures::subscriptionRemoteResponse(['id' => 77778]), 200),
    ]);

    $count = app(SubscriptionManager::class)->syncAll();

    expect($count)->toBe(1);
});

it('persist transitions status correctly via SubscriptionStatus::fromPaymob', function () {
    ['subscription' => $subscription] = createSubscription([
        'status' => SubscriptionStatus::INCOMPLETE,
    ]);

    $result = app(SubscriptionManager::class)->persist($subscription, ['state' => 'activated']);

    expect($result->status)->toBe(SubscriptionStatus::ACTIVE);

    $result = app(SubscriptionManager::class)->persist($result, ['state' => 'suspended']);

    expect($result->status)->toBe(SubscriptionStatus::SUSPENDED);

    $result = app(SubscriptionManager::class)->persist($result, ['state' => 'cancelled']);

    expect($result->status)->toBe(SubscriptionStatus::CANCELED);
});

it('expireIncomplete marks old incomplete subscriptions as INCOMPLETE_EXPIRED', function () {
    ['subscription' => $old] = createSubscription(['paymob_id' => '77777', 'status' => SubscriptionStatus::INCOMPLETE]);
    $old->update(['created_at' => now()->subSeconds(7200)]);

    ['subscription' => $recent] = createSubscription(['paymob_id' => '77778', 'status' => SubscriptionStatus::INCOMPLETE]);

    $count = app(SubscriptionManager::class)->expireIncomplete();

    expect($count)->toBe(1);

    expect($old->refresh()->status)->toBe(SubscriptionStatus::INCOMPLETE_EXPIRED);
    expect($recent->refresh()->status)->toBe(SubscriptionStatus::INCOMPLETE);
});

it('throws PaymobException when paymob_id is missing', function () {
    $billable = $this->createBillable([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    $subscription = Subscription::create([
        'billable_type' => $billable->getMorphClass(),
        'billable_id' => $billable->getKey(),
        'name' => 'default',
        'paymob_id' => null,
        'remote_plan_id' => '88888',
        'reference' => (string) Str::uuid(),
        'status' => SubscriptionStatus::ACTIVE,
        'amount_cents' => 49900,
        'currency' => 'EGP',
    ]);

    app(SubscriptionManager::class)->suspend($subscription);
})->throws(PaymobException::class, 'The subscription is not active at Paymob yet.');
