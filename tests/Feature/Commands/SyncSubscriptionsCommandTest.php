<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->setUpConfig();
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*/api/acceptance/subscription-plan*' => Http::response([], 200),
        '*/api/acceptance/subscriptions*' => Http::response([], 200),
    ]);
});

it('runs successfully with default options', function () {
    $this->artisan('paymob:sync-subscriptions')
        ->expectsOutputToContain('Expired')
        ->expectsOutputToContain('Paymob subscriptions')
        ->assertExitCode(0);
});

it('reports expired incomplete count', function () {
    $this->artisan('paymob:sync-subscriptions')
        ->expectsOutputToContain('Expired 0 stale incomplete subscriptions')
        ->assertExitCode(0);
});

it('syncs plans when --plans flag is used', function () {
    $this->artisan('paymob:sync-subscriptions', ['--plans' => true])
        ->expectsOutputToContain('Expired')
        ->expectsOutputToContain('Synced 0 Paymob plans')
        ->expectsOutputToContain('Paymob subscriptions')
        ->assertExitCode(0);
});

it('syncs subscriptions with relations when --with-relations flag is used', function () {
    $this->artisan('paymob:sync-subscriptions', ['--with-relations' => true])
        ->expectsOutputToContain('Expired')
        ->expectsOutputToContain('Paymob subscriptions')
        ->assertExitCode(0);
});
