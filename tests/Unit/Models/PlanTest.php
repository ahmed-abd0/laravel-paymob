<?php

use Paymob\Laravel\Enums\PlanType;
use Paymob\Laravel\Models\Plan;

it('casts attributes correctly', function () {
    $plan = new Plan([
        'paymob_id' => '123',
        'name' => 'Test Plan',
        'frequency' => 30,
        'plan_type' => 'rent',
        'amount_cents' => 49900,
        'integration_id' => 465538,
        'reminder_days' => 2,
        'retrial_days' => 2,
        'use_transaction_amount' => false,
        'active' => true,
    ]);
    expect($plan->frequency)->toBeInt()->toBe(30);
    expect($plan->amount_cents)->toBeInt()->toBe(49900);
    expect($plan->integration_id)->toBeInt()->toBe(465538);
    expect($plan->reminder_days)->toBeInt()->toBe(2);
    expect($plan->retrial_days)->toBeInt()->toBe(2);
    expect($plan->use_transaction_amount)->toBeFalse();
    expect($plan->active)->toBeTrue();
    expect($plan->plan_type)->toBeInstanceOf(PlanType::class);
});

it('uses configurable table name', function () {
    config()->set('paymob.tables.plans', 'custom_plans');
    $plan = new Plan;
    expect($plan->getTable())->toBe('custom_plans');
});

it('uses default table name', function () {
    $plan = new Plan;
    expect($plan->getTable())->toBe('paymob_plans');
});

it('has fillable guarded by guarded array', function () {
    $plan = new Plan;
    $plan->fill(['paymob_id' => '123', 'name' => 'Plan', 'frequency' => 30]);
    expect($plan->paymob_id)->toBe('123');
    expect($plan->name)->toBe('Plan');
});

it('can be persisted and retrieved', function () {
    $plan = Plan::create([
        'paymob_id' => '999',
        'name' => 'Persisted Plan',
        'frequency' => 30,
        'amount_cents' => 49900,
        'integration_id' => 465538,
        'active' => true,
    ]);
    $found = Plan::find($plan->id);
    expect($found->name)->toBe('Persisted Plan');
    expect($found->paymob_id)->toBe('999');
});
