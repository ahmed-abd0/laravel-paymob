<?php

use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Exceptions\ApiException;
use Paymob\Laravel\Exceptions\ConfigurationException;
use Paymob\Laravel\Http\TokenManager;

beforeEach(function () {
    $this->setUpConfig();
    Http::preventStrayRequests();
});

it('authenticates and returns bearer token', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'fake_bearer_token'], 200),
    ]);
    $token = app(TokenManager::class)->token();
    expect($token)->toBe('fake_bearer_token');
    Http::assertSent(function ($request) {
        return $request->url() === 'https://accept.paymob.com/api/auth/tokens'
            && $request->method() === 'POST'
            && $request->data()['api_key'] === 'fake_api_key';
    });
});

it('caches the bearer token', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'cached_token'], 200),
    ]);
    $manager = app(TokenManager::class);
    $first = $manager->token();
    $second = $manager->token();
    expect($first)->toBe('cached_token');
    expect($second)->toBe('cached_token');
    Http::assertSentCount(1);
});

it('forces fresh token when requested', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'fresh_token'], 200),
    ]);
    $manager = app(TokenManager::class);
    $manager->token();
    $fresh = $manager->token(fresh: true);
    expect($fresh)->toBe('fresh_token');
    Http::assertSentCount(2);
});

it('throws ConfigurationException when API key is missing', function () {
    config()->set('paymob.keys.api', null);
    app(TokenManager::class)->token();
})->throws(ConfigurationException::class, 'PAYMOB_API_KEY is not configured.');

it('throws ApiException when Paymob returns an error', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['message' => 'Invalid'], 401),
    ]);
    app(TokenManager::class)->token();
})->throws(ApiException::class);

it('throws ApiException when response has no token', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['data' => null], 200),
    ]);
    app(TokenManager::class)->token();
})->throws(ApiException::class);

it('forgets the cached token', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'new_token'], 200),
    ]);
    $manager = app(TokenManager::class);
    $manager->token();
    $manager->forget();
    $manager->token();
    Http::assertSentCount(2);
});

it('uses regional base URL for Egypt by default', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
    ]);
    app(TokenManager::class)->token();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'https://accept.paymob.com'));
});

it('uses custom base URL when configured', function () {
    config()->set('paymob.base_url', 'https://custom.paymob.test');
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
    ]);
    app(TokenManager::class)->token();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'https://custom.paymob.test'));
});
