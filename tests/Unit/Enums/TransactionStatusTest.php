<?php

use Paymob\Laravel\Enums\TransactionStatus;

it('identifies refunded transactions', function () {
    expect(TransactionStatus::fromPayload(['is_refunded' => true]))->toBe(TransactionStatus::REFUNDED);
});

it('identifies voided transactions', function () {
    expect(TransactionStatus::fromPayload(['is_voided' => true]))->toBe(TransactionStatus::VOIDED);
});

it('identifies captured transactions', function () {
    expect(TransactionStatus::fromPayload(['is_capture' => true]))->toBe(TransactionStatus::CAPTURED);
    expect(TransactionStatus::fromPayload(['is_captured' => true]))->toBe(TransactionStatus::CAPTURED);
});

it('identifies pending transactions', function () {
    expect(TransactionStatus::fromPayload(['pending' => true, 'success' => false]))->toBe(TransactionStatus::PENDING);
});

it('identifies successful transactions', function () {
    expect(TransactionStatus::fromPayload(['success' => true]))->toBe(TransactionStatus::SUCCEEDED);
});

it('identifies failed transactions', function () {
    expect(TransactionStatus::fromPayload(['success' => false]))->toBe(TransactionStatus::FAILED);
});

it('prefers refunded over other statuses', function () {
    expect(TransactionStatus::fromPayload([
        'is_refunded' => true, 'success' => true, 'is_capture' => true,
    ]))->toBe(TransactionStatus::REFUNDED);
});

it('prefers voided over captured', function () {
    expect(TransactionStatus::fromPayload([
        'is_voided' => true, 'is_capture' => true,
    ]))->toBe(TransactionStatus::VOIDED);
});

it('prefers captured over pending', function () {
    expect(TransactionStatus::fromPayload([
        'is_capture' => true, 'pending' => true,
    ]))->toBe(TransactionStatus::CAPTURED);
});
