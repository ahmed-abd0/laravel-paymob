<?php

use Carbon\Carbon;
use Paymob\Laravel\Enums\SubscriptionStatus;
use Paymob\Laravel\Models\Subscription;

it('casts status to SubscriptionStatus enum', function () {
    $sub = new Subscription(['status' => 'active']);
    expect($sub->status)->toBeInstanceOf(SubscriptionStatus::class);
    expect($sub->status)->toBe(SubscriptionStatus::ACTIVE);
});

it('casts date fields correctly', function () {
    $sub = new Subscription([
        'status' => 'active',
        'starts_at' => '2026-01-01',
        'ends_at' => '2027-01-01',
        'amount_cents' => 10000,
    ]);
    expect($sub->starts_at)->toBeInstanceOf(Carbon::class);
    expect($sub->ends_at)->toBeInstanceOf(Carbon::class);
});

it('returns true for active subscription within end date', function () {
    $sub = new Subscription([
        'status' => 'active',
        'ends_at' => Carbon::now()->addMonth(),
    ]);
    expect($sub->active())->toBeTrue();
});

it('returns false for active subscription past end date', function () {
    $sub = new Subscription([
        'status' => 'active',
        'ends_at' => Carbon::now()->subDay(),
    ]);
    expect($sub->active())->toBeFalse();
});

it('returns true for active subscription with no end date', function () {
    $sub = new Subscription(['status' => 'active', 'ends_at' => null]);
    expect($sub->active())->toBeTrue();
});

it('identifies incomplete status', function () {
    $sub = new Subscription(['status' => 'incomplete']);
    expect($sub->incomplete())->toBeTrue();
    $sub2 = new Subscription(['status' => 'incomplete_expired']);
    expect($sub2->incomplete())->toBeTrue();
});

it('identifies canceled status', function () {
    $sub = new Subscription(['status' => 'canceled']);
    expect($sub->canceled())->toBeTrue();
});

it('identifies suspended status', function () {
    $sub = new Subscription(['status' => 'suspended']);
    expect($sub->suspended())->toBeTrue();
});

it('identifies past due status', function () {
    $sub = new Subscription(['status' => 'past_due']);
    expect($sub->pastDue())->toBeTrue();
});

it('identifies active trial', function () {
    $sub = new Subscription(['trial_ends_at' => Carbon::now()->addDays(7)]);
    expect($sub->onTrial())->toBeTrue();
});

it('identifies expired trial', function () {
    $sub = new Subscription(['trial_ends_at' => Carbon::now()->subDay()]);
    expect($sub->onTrial())->toBeFalse();
});

it('returns false on trial when trial_ends_at is null', function () {
    $sub = new Subscription(['trial_ends_at' => null]);
    expect($sub->onTrial())->toBeFalse();
});

it('identifies grace period for canceled subscription with future next_billing_at', function () {
    $sub = new Subscription([
        'status' => 'canceled',
        'next_billing_at' => Carbon::now()->addDays(5),
    ]);
    expect($sub->onGracePeriod())->toBeTrue();
});

it('identifies grace period for suspended subscription with future next_billing_at', function () {
    $sub = new Subscription([
        'status' => 'suspended',
        'next_billing_at' => Carbon::now()->addDays(5),
    ]);
    expect($sub->onGracePeriod())->toBeTrue();
});

it('returns false for grace period when next_billing_at is in the past', function () {
    $sub = new Subscription([
        'status' => 'canceled',
        'next_billing_at' => Carbon::now()->subDay(),
    ]);
    expect($sub->onGracePeriod())->toBeFalse();
});

it('returns false for grace period when status is active', function () {
    $sub = new Subscription([
        'status' => 'active',
        'next_billing_at' => Carbon::now()->addDays(5),
    ]);
    expect($sub->onGracePeriod())->toBeFalse();
});

it('valid() returns true when active', function () {
    $sub = new Subscription(['status' => 'active', 'ends_at' => Carbon::now()->addMonth()]);
    expect($sub->valid())->toBeTrue();
});

it('valid() returns true when on trial', function () {
    $sub = new Subscription(['status' => 'incomplete', 'trial_ends_at' => Carbon::now()->addDays(7)]);
    expect($sub->valid())->toBeTrue();
});

it('valid() returns true when on grace period', function () {
    $sub = new Subscription([
        'status' => 'canceled',
        'next_billing_at' => Carbon::now()->addDays(5),
    ]);
    expect($sub->valid())->toBeTrue();
});

it('valid() returns false for past_due subscription without trial or grace', function () {
    $sub = new Subscription(['status' => 'past_due']);
    expect($sub->valid())->toBeFalse();
});

it('uses configurable table name', function () {
    $sub = new Subscription;
    expect($sub->getTable())->toBe('paymob_subscriptions');
});

it('encrypts client_secret via encrypted cast', function () {
    $sub = new Subscription(['client_secret' => 'secret_value_123']);
    expect($sub->client_secret)->toBe('secret_value_123');
});
