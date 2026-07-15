<?php

use Paymob\Laravel\Data\SubscriptionPlanData;
use Paymob\Laravel\Enums\PlanFrequency;
use Paymob\Laravel\Enums\PlanType;

it('serializes plan data with enum values', function () {
    $data = new SubscriptionPlanData(
        name: 'Monthly Plan',
        amountCents: 49900,
        integration: 465538,
        frequency: PlanFrequency::MONTHLY,
        planType: PlanType::RENT
    );
    $payload = $data->toArray();
    expect($payload['frequency'])->toBe(30);
    expect($payload['plan_type'])->toBe('rent');
    expect($payload['amount_cents'])->toBe(49900);
    expect($payload['integration'])->toBe(465538);
    expect($payload['name'])->toBe('Monthly Plan');
});

it('serializes plan data with integer values', function () {
    $data = new SubscriptionPlanData(
        name: 'Plan',
        amountCents: 10000,
        integration: 465538,
        frequency: 90,
        planType: 'purchase'
    );
    $payload = $data->toArray();
    expect($payload['frequency'])->toBe(90);
    expect($payload['plan_type'])->toBe('purchase');
});

it('includes optional fields when provided', function () {
    $data = new SubscriptionPlanData(
        name: 'Plan',
        amountCents: 10000,
        integration: 465538,
        reminderDays: 5,
        retrialDays: 3,
        numberOfDeductions: 12,
        useTransactionAmount: true,
        active: false,
        webhookUrl: 'https://example.com/hook'
    );
    $payload = $data->toArray();
    expect($payload['reminder_days'])->toBe(5);
    expect($payload['retrial_days'])->toBe(3);
    expect($payload['number_of_deductions'])->toBe(12);
    expect($payload['use_transaction_amount'])->toBeTrue();
    expect($payload['is_active'])->toBeFalse();
    expect($payload['webhook_url'])->toBe('https://example.com/hook');
});

it('uses default values', function () {
    $data = new SubscriptionPlanData(name: 'Plan', amountCents: 10000, integration: 465538);
    $payload = $data->toArray();
    expect($payload['reminder_days'])->toBe(2);
    expect($payload['retrial_days'])->toBe(2);
    expect($payload['use_transaction_amount'])->toBeFalse();
    expect($payload['is_active'])->toBeTrue();
});

it('omits null optional fields', function () {
    $data = new SubscriptionPlanData(name: 'Plan', amountCents: 10000, integration: 465538);
    $payload = $data->toArray();
    expect($payload)->not->toHaveKey('number_of_deductions');
    expect($payload)->not->toHaveKey('webhook_url');
});

it('rejects unsupported frequency', function () {
    $data = new SubscriptionPlanData(name: 'Plan', amountCents: 10000, integration: 465538, frequency: 100);
    $data->toArray();
})->throws(InvalidArgumentException::class, 'Unsupported Paymob subscription frequency.');

it('rejects zero amount', function () {
    $data = new SubscriptionPlanData(name: 'Plan', amountCents: 0, integration: 465538);
    $data->toArray();
})->throws(InvalidArgumentException::class);

it('rejects negative integration ID', function () {
    $data = new SubscriptionPlanData(name: 'Plan', amountCents: 10000, integration: -1);
    $data->toArray();
})->throws(InvalidArgumentException::class);
