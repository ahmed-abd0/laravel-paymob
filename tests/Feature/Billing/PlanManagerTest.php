<?php

use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Billing\PlanManager;
use Paymob\Laravel\Data\SubscriptionPlanData;
use Paymob\Laravel\Enums\PlanType;
use Paymob\Laravel\Exceptions\ApiException;
use Paymob\Laravel\Models\Plan;
use Paymob\Laravel\Tests\Fixtures\Fixtures;

beforeEach(function () {
    $this->setUpConfig();
    Http::preventStrayRequests();
});

it('creates a plan locally after successful remote creation', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans' => Http::response(Fixtures::planResponse(), 200),
    ]);

    $data = new SubscriptionPlanData(name: 'Test Plan', amountCents: 49900, integration: 465538);
    $plan = app(PlanManager::class)->create($data);

    expect($plan)->toBeInstanceOf(Plan::class)
        ->and($plan->paymob_id)->toBe('88888')
        ->and($plan->name)->toBe('Test Monthly Plan')
        ->and($plan->amount_cents)->toBe(49900)
        ->and($plan->currency)->toBe('EGP')
        ->and($plan->integration_id)->toBe(465538)
        ->and($plan->active)->toBeTrue();

    $plan->refresh();
    expect($plan->paymob_id)->toBe('88888');
});

it('uses default webhook_url when subscription route exists', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans' => Http::response(Fixtures::planResponse(['webhook_url' => 'https://example.com/hook']), 200),
    ]);

    $data = new SubscriptionPlanData(name: 'Test Plan', amountCents: 49900, integration: 465538);
    app(PlanManager::class)->create($data);

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return isset($payload['webhook_url']) && $payload['webhook_url'] !== null;
    });
});

it('creates a plan with explicit webhook_url', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans' => Http::response(Fixtures::planResponse(), 200),
    ]);

    $data = new SubscriptionPlanData(name: 'Test Plan', amountCents: 49900, integration: 465538, webhookUrl: 'https://example.com/hook');
    app(PlanManager::class)->create($data);

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return ($payload['webhook_url'] ?? null) === 'https://example.com/hook';
    });
});

it('update modifies an existing plan locally', function () {
    $existing = Plan::create([
        'paymob_id' => '88888',
        'name' => 'Old Plan',
        'frequency' => 30,
        'plan_type' => PlanType::RENT,
        'amount_cents' => 49900,
        'currency' => 'EGP',
        'integration_id' => 465538,
        'active' => true,
    ]);

    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans/*' => Http::response(['id' => 88888, 'name' => 'Updated Plan', 'amount_cents' => 59900], 200),
    ]);

    $plan = app(PlanManager::class)->update($existing, ['name' => 'Updated Plan', 'amount_cents' => 59900]);

    expect($plan->name)->toBe('Updated Plan')
        ->and($plan->amount_cents)->toBe(59900);

    $plan->refresh();
    expect($plan->name)->toBe('Updated Plan');
});

it('suspend sets active to false', function () {
    $plan = Plan::create([
        'paymob_id' => '88888',
        'name' => 'Plan',
        'frequency' => 30,
        'plan_type' => PlanType::RENT,
        'amount_cents' => 49900,
        'currency' => 'EGP',
        'integration_id' => 465538,
        'active' => true,
    ]);

    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans/*/suspend' => Http::response(['id' => 88888], 200),
    ]);

    $result = app(PlanManager::class)->suspend($plan);

    expect($result->active)->toBeFalse();

    $result->refresh();
    expect($result->active)->toBeFalse();
});

it('resume sets active to true', function () {
    $plan = Plan::create([
        'paymob_id' => '88888',
        'name' => 'Plan',
        'frequency' => 30,
        'plan_type' => PlanType::RENT,
        'amount_cents' => 49900,
        'currency' => 'EGP',
        'integration_id' => 465538,
        'active' => false,
    ]);

    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans/*/resume' => Http::response(['id' => 88888], 200),
    ]);

    $result = app(PlanManager::class)->resume($plan);

    expect($result->active)->toBeTrue();

    $result->refresh();
    expect($result->active)->toBeTrue();
});

it('sync updates plan from remote data', function () {
    $plan = Plan::create([
        'paymob_id' => '88888',
        'name' => 'Old Name',
        'frequency' => 30,
        'plan_type' => PlanType::RENT,
        'amount_cents' => 49900,
        'currency' => 'EGP',
        'integration_id' => 465538,
        'active' => true,
    ]);

    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans' => Http::response([
            Fixtures::planResponse(['name' => 'Synced Plan', 'amount_cents' => 79900]),
        ], 200),
    ]);

    $result = app(PlanManager::class)->sync($plan);

    expect($result->name)->toBe('Synced Plan')
        ->and($result->amount_cents)->toBe(79900);

    $result->refresh();
    expect($result->name)->toBe('Synced Plan');
});

it('sync returns unchanged plan when remote not found', function () {
    $plan = Plan::create([
        'paymob_id' => '99999',
        'name' => 'Unchanged Plan',
        'frequency' => 30,
        'plan_type' => PlanType::RENT,
        'amount_cents' => 49900,
        'currency' => 'EGP',
        'integration_id' => 465538,
        'active' => true,
    ]);

    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans' => Http::response([
            Fixtures::planResponse(['id' => 88888]),
        ], 200),
    ]);

    $result = app(PlanManager::class)->sync($plan);

    expect($result->name)->toBe('Unchanged Plan');
});

it('syncAll persists multiple plans from remote list', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans' => Http::response([
            Fixtures::planResponse(['id' => 88888, 'name' => 'Plan A']),
            Fixtures::planResponse(['id' => 88889, 'name' => 'Plan B']),
        ], 200),
    ]);

    $count = app(PlanManager::class)->syncAll();

    expect($count)->toBe(2);
    expect(Plan::where('paymob_id', '88888')->exists())->toBeTrue();
    expect(Plan::where('paymob_id', '88889')->exists())->toBeTrue();
});

it('syncAll handles paginated response with results key', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans' => Http::response([
            'results' => [
                Fixtures::planResponse(['id' => 88888]),
            ],
        ], 200),
    ]);

    $count = app(PlanManager::class)->syncAll();

    expect($count)->toBe(1);
    expect(Plan::where('paymob_id', '88888')->exists())->toBeTrue();
});

it('syncAll handles paginated response with data key', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans' => Http::response([
            'data' => [
                Fixtures::planResponse(['id' => 88888]),
                Fixtures::planResponse(['id' => 88889]),
                Fixtures::planResponse(['id' => 88890]),
            ],
        ], 200),
    ]);

    $count = app(PlanManager::class)->syncAll();

    expect($count)->toBe(3);
    expect(Plan::count())->toBe(3);
});

it('throws on api failure during create', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plans' => Http::response(['message' => 'Validation error'], 422),
    ]);

    $data = new SubscriptionPlanData(name: 'Bad Plan', amountCents: 49900, integration: 465538);
    app(PlanManager::class)->create($data);
})->throws(ApiException::class);
