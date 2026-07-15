<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Paymob\Laravel\Enums\SubscriptionStatus;
use Paymob\Laravel\Models\Plan;
use Paymob\Laravel\Models\Subscription;
use Paymob\Laravel\Support\Checkout;
use Paymob\Laravel\Support\PaymobResponse;
use Paymob\Laravel\Tests\Fixtures\Fixtures;

beforeEach(function () {
    $this->setUpConfig();
    Http::preventStrayRequests();
    $this->ensureUserTable();

    if (! Schema::hasTable('cache')) {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
    }
    if (! Schema::hasTable('cache_locks')) {
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }
    config()->set('cache.default', 'database');
    config()->set('cache.stores.database', [
        'driver' => 'database',
        'connection' => null,
        'table' => 'cache',
        'lock_connection' => null,
        'lock_table' => 'cache_locks',
    ]);
});

it('creates local subscription record with INCOMPLETE status', function () {
    Http::fake([
        '*/v1/intention/*' => Http::response(Fixtures::intentionResponse(), 200),
    ]);

    $billable = $this->createBillable([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    $checkout = $billable->newSubscription('default', 88888)
        ->amount(49900)
        ->currency('EGP')
        ->paymentMethods([465537])
        ->checkout();

    $subscription = $billable->subscription('default');

    expect($subscription)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::INCOMPLETE)
        ->and($checkout)->toBeInstanceOf(Checkout::class);
});

it('generates a UUID reference', function () {
    Http::fake([
        '*/v1/intention/*' => Http::response(Fixtures::intentionResponse(), 200),
    ]);

    $billable = $this->createBillable([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    $billable->newSubscription('default', 88888)
        ->amount(49900)
        ->currency('EGP')
        ->paymentMethods([465537])
        ->checkout();

    $subscription = $billable->subscription('default');

    expect($subscription->reference)->not->toBeNull()
        ->and(Str::isUuid($subscription->reference))->toBeTrue();
});

it('builds intention data with correct amount, currency, payment methods', function () {
    Http::fake([
        '*/v1/intention/*' => Http::response(Fixtures::intentionResponse(), 200),
    ]);

    $billable = $this->createBillable([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    $billable->newSubscription('default', 88888)
        ->amount(49900)
        ->currency('EGP')
        ->paymentMethods([465537])
        ->checkout();

    Http::assertSent(function ($request) {
        $data = $request->data();

        return ($data['amount'] ?? null) === 49900
            && ($data['currency'] ?? null) === 'EGP'
            && ($data['payment_methods'] ?? null) === [465537]
            && isset($data['billing_data'])
            && isset($data['items']);
    });
});

it('returns Checkout object with url, client_secret, response, subscription', function () {
    Http::fake([
        '*/v1/intention/*' => Http::response(Fixtures::intentionResponse(), 200),
    ]);

    $billable = $this->createBillable([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    $checkout = $billable->newSubscription('default', 88888)
        ->amount(49900)
        ->currency('EGP')
        ->paymentMethods([465537])
        ->checkout();

    expect($checkout->url)->toBeString()
        ->and($checkout->clientSecret)->toBe('test_client_secret_xyz')
        ->and($checkout->response)->toBeInstanceOf(PaymobResponse::class)
        ->and($checkout->subscription)->toBeInstanceOf(Subscription::class);
});

it('throws InvalidArgumentException when subscription name already exists and is non-terminal', function () {
    $billable = $this->createBillable([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    Subscription::create([
        'billable_type' => $billable->getMorphClass(),
        'billable_id' => $billable->getKey(),
        'name' => 'default',
        'paymob_id' => '77777',
        'remote_plan_id' => '88888',
        'reference' => (string) Str::uuid(),
        'status' => SubscriptionStatus::ACTIVE,
        'amount_cents' => 49900,
        'currency' => 'EGP',
    ]);

    $billable->newSubscription('default', 88888)
        ->amount(49900)
        ->currency('EGP')
        ->paymentMethods([465537])
        ->checkout();
})->throws(InvalidArgumentException::class, 'A non-terminal subscription named [default] already exists.');

it('allows creating subscription when existing is CANCELED', function () {
    Http::fake([
        '*/v1/intention/*' => Http::response(Fixtures::intentionResponse(), 200),
    ]);

    $billable = $this->createBillable([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    Subscription::create([
        'billable_type' => $billable->getMorphClass(),
        'billable_id' => $billable->getKey(),
        'name' => 'default',
        'paymob_id' => '77777',
        'remote_plan_id' => '88888',
        'reference' => (string) Str::uuid(),
        'status' => SubscriptionStatus::CANCELED,
        'amount_cents' => 49900,
        'currency' => 'EGP',
    ]);

    $checkout = $billable->newSubscription('default', 88888)
        ->amount(49900)
        ->currency('EGP')
        ->paymentMethods([465537])
        ->checkout();

    expect($checkout)->toBeInstanceOf(Checkout::class);
});

it('allows creating subscription when existing is EXPIRED', function () {
    Http::fake([
        '*/v1/intention/*' => Http::response(Fixtures::intentionResponse(), 200),
    ]);

    $billable = $this->createBillable([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    Subscription::create([
        'billable_type' => $billable->getMorphClass(),
        'billable_id' => $billable->getKey(),
        'name' => 'default',
        'paymob_id' => '77777',
        'remote_plan_id' => '88888',
        'reference' => (string) Str::uuid(),
        'status' => SubscriptionStatus::EXPIRED,
        'amount_cents' => 49900,
        'currency' => 'EGP',
    ]);

    $checkout = $billable->newSubscription('default', 88888)
        ->amount(49900)
        ->currency('EGP')
        ->paymentMethods([465537])
        ->checkout();

    expect($checkout)->toBeInstanceOf(Checkout::class);
});

it('sets trial_ends_at and starts_at when trialDays used', function () {
    Http::fake([
        '*/v1/intention/*' => Http::response(Fixtures::intentionResponse(), 200),
    ]);

    $billable = $this->createBillable([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    $billable->newSubscription('default', 88888)
        ->amount(49900)
        ->currency('EGP')
        ->paymentMethods([465537])
        ->trialDays(7)
        ->checkout();

    $subscription = $billable->subscription('default');

    expect($subscription->trial_ends_at)->not->toBeNull()
        ->and($subscription->trial_ends_at->format('Y-m-d'))->toBe(now()->addDays(7)->format('Y-m-d'))
        ->and($subscription->starts_at)->not->toBeNull()
        ->and($subscription->starts_at->format('Y-m-d'))->toBe(now()->addDays(7)->format('Y-m-d'));
});

it('uses plan amount when no explicit amount provided', function () {
    Http::fake([
        '*/v1/intention/*' => Http::response(Fixtures::intentionResponse(), 200),
    ]);

    $billable = $this->createBillable([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    $plan = Plan::create([
        'paymob_id' => '88888',
        'name' => 'Test Plan',
        'frequency' => 30,
        'plan_type' => 'rent',
        'amount_cents' => 49900,
        'currency' => 'EGP',
        'integration_id' => 465538,
    ]);

    $billable->newSubscription('default', $plan)
        ->currency('EGP')
        ->paymentMethods([465537])
        ->checkout();

    $subscription = $billable->subscription('default');

    expect($subscription->amount_cents)->toBe(49900)
        ->and($subscription->currency)->toBe('EGP');
});

it('throws when no payment methods configured', function () {
    config()->set('paymob.integrations.card_3ds', 0);
    config()->set('paymob.integrations.default', 0);

    $billable = $this->createBillable([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    $billable->newSubscription('default', 88888)
        ->amount(49900)
        ->currency('EGP')
        ->checkout();
})->throws(InvalidArgumentException::class, 'Configure PAYMOB_CARD_3DS_INTEGRATION_ID or pass paymentMethods().');
