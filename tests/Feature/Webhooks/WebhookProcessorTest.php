<?php

use Illuminate\Support\Facades\Event;
use Paymob\Laravel\Events\PaymentMethodUpdated;
use Paymob\Laravel\Events\SubscriptionUpdated;
use Paymob\Laravel\Events\TransactionUpdated;
use Paymob\Laravel\Events\WebhookHandled;
use Paymob\Laravel\Models\PaymentMethod;
use Paymob\Laravel\Models\Subscription;
use Paymob\Laravel\Models\Transaction;
use Paymob\Laravel\Models\WebhookCall;
use Paymob\Laravel\Tests\Fixtures\Fixtures;
use Paymob\Laravel\Webhooks\WebhookProcessor;

beforeEach(function () {
    $this->setUpConfig();
    $this->ensureUserTable();
    config()->set('paymob.webhooks.dispatch_after_commit', false);
    Event::fake([TransactionUpdated::class, SubscriptionUpdated::class, PaymentMethodUpdated::class, WebhookHandled::class]);
    $this->billable = $this->createBillable(['name' => 'Test User', 'email' => 'test@example.com']);
    $this->billable->save();
});

it('creates a new transaction from webhook payload', function () {
    $call = WebhookCall::create([
        'type' => 'transaction', 'payload_hash' => hash('sha256', 'tx1'),
        'payload' => Fixtures::transactionPayload(), 'valid_signature' => true, 'status' => 'pending',
    ]);
    app(WebhookProcessor::class)->process($call);
    $tx = Transaction::where('paymob_id', '98765')->first();
    expect($tx)->not->toBeNull();
    expect($tx->status->value)->toBe('succeeded');
    expect($tx->amount_cents)->toBe(10000);
    expect($tx->currency)->toBe('EGP');
    expect($tx->order_id)->toBe('12345');
    expect($tx->success)->toBeTrue();
});

it('links transaction to subscription via reference matching', function () {
    $sub = Subscription::create([
        'billable_type' => get_class($this->billable), 'billable_id' => $this->billable->getKey(),
        'remote_plan_id' => '88888', 'reference' => 'ORDER-001', 'amount_cents' => 49900, 'currency' => 'EGP',
    ]);
    $call = WebhookCall::create([
        'type' => 'transaction', 'payload_hash' => hash('sha256', 'tx2'),
        'payload' => Fixtures::transactionPayload(), 'valid_signature' => true, 'status' => 'pending',
    ]);
    app(WebhookProcessor::class)->process($call);
    $tx = Transaction::where('paymob_id', '98765')->first();
    expect($tx->subscription_id)->toBe($sub->getKey());
});

it('updates existing transaction on duplicate paymob_id', function () {
    $call1 = WebhookCall::create([
        'type' => 'transaction', 'payload_hash' => hash('sha256', 'tx3a'),
        'payload' => Fixtures::transactionPayload(), 'valid_signature' => true, 'status' => 'pending',
    ]);
    app(WebhookProcessor::class)->process($call1);
    expect(Transaction::where('paymob_id', '98765')->count())->toBe(1);
    $modified = Fixtures::transactionPayload(['obj' => array_merge(Fixtures::transactionPayload()['obj'], ['success' => false])]);
    $call2 = WebhookCall::create([
        'type' => 'transaction', 'payload_hash' => hash('sha256', 'tx3b'),
        'payload' => $modified, 'valid_signature' => true, 'status' => 'pending',
    ]);
    app(WebhookProcessor::class)->process($call2);
    expect(Transaction::where('paymob_id', '98765')->count())->toBe(1);
    expect(Transaction::where('paymob_id', '98765')->first()->success)->toBeFalse();
});

it('updates subscription status to active on successful payment', function () {
    $sub = Subscription::create([
        'billable_type' => get_class($this->billable), 'billable_id' => $this->billable->getKey(),
        'remote_plan_id' => '88888', 'reference' => 'ORDER-001', 'amount_cents' => 49900, 'currency' => 'EGP',
        'status' => 'incomplete',
    ]);
    $call = WebhookCall::create([
        'type' => 'transaction', 'payload_hash' => hash('sha256', 'tx4'),
        'payload' => Fixtures::transactionPayload(), 'valid_signature' => true, 'status' => 'pending',
    ]);
    app(WebhookProcessor::class)->process($call);
    expect($sub->fresh()->status->value)->toBe('active');
});

it('sets subscription to past_due on failed payment', function () {
    $sub = Subscription::create([
        'billable_type' => get_class($this->billable), 'billable_id' => $this->billable->getKey(),
        'remote_plan_id' => '88888', 'reference' => 'ORDER-001', 'amount_cents' => 49900, 'currency' => 'EGP',
        'status' => 'active',
    ]);
    $payload = Fixtures::transactionPayload(['obj' => array_merge(Fixtures::transactionPayload()['obj'], ['success' => false])]);
    $call = WebhookCall::create([
        'type' => 'transaction', 'payload_hash' => hash('sha256', 'tx5'),
        'payload' => $payload, 'valid_signature' => true, 'status' => 'pending',
    ]);
    app(WebhookProcessor::class)->process($call);
    expect($sub->fresh()->status->value)->toBe('past_due');
});

it('creates payment method from token webhook', function () {
    $call = WebhookCall::create([
        'type' => 'token', 'payload_hash' => hash('sha256', 'tk1'),
        'payload' => Fixtures::tokenPayload(), 'valid_signature' => true, 'status' => 'pending',
    ]);
    app(WebhookProcessor::class)->process($call);
    $pm = PaymentMethod::where('paymob_id', '55555')->first();
    expect($pm)->not->toBeNull();
    expect($pm->masked_pan)->toBe('411111******1111');
    expect($pm->brand)->toBe('VISA');
});

it('links payment method to subscription via order_id', function () {
    $sub = Subscription::create([
        'billable_type' => get_class($this->billable), 'billable_id' => $this->billable->getKey(),
        'remote_plan_id' => '88888', 'reference' => 'ref-link', 'amount_cents' => 49900, 'currency' => 'EGP',
    ]);
    Transaction::create([
        'paymob_id' => '11111', 'order_id' => '12345', 'status' => 'succeeded', 'amount_cents' => 10000,
        'currency' => 'EGP', 'subscription_id' => $sub->getKey(),
        'billable_type' => get_class($this->billable), 'billable_id' => $this->billable->getKey(),
    ]);
    $call = WebhookCall::create([
        'type' => 'token', 'payload_hash' => hash('sha256', 'tk2'),
        'payload' => Fixtures::tokenPayload(), 'valid_signature' => true, 'status' => 'pending',
    ]);
    app(WebhookProcessor::class)->process($call);
    $pm = PaymentMethod::where('paymob_id', '55555')->first();
    expect($pm->subscription_id)->toBe($sub->getKey());
});

it('updates subscription status from state field', function () {
    $sub = Subscription::create([
        'billable_type' => get_class($this->billable), 'billable_id' => $this->billable->getKey(),
        'remote_plan_id' => '88888', 'reference' => 'ref-state', 'amount_cents' => 49900, 'currency' => 'EGP',
        'status' => 'incomplete', 'paymob_id' => '77777',
    ]);
    $call = WebhookCall::create([
        'type' => 'subscription', 'payload_hash' => hash('sha256', 'sub1'),
        'payload' => Fixtures::subscriptionPayload(), 'valid_signature' => true, 'status' => 'pending',
    ]);
    app(WebhookProcessor::class)->process($call);
    expect($sub->fresh()->status->value)->toBe('active');
});

it('matches subscription by reference when paymob_id not available', function () {
    $sub = Subscription::create([
        'billable_type' => get_class($this->billable), 'billable_id' => $this->billable->getKey(),
        'remote_plan_id' => '88888', 'reference' => 'sub-ref-123', 'amount_cents' => 49900, 'currency' => 'EGP',
        'status' => 'incomplete',
    ]);
    $payload = Fixtures::subscriptionPayload(['obj' => array_merge(Fixtures::subscriptionPayload()['obj'], [
        'reference' => 'sub-ref-123', 'id' => null,
    ])]);
    $call = WebhookCall::create([
        'type' => 'subscription', 'payload_hash' => hash('sha256', 'sub2'),
        'payload' => $payload, 'valid_signature' => true, 'status' => 'pending',
    ]);
    app(WebhookProcessor::class)->process($call);
    expect($sub->fresh()->status->value)->toBe('active');
});

it('matches subscription by initial_transaction when other fields missing', function () {
    $sub = Subscription::create([
        'billable_type' => get_class($this->billable), 'billable_id' => $this->billable->getKey(),
        'remote_plan_id' => '88888', 'reference' => 'ref-init', 'amount_cents' => 49900, 'currency' => 'EGP',
        'status' => 'incomplete',
    ]);
    Transaction::create([
        'paymob_id' => '98765', 'order_id' => '99999', 'status' => 'succeeded', 'amount_cents' => 10000,
        'currency' => 'EGP', 'subscription_id' => $sub->getKey(),
        'billable_type' => get_class($this->billable), 'billable_id' => $this->billable->getKey(),
    ]);
    $payload = Fixtures::subscriptionPayload(['obj' => array_merge(Fixtures::subscriptionPayload()['obj'], [
        'id' => null, 'reference' => null, 'initial_transaction' => 98765,
    ])]);
    $call = WebhookCall::create([
        'type' => 'subscription', 'payload_hash' => hash('sha256', 'sub3'),
        'payload' => $payload, 'valid_signature' => true, 'status' => 'pending',
    ]);
    app(WebhookProcessor::class)->process($call);
    expect($sub->fresh()->status->value)->toBe('active');
});

it('is idempotent for duplicate processing', function () {
    $call = WebhookCall::create([
        'type' => 'transaction', 'payload_hash' => hash('sha256', 'idem'),
        'payload' => Fixtures::transactionPayload(), 'valid_signature' => true, 'status' => 'processed',
    ]);
    app(WebhookProcessor::class)->process($call);
    expect(Transaction::count())->toBe(0);
    expect($call->fresh()->attempts)->toBe(0);
});

it('throws and rolls back on failed subscription matching', function () {
    $call = WebhookCall::create([
        'type' => 'subscription', 'payload_hash' => hash('sha256', 'fail1'),
        'payload' => Fixtures::subscriptionPayload(['obj' => array_merge(Fixtures::subscriptionPayload()['obj'], [
            'id' => 99999, 'reference' => null, 'initial_transaction' => null,
        ])]), 'valid_signature' => true, 'status' => 'pending',
    ]);
    app(WebhookProcessor::class)->process($call);
})->throws(RuntimeException::class, 'Unable to match');

it('redacts secrets from stored transaction payload', function () {
    $obj = Fixtures::transactionPayload()['obj'];
    $obj['token'] = 'secret_token_value';
    $obj['client_secret'] = 'secret_client_value';
    $obj['payment_token'] = 'secret_payment_value';
    $call = WebhookCall::create([
        'type' => 'transaction', 'payload_hash' => hash('sha256', 'redact'),
        'payload' => ['obj' => $obj], 'valid_signature' => true, 'status' => 'pending',
    ]);
    app(WebhookProcessor::class)->process($call);
    $tx = Transaction::where('paymob_id', '98765')->first();
    expect($tx->payload)->not->toHaveKey('token');
    expect($tx->payload)->not->toHaveKey('client_secret');
    expect($tx->payload)->not->toHaveKey('payment_token');
});
