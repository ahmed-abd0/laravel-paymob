<?php

use Paymob\Laravel\Enums\PlanFrequency;

it('maps each frequency to its correct integer value', function () {
    expect(PlanFrequency::WEEKLY->value)->toBe(7);
    expect(PlanFrequency::BIWEEKLY->value)->toBe(15);
    expect(PlanFrequency::MONTHLY->value)->toBe(30);
    expect(PlanFrequency::BIMONTHLY->value)->toBe(60);
    expect(PlanFrequency::QUARTERLY->value)->toBe(90);
    expect(PlanFrequency::SEMIANNUAL->value)->toBe(180);
    expect(PlanFrequency::ANNUAL->value)->toBe(360);
});

it('provides exactly seven frequency cases', function () {
    expect(PlanFrequency::cases())->toHaveCount(7);
});
