<?php

use Paymob\Laravel\Webhooks\SignatureVerifier;

it('verifies transaction callback hmac', function () {
    config()->set('paymob.keys.hmac', 'secret');
    $payload = ['obj' => [
        'amount_cents' => 1000, 'created_at' => '2026-01-01', 'currency' => 'EGP', 'error_occured' => false,
        'has_parent_transaction' => false, 'id' => 1, 'integration_id' => 2, 'is_3d_secure' => true, 'is_auth' => false,
        'is_capture' => false, 'is_refunded' => false, 'is_standalone_payment' => true, 'is_voided' => false,
        'order' => ['id' => 3], 'owner' => 4, 'pending' => false,
        'source_data' => ['pan' => '1234', 'sub_type' => 'Visa', 'type' => 'card'], 'success' => true
    ]];
    $values = ['1000','2026-01-01','EGP','false','false','1','2','true','false','false','false','true','false','3','4','false','1234','Visa','card','true'];
    $hmac = hash_hmac('sha512', implode('', $values), 'secret');
    expect(app(SignatureVerifier::class)->transaction($payload, $hmac))->toBeTrue();
});

it('verifies card token callback hmac', function () {
    config()->set('paymob.keys.hmac', 'secret');
    $obj = ['card_subtype' => 'MasterCard', 'created_at' => '2026-01-01', 'email' => 'a@example.com', 'id' => 1, 'masked_pan' => 'xxxx-1111', 'merchant_id' => 2, 'order_id' => 3, 'token' => 'token'];
    $hmac = hash_hmac('sha512', implode('', array_values($obj)), 'secret');
    expect(app(SignatureVerifier::class)->token(['type' => 'TOKEN', 'obj' => $obj], $hmac))->toBeTrue();
});
