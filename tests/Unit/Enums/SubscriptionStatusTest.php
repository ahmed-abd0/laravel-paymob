<?php

use Paymob\Laravel\Enums\SubscriptionStatus;

it('maps active and activated to ACTIVE', function () {
    expect(SubscriptionStatus::fromPaymob('active'))->toBe(SubscriptionStatus::ACTIVE);
    expect(SubscriptionStatus::fromPaymob('activated'))->toBe(SubscriptionStatus::ACTIVE);
});

it('maps suspended and paused to SUSPENDED', function () {
    expect(SubscriptionStatus::fromPaymob('suspended'))->toBe(SubscriptionStatus::SUSPENDED);
    expect(SubscriptionStatus::fromPaymob('paused'))->toBe(SubscriptionStatus::SUSPENDED);
});

it('maps cancelled and canceled to CANCELED', function () {
    expect(SubscriptionStatus::fromPaymob('cancelled'))->toBe(SubscriptionStatus::CANCELED);
    expect(SubscriptionStatus::fromPaymob('canceled'))->toBe(SubscriptionStatus::CANCELED);
});

it('maps failed states to PAST_DUE', function () {
    expect(SubscriptionStatus::fromPaymob('past_due'))->toBe(SubscriptionStatus::PAST_DUE);
    expect(SubscriptionStatus::fromPaymob('failed'))->toBe(SubscriptionStatus::PAST_DUE);
    expect(SubscriptionStatus::fromPaymob('payment_failed'))->toBe(SubscriptionStatus::PAST_DUE);
});

it('maps expired and ended to EXPIRED', function () {
    expect(SubscriptionStatus::fromPaymob('expired'))->toBe(SubscriptionStatus::EXPIRED);
    expect(SubscriptionStatus::fromPaymob('ended'))->toBe(SubscriptionStatus::EXPIRED);
});

it('maps unknown values to INCOMPLETE', function () {
    expect(SubscriptionStatus::fromPaymob('bogus'))->toBe(SubscriptionStatus::INCOMPLETE);
    expect(SubscriptionStatus::fromPaymob(null))->toBe(SubscriptionStatus::INCOMPLETE);
    expect(SubscriptionStatus::fromPaymob(''))->toBe(SubscriptionStatus::INCOMPLETE);
});

it('is case insensitive', function () {
    expect(SubscriptionStatus::fromPaymob('ACTIVE'))->toBe(SubscriptionStatus::ACTIVE);
    expect(SubscriptionStatus::fromPaymob('Activated'))->toBe(SubscriptionStatus::ACTIVE);
});
