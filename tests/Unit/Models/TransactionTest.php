<?php

use Carbon\Carbon;
use Paymob\Laravel\Enums\TransactionStatus;
use Paymob\Laravel\Models\Transaction;

it('casts status to TransactionStatus enum', function () {
    $tx = new Transaction(['status' => 'succeeded']);
    expect($tx->status)->toBeInstanceOf(TransactionStatus::class);
    expect($tx->status)->toBe(TransactionStatus::SUCCEEDED);
});

it('casts amount fields to integer', function () {
    $tx = new Transaction([
        'amount_cents' => 10000,
        'refunded_amount_cents' => 2000,
        'captured_amount_cents' => 8000,
    ]);
    expect($tx->amount_cents)->toBeInt()->toBe(10000);
    expect($tx->refunded_amount_cents)->toBeInt()->toBe(2000);
    expect($tx->captured_amount_cents)->toBeInt()->toBe(8000);
});

it('casts boolean fields correctly', function () {
    $tx = new Transaction([
        'success' => true,
        'pending' => false,
        'is_refund' => true,
        'is_void' => false,
        'is_capture' => true,
    ]);
    expect($tx->success)->toBeTrue();
    expect($tx->pending)->toBeFalse();
    expect($tx->is_refund)->toBeTrue();
    expect($tx->is_void)->toBeFalse();
    expect($tx->is_capture)->toBeTrue();
});

it('identifies succeeded transaction', function () {
    $tx = new Transaction(['status' => 'succeeded']);
    expect($tx->succeeded())->toBeTrue();
    expect($tx->failed())->toBeFalse();
});

it('identifies failed transaction', function () {
    $tx = new Transaction(['status' => 'failed']);
    expect($tx->failed())->toBeTrue();
    expect($tx->succeeded())->toBeFalse();
});

it('casts occurred_at to datetime', function () {
    $tx = new Transaction(['occurred_at' => '2026-01-15 10:00:00']);
    expect($tx->occurred_at)->toBeInstanceOf(Carbon::class);
});

it('uses configurable table name', function () {
    $tx = new Transaction;
    expect($tx->getTable())->toBe('paymob_transactions');
});

it('can be persisted and retrieved', function () {
    $tx = Transaction::create([
        'paymob_id' => '12345',
        'status' => 'succeeded',
        'amount_cents' => 10000,
        'currency' => 'EGP',
        'success' => true,
    ]);
    $found = Transaction::find($tx->id);
    expect($found->paymob_id)->toBe('12345');
    expect($found->status)->toBe(TransactionStatus::SUCCEEDED);
});
