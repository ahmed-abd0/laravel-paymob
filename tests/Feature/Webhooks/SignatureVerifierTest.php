<?php

use Paymob\Laravel\Exceptions\ConfigurationException;
use Paymob\Laravel\Tests\Fixtures\Fixtures;
use Paymob\Laravel\Webhooks\SignatureVerifier;

beforeEach(fn () => $this->setUpConfig());

it('returns true for valid transaction HMAC signature', function () {
    $secret = 'test_hmac_secret';
    config()->set('paymob.keys.hmac', $secret);
    $payload = Fixtures::transactionPayload();
    $obj = $payload['obj'];
    $values = [
        (string) $obj['amount_cents'], $obj['created_at'], $obj['currency'],
        $obj['error_occured'] ? 'true' : 'false', $obj['has_parent_transaction'] ? 'true' : 'false',
        (string) $obj['id'], (string) $obj['integration_id'],
        $obj['is_3d_secure'] ? 'true' : 'false', $obj['is_auth'] ? 'true' : 'false',
        $obj['is_capture'] ? 'true' : 'false', $obj['is_refunded'] ? 'true' : 'false',
        $obj['is_standalone_payment'] ? 'true' : 'false', $obj['is_voided'] ? 'true' : 'false',
        (string) $obj['order']['id'], $obj['owner'], $obj['pending'] ? 'true' : 'false',
        $obj['source_data']['pan'], $obj['source_data']['sub_type'], $obj['source_data']['type'],
        $obj['success'] ? 'true' : 'false',
    ];
    $hmac = hash_hmac('sha512', implode('', $values), $secret);
    expect(app(SignatureVerifier::class)->transaction($payload, $hmac))->toBeTrue();
});

it('returns false for invalid transaction HMAC signature', function () {
    config()->set('paymob.keys.hmac', 'test_hmac_secret');
    expect(app(SignatureVerifier::class)->transaction(Fixtures::transactionPayload(), 'bad_hmac'))->toBeFalse();
});

it('returns false when transaction signature is null', function () {
    config()->set('paymob.keys.hmac', 'test_hmac_secret');
    expect(app(SignatureVerifier::class)->transaction(Fixtures::transactionPayload(), null))->toBeFalse();
});

it('throws ConfigurationException when HMAC key not configured', function () {
    config()->set('paymob.keys.hmac', null);
    app(SignatureVerifier::class)->transaction(Fixtures::transactionPayload(), 'sig');
})->throws(ConfigurationException::class);

it('returns true when transaction HMAC verification is disabled', function () {
    config()->set('paymob.webhooks.verify_transaction_hmac', false);
    expect(app(SignatureVerifier::class)->transaction(Fixtures::transactionPayload(), null))->toBeTrue();
});

it('returns true for valid token HMAC signature', function () {
    $secret = 'token_secret';
    config()->set('paymob.keys.hmac', $secret);
    $payload = Fixtures::tokenPayload();
    $obj = $payload['obj'];
    $concatenated = implode('', [
        $obj['card_subtype'], $obj['created_at'], $obj['email'], (string) $obj['id'],
        $obj['masked_pan'], (string) $obj['merchant_id'], (string) $obj['order_id'], $obj['token'],
    ]);
    $hmac = hash_hmac('sha512', $concatenated, $secret);
    expect(app(SignatureVerifier::class)->token($payload, $hmac))->toBeTrue();
});

it('returns false for invalid token HMAC signature', function () {
    config()->set('paymob.keys.hmac', 'token_secret');
    expect(app(SignatureVerifier::class)->token(Fixtures::tokenPayload(), 'wrong'))->toBeFalse();
});

it('returns true when subscription secret matches', function () {
    config()->set('paymob.webhooks.subscription_secret', 'sub_secret');
    expect(app(SignatureVerifier::class)->subscription('sub_secret'))->toBeTrue();
});

it('returns false when subscription secret does not match', function () {
    config()->set('paymob.webhooks.subscription_secret', 'sub_secret');
    expect(app(SignatureVerifier::class)->subscription('wrong'))->toBeFalse();
});

it('returns true when no subscription secret configured and require is false', function () {
    config()->set('paymob.webhooks.subscription_secret', null);
    config()->set('paymob.webhooks.require_subscription_secret', false);
    expect(app(SignatureVerifier::class)->subscription(null))->toBeTrue();
});

it('returns false when no subscription secret configured and require is true', function () {
    config()->set('paymob.webhooks.subscription_secret', null);
    config()->set('paymob.webhooks.require_subscription_secret', true);
    expect(app(SignatureVerifier::class)->subscription(null))->toBeFalse();
});
