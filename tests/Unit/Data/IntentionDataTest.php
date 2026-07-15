<?php

use Illuminate\Support\Str;
use Paymob\Laravel\Data\BillingData;
use Paymob\Laravel\Data\IntentionData;
use Paymob\Laravel\Data\Item;

function makeBilling(): BillingData
{
    return new BillingData(firstName: 'Test', lastName: 'User', email: 'test@example.com', phoneNumber: '+201000000000');
}

it('generates a UUID special reference when omitted', function () {
    $data = new IntentionData(amount: 1000, billingData: makeBilling(), paymentMethods: [465537]);
    $payload = $data->toArray();
    expect($payload['special_reference'])->not->toBeNull();
    expect(Str::isUuid($payload['special_reference']))->toBeTrue();
});

it('uses provided special reference', function () {
    $data = new IntentionData(amount: 1000, billingData: makeBilling(), paymentMethods: [465537], specialReference: 'ORDER-123');
    expect($data->toArray()['special_reference'])->toBe('ORDER-123');
});

it('serializes minimal payload correctly', function () {
    $data = new IntentionData(amount: 1000, billingData: makeBilling(), paymentMethods: [465537]);
    $payload = $data->toArray();
    expect($payload['amount'])->toBe(1000);
    expect($payload['currency'])->toBe('EGP');
    expect($payload['payment_methods'])->toBe([465537]);
    expect($payload)->toHaveKey('billing_data');
});

it('includes items when set', function () {
    $data = new IntentionData(amount: 10000, billingData: makeBilling(), paymentMethods: [465537]);
    $data->items(new Item('Plan', 10000));
    $payload = $data->toArray();
    expect($payload['items'])->toHaveCount(1);
    expect($payload['items'][0]['name'])->toBe('Plan');
});

it('includes extras when set', function () {
    $data = new IntentionData(amount: 1000, billingData: makeBilling(), paymentMethods: [465537]);
    $data->extras(['key' => 'value']);
    expect($data->toArray()['extras'])->toBe(['key' => 'value']);
});

it('includes card_tokens when set', function () {
    $data = new IntentionData(amount: 1000, billingData: makeBilling(), paymentMethods: [465537]);
    $data->cardTokens(['token_abc', 'token_def']);
    expect($data->toArray()['card_tokens'])->toBe(['token_abc', 'token_def']);
});

it('maps subscription_plan_id correctly', function () {
    $data = new IntentionData(amount: 1000, billingData: makeBilling(), paymentMethods: [465537], subscriptionPlanId: 999);
    expect($data->toArray()['subscription_plan_id'])->toBe(999);
});

it('maps subscriptionv2_id correctly', function () {
    $data = new IntentionData(amount: 1000, billingData: makeBilling(), paymentMethods: [465537], subscriptionId: 888);
    expect($data->toArray()['subscriptionv2_id'])->toBe(888);
});

it('rejects zero amount', function () {
    $data = new IntentionData(amount: 0, billingData: makeBilling(), paymentMethods: [465537]);
    $data->toArray();
})->throws(InvalidArgumentException::class, 'The intention amount must be greater than zero.');

it('rejects negative amount', function () {
    $data = new IntentionData(amount: -100, billingData: makeBilling(), paymentMethods: [465537]);
    $data->toArray();
})->throws(InvalidArgumentException::class, 'The intention amount must be greater than zero.');

it('rejects empty payment methods', function () {
    $data = new IntentionData(amount: 1000, billingData: makeBilling(), paymentMethods: []);
    $data->toArray();
})->throws(InvalidArgumentException::class, 'Valid Paymob integration IDs are required.');

it('rejects non-positive integration IDs', function () {
    $data = new IntentionData(amount: 1000, billingData: makeBilling(), paymentMethods: [0]);
    $data->toArray();
})->throws(InvalidArgumentException::class, 'Valid Paymob integration IDs are required.');

it('rejects mismatched item totals', function () {
    $data = new IntentionData(amount: 1000, billingData: makeBilling(), paymentMethods: [465537]);
    $data->items(new Item('Plan', 500));
    $data->toArray();
})->throws(InvalidArgumentException::class, 'The intention amount must equal the sum of item amounts.');

it('validates item total matches when multiple items provided', function () {
    $data = new IntentionData(amount: 1000, billingData: makeBilling(), paymentMethods: [465537]);
    $data->items(new Item('A', 400), new Item('B', 600));
    expect($data->toArray()['amount'])->toBe(1000);
});

it('excludes null optional fields from payload', function () {
    $data = new IntentionData(amount: 1000, billingData: makeBilling(), paymentMethods: [465537]);
    $payload = $data->toArray();
    expect($payload)->not->toHaveKey('notification_url');
    expect($payload)->not->toHaveKey('redirection_url');
    expect($payload)->not->toHaveKey('expiration');
    expect($payload)->not->toHaveKey('subscription_plan_id');
});

it('uses default currency EGP', function () {
    $data = new IntentionData(amount: 1000, billingData: makeBilling(), paymentMethods: [465537]);
    expect($data->toArray()['currency'])->toBe('EGP');
});

it('allows custom currency', function () {
    $data = new IntentionData(amount: 1000, billingData: makeBilling(), paymentMethods: [465537], currency: 'SAR');
    expect($data->toArray()['currency'])->toBe('SAR');
});
