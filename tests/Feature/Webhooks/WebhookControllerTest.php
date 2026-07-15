<?php

use Illuminate\Support\Facades\Route;
use Paymob\Laravel\Http\Controllers\WebhookController;
use Paymob\Laravel\Models\PaymentMethod;
use Paymob\Laravel\Models\Subscription;
use Paymob\Laravel\Models\Transaction;
use Paymob\Laravel\Models\WebhookCall;
use Paymob\Laravel\Tests\Fixtures\Fixtures;

beforeEach(function () {
    $this->setUpConfig();
    $this->ensureUserTable();
    config()->set('paymob.webhooks.verify_transaction_hmac', false);
    Route::post('/test/webhooks', [WebhookController::class, 'handle']);
    Route::post('/test/webhooks/transaction', [WebhookController::class, 'transaction']);
    Route::post('/test/webhooks/token', [WebhookController::class, 'token']);
    Route::post('/test/webhooks/subscription', [WebhookController::class, 'subscription']);
    $this->billable = $this->createBillable(['name' => 'Test User', 'email' => 'test@example.com']);
    $this->billable->save();
});

it('returns 204 for valid transaction webhook', function () {
    $this->postJson('/test/webhooks/transaction', Fixtures::transactionPayload())
        ->assertNoContent();
});

it('creates webhook call record with correct type and status', function () {
    $this->postJson('/test/webhooks/transaction', Fixtures::transactionPayload());
    $call = WebhookCall::where('type', 'transaction')->first();
    expect($call)->not->toBeNull();
    expect($call->status)->toBe('processed');
    expect($call->valid_signature)->toBeTrue();
    expect($call->external_id)->toBe('98765');
});

it('returns 401 for invalid transaction signature', function () {
    config()->set('paymob.webhooks.verify_transaction_hmac', true);
    $this->postJson('/test/webhooks/transaction', Fixtures::transactionPayload())
        ->assertStatus(401);
    expect(WebhookCall::where('status', 'rejected')->count())->toBe(1);
});

it('is idempotent for duplicate transaction requests', function () {
    $payload = Fixtures::transactionPayload();
    $this->postJson('/test/webhooks/transaction', $payload)->assertNoContent();
    $this->postJson('/test/webhooks/transaction', $payload)->assertNoContent();
    expect(Transaction::count())->toBe(1);
    expect(WebhookCall::where('type', 'transaction')->count())->toBe(1);
});

it('auto-detects subscription type from payload', function () {
    $sub = Subscription::create([
        'billable_type' => get_class($this->billable), 'billable_id' => $this->billable->getKey(),
        'remote_plan_id' => '88888', 'reference' => 'auto-sub-ref', 'amount_cents' => 49900,
        'currency' => 'EGP', 'status' => 'incomplete', 'paymob_id' => '77777',
    ]);
    $payload = [
        'plan_id' => 88888, 'state' => 'activated', 'amount_cents' => 49900, 'currency' => 'EGP',
        'id' => 77777, 'starts_at' => '2026-01-15', 'ends_at' => '2027-01-15', 'next_billing' => '2026-02-15',
    ];
    $this->postJson('/test/webhooks?secret=test_subscription_secret', $payload)->assertNoContent();
    expect($sub->fresh()->status->value)->toBe('active');
    $call = WebhookCall::where('type', 'subscription')->first();
    expect($call)->not->toBeNull();
});

it('rejects webhook when subscription secret does not match', function () {
    $payload = [
        'plan_id' => 88888, 'state' => 'activated', 'amount_cents' => 49900, 'currency' => 'EGP', 'id' => 77777,
    ];
    $this->postJson('/test/webhooks/subscription?secret=wrong_secret', $payload)->assertStatus(401);
});

it('processes token webhook and creates payment method', function () {
    $this->postJson('/test/webhooks/token', Fixtures::tokenPayload())->assertNoContent();
    $pm = PaymentMethod::where('paymob_id', '55555')->first();
    expect($pm)->not->toBeNull();
    expect($pm->masked_pan)->toBe('411111******1111');
    expect($pm->brand)->toBe('VISA');
    $call = WebhookCall::where('type', 'token')->first();
    expect($call->status)->toBe('processed');
});

it('stores sanitized payload without hmac or secrets', function () {
    $obj = Fixtures::transactionPayload()['obj'];
    $obj['token'] = 'secret_token_value';
    $obj['client_secret'] = 'secret_client_value';
    $obj['payment_token'] = 'secret_payment_value';
    $this->postJson('/test/webhooks/transaction', ['obj' => $obj, 'hmac' => 'leaked_hmac']);
    $call = WebhookCall::where('type', 'transaction')->first();
    expect($call->payload)->not->toHaveKey('hmac');
    expect($call->payload)->not->toHaveKey('secret');
    $tx = Transaction::first();
    expect($tx->payload)->not->toHaveKey('token');
    expect($tx->payload)->not->toHaveKey('client_secret');
    expect($tx->payload)->not->toHaveKey('payment_token');
});
