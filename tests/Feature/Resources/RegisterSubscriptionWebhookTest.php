<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Paymob\Laravel\Facades\Paymob;

beforeEach(function () {
    Cache::flush();

    config()->set('paymob.base_url', 'https://paymob.test');
    config()->set('paymob.keys.api', 'test-api-key');
});

it('registers a webhook for the requested remote subscription', function () {
    Http::fake([
        'https://paymob.test/api/auth/tokens' => Http::response([
            'token' => 'bearer-token',
        ]),
        'https://paymob.test/api/acceptance/subscriptions/sub-100/register_webhook' => Http::response([
            'id' => 100,
            'webhook_url' => 'https://app.test/paymob/webhooks/subscription',
        ]),
    ]);

    $response = Paymob::subscriptions()->registerWebhook(
        'sub-100',
        'https://app.test/paymob/webhooks/subscription'
    );

    expect($response->successful())->toBeTrue()
        ->and($response->get('webhook_url'))
        ->toBe('https://app.test/paymob/webhooks/subscription');

    Http::assertSent(
        fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://paymob.test/api/acceptance/subscriptions/sub-100/register_webhook'
            && $request->hasHeader('Authorization', 'Bearer bearer-token')
            && $request->data() === [
                'url' => 'https://app.test/paymob/webhooks/subscription',
            ]
    );
});
