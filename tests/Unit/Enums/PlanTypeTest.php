<?php

use Paymob\Laravel\Enums\PlanType;

it('contains all expected plan types', function () {
    $values = collect(PlanType::cases())->pluck('value')->values();
    expect($values)->toContain('rent');
    expect($values)->toContain('installment');
    expect($values)->toContain('purchase');
    expect($values)->toContain('bundle');
    expect($values)->toContain('merchant_subscription');
    expect($values)->toContain('other');
});
