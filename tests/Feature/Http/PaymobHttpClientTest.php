<?php

use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Exceptions\ApiException;
use Paymob\Laravel\Exceptions\ConfigurationException;
use Paymob\Laravel\Http\PaymobHttpClient;

beforeEach(function () {
    $this->setUpConfig();
    Http::preventStrayRequests();
});

it('sends secret-key requests with Token authorization', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    app(PaymobHttpClient::class)->secret('POST', '/v1/intention/', ['amount' => 1000]);
    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Token sk_test_fake_secret_key')
            && $request->data()['amount'] === 1000;
    });
});

it('sends bearer requests with Bearer authorization', function () {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'my_bearer'], 200),
        '*' => Http::response(['ok' => true], 200),
    ]);
    app(PaymobHttpClient::class)->bearer('GET', '/api/test');
    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer my_bearer');
    });
});

it('refreshes token and retries after 401 on bearer request', function () {
    $callCount = 0;
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok'], 200),
        '*' => function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return Http::response(['message' => 'Unauthorized'], 401);
            }

            return Http::response(['ok' => true], 200);
        },
    ]);
    $response = app(PaymobHttpClient::class)->bearer('GET', '/api/test');
    expect($response->successful())->toBeTrue();
});

it('throws ApiException for failed responses', function () {
    Http::fake(['*' => Http::response(['message' => 'Bad request'], 400)]);
    app(PaymobHttpClient::class)->secret('POST', '/v1/test');
})->throws(ApiException::class);

it('throws ConfigurationException when secret key is missing', function () {
    config()->set('paymob.keys.secret', null);
    app(PaymobHttpClient::class)->secret('POST', '/v1/test');
})->throws(ConfigurationException::class);

it('sends JSON content type by default', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    app(PaymobHttpClient::class)->secret('POST', '/v1/test', ['data' => 1]);
    Http::assertSent(function ($request) {
        return $request->header('Content-Type')[0] === 'application/json';
    });
});

it('sends form data when form option is true', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    app(PaymobHttpClient::class)->secret('POST', '/api/test', ['field' => 'value'], ['form' => true]);
    Http::assertSent(function ($request) {
        return str_contains($request->header('Content-Type')[0], 'application/x-www-form-urlencoded');
    });
});

it('sends query parameters for GET requests', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    app(PaymobHttpClient::class)->secret('GET', '/api/test', ['page' => 2]);
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'page=2');
    });
});

it('uses correct regional base URL', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    app(PaymobHttpClient::class)->secret('POST', '/v1/test');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'https://accept.paymob.com'));
});

it('uses configured base URL override', function () {
    config()->set('paymob.base_url', 'https://custom.test');
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    app(PaymobHttpClient::class)->secret('POST', '/v1/test');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'https://custom.test'));
});

it('does not retry non-GET methods automatically', function () {
    $callCount = 0;
    Http::fake(['*' => function () use (&$callCount) {
        $callCount++;

        return Http::response(['ok' => true], 200);
    }]);
    app(PaymobHttpClient::class)->secret('POST', '/v1/test', ['amount' => 100]);
    expect($callCount)->toBe(1);
});

it('includes User-Agent header', function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
    app(PaymobHttpClient::class)->secret('POST', '/v1/test');
    Http::assertSent(fn ($request) => $request->hasHeader('User-Agent', 'paymob-community/laravel-paymob'));
});

it('returns PaymobResponse with status and data', function () {
    Http::fake(['*' => Http::response(['id' => 123], 200)]);
    $response = app(PaymobHttpClient::class)->secret('POST', '/v1/test');
    expect($response->status)->toBe(200);
    expect($response['id'])->toBe(123);
});

it('returns PaymobResponse even for empty 200 response', function () {
    Http::fake(['*' => Http::response([], 200)]);
    $response = app(PaymobHttpClient::class)->secret('POST', '/v1/test');
    expect($response->status)->toBe(200);
    expect($response->toArray())->toBe([]);
});
