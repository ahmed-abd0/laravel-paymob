<?php

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Paymob\Laravel\Billing\SubscriptionBuilder;
use Paymob\Laravel\Enums\SubscriptionStatus;
use Paymob\Laravel\Models\Subscription;

beforeEach(function () {
    $this->ensureUserTable();
});

it('paymobSubscriptions returns MorphMany relation', function () {
    $billable = $this->createBillable([
        'name' => 'John',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    expect($billable->paymobSubscriptions())->toBeInstanceOf(MorphMany::class);
});

it('paymobTransactions returns MorphMany relation', function () {
    $billable = $this->createBillable([
        'name' => 'John',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    expect($billable->paymobTransactions())->toBeInstanceOf(MorphMany::class);
});

it('paymobPaymentMethods returns MorphMany relation', function () {
    $billable = $this->createBillable([
        'name' => 'John',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    expect($billable->paymobPaymentMethods())->toBeInstanceOf(MorphMany::class);
});

it('subscription returns latest subscription by name', function () {
    $billable = $this->createBillable([
        'name' => 'John',
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

    Subscription::create([
        'billable_type' => $billable->getMorphClass(),
        'billable_id' => $billable->getKey(),
        'name' => 'pro',
        'paymob_id' => '77778',
        'remote_plan_id' => '88889',
        'reference' => (string) Str::uuid(),
        'status' => SubscriptionStatus::ACTIVE,
        'amount_cents' => 99900,
        'currency' => 'EGP',
    ]);

    expect($billable->subscription('default'))->not->toBeNull()
        ->and($billable->subscription('default')->name)->toBe('default')
        ->and($billable->subscription('pro')->name)->toBe('pro');
});

it('subscribed returns true for active subscription', function () {
    $billable = $this->createBillable([
        'name' => 'John',
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

    expect($billable->subscribed('default'))->toBeTrue();
});

it('subscribed returns false for no subscription', function () {
    $billable = $this->createBillable([
        'name' => 'John',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    expect($billable->subscribed('default'))->toBeFalse();
});

it('subscribed returns false for expired subscription', function () {
    $billable = $this->createBillable([
        'name' => 'John',
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

    expect($billable->subscribed('default'))->toBeFalse();
});

it('newSubscription returns SubscriptionBuilder', function () {
    $billable = $this->createBillable([
        'name' => 'John',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);
    $billable->save();

    $builder = $billable->newSubscription('default', 88888);

    expect($builder)->toBeInstanceOf(SubscriptionBuilder::class);
});

it('paymobBillingData returns correct array from model attributes', function () {
    $billable = $this->createBillable([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);

    $data = $billable->paymobBillingData();

    expect($data)->toBe([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone_number' => '+20123456789',
        'country' => 'EG',
    ]);
});

it('paymobBillingData uses name attribute for first and last name splitting', function () {
    $billable = $this->createBillable([
        'name' => 'John',
        'email' => 'john@example.com',
        'phone' => '+20123456789',
    ]);

    $data = $billable->paymobBillingData();

    expect($data['first_name'])->toBe('John')
        ->and($data['last_name'])->toBe('Customer');
});

it('paymobBillingData uses first_name and last_name when available', function () {
    $billable = $this->createBillable([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane@example.com',
        'phone' => '+20123456789',
    ]);

    $data = $billable->paymobBillingData();

    expect($data['first_name'])->toBe('Jane')
        ->and($data['last_name'])->toBe('Smith');
});

it('subscribed returns false for past_due subscription without trial or grace', function () {
    $billable = $this->createBillable([
        'name' => 'John',
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
        'status' => SubscriptionStatus::PAST_DUE,
        'amount_cents' => 49900,
        'currency' => 'EGP',
    ]);

    expect($billable->subscribed('default'))->toBeFalse();
});
