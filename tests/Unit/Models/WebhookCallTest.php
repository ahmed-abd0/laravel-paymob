<?php

use Paymob\Laravel\Models\WebhookCall;

it('uses UUID as primary key', function () {
    $call = new WebhookCall;
    expect($call->getIncrementing())->toBeFalse();
    expect($call->getKeyType())->toBe('string');
});

it('casts valid_signature to boolean', function () {
    $call = new WebhookCall(['valid_signature' => true]);
    expect($call->valid_signature)->toBeTrue();
});

it('encrypts payload via encrypted array cast', function () {
    $payload = ['obj' => ['id' => 123, 'success' => true]];
    $call = new WebhookCall(['payload' => $payload]);
    expect($call->payload)->toBe($payload);
});

it('uses configurable table name', function () {
    $call = new WebhookCall;
    expect($call->getTable())->toBe('paymob_webhook_calls');
});

it('can be persisted with UUID and retrieved', function () {
    $call = WebhookCall::create([
        'type' => 'transaction',
        'payload_hash' => hash('sha256', 'test'),
        'payload' => ['test' => true],
        'valid_signature' => true,
        'status' => 'pending',
    ]);
    expect($call->getKey())->not->toBeNull();
    $found = WebhookCall::find($call->getKey());
    expect($found->type)->toBe('transaction');
    expect($found->status)->toBe('pending');
});
