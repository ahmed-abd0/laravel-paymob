<?php

use Paymob\Laravel\Enums\WebhookType;

it('detects transaction type from type field', function () {
    expect(WebhookType::detect(['type' => 'TRANSACTION']))->toBe(WebhookType::TRANSACTION);
});

it('detects token type from type field', function () {
    expect(WebhookType::detect(['type' => 'TOKEN']))->toBe(WebhookType::TOKEN);
});

it('detects subscription type from subscription_id and state', function () {
    expect(WebhookType::detect(['subscription_id' => 123, 'state' => 'activated']))->toBe(WebhookType::SUBSCRIPTION);
});

it('detects subscription type from plan_id and state', function () {
    expect(WebhookType::detect(['plan_id' => 100, 'state' => 'active']))->toBe(WebhookType::SUBSCRIPTION);
});

it('detects token type from obj fields', function () {
    expect(WebhookType::detect(['obj' => ['token' => 'abc', 'masked_pan' => '4111']]))->toBe(WebhookType::TOKEN);
});

it('detects transaction type from obj success field', function () {
    expect(WebhookType::detect(['obj' => ['success' => true]]))->toBe(WebhookType::TRANSACTION);
});

it('detects transaction type from success and amount_cents', function () {
    expect(WebhookType::detect(['success' => true, 'amount_cents' => 1000]))->toBe(WebhookType::TRANSACTION);
});

it('returns UNKNOWN for unrecognizable payloads', function () {
    expect(WebhookType::detect([]))->toBe(WebhookType::UNKNOWN);
    expect(WebhookType::detect(['foo' => 'bar']))->toBe(WebhookType::UNKNOWN);
});
