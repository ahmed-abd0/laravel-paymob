<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Paymob\Laravel\Models\Subscription;

beforeEach(function () {
    $this->artisan('migrate')->run();

    Cache::flush();

    config()->set('app.url', 'https://billing.example.com');
    config()->set('paymob.base_url', 'https://paymob.test');
    config()->set('paymob.keys.api', 'test-api-key');
    config()->set('paymob.webhooks.subscription_secret', 'test-secret');
    config()->set('paymob.webhooks.require_subscription_secret', true);
});

it('registers the package webhook route for one subscription', function () {
    $subscription = createWebhookSubscription([
        'paymob_id' => 'sub-100',
    ]);

    Http::fake([
        'https://paymob.test/api/auth/tokens' => Http::response([
            'token' => 'bearer-token',
        ]),
        'https://paymob.test/api/acceptance/subscriptions/sub-100/register_webhook' => Http::response([
            'id' => 'sub-100',
            'state' => 'active',
        ]),
    ]);

    $this->artisan('paymob:webhook', [
        'subscription' => 'local:'.$subscription->id,
        '--force' => true,
    ])->assertSuccessful();

    $expectedUrl = route('paymob.webhooks.subscription', [
        'secret' => 'test-secret',
    ]);

    Http::assertSent(
        fn (Request $request) => $request->url() ===
            'https://paymob.test/api/acceptance/subscriptions/sub-100/register_webhook'
            && $request->data() === ['url' => $expectedUrl]
    );

    expect($subscription->refresh()->payload['webhook_url'])
        ->toBe($expectedUrl);
});

it('registers a custom webhook from the subscription model', function () {
    $subscription = createWebhookSubscription([
        'paymob_id' => 'sub-100',
    ]);

    Http::fake([
        'https://paymob.test/api/auth/tokens' => Http::response([
            'token' => 'bearer-token',
        ]),
        'https://paymob.test/api/acceptance/subscriptions/sub-100/register_webhook' => Http::response([
            'id' => 'sub-100',
            'state' => 'active',
        ]),
    ]);

    $subscription->registerWebhook(
        'https://app.test/custom-subscription-webhook'
    );

    expect($subscription->payload['webhook_url'])
        ->toBe('https://app.test/custom-subscription-webhook');
});

it('registers all subscriptions and continues after an individual failure', function () {
    createWebhookSubscription(['paymob_id' => 'sub-100']);
    createWebhookSubscription(['paymob_id' => 'sub-200']);

    Http::fake(function (Request $request) {
        if ($request->url() === 'https://paymob.test/api/auth/tokens') {
            return Http::response(['token' => 'bearer-token']);
        }

        if (str_contains($request->url(), 'sub-100/register_webhook')) {
            return Http::response([
                'id' => 'sub-100',
                'state' => 'active',
            ]);
        }

        return Http::response([
            'detail' => 'Webhook registration failed.',
        ], 422);
    });

    $this->artisan('paymob:webhook', [
        '--force' => true,
        '--chunk' => 1,
    ])->assertFailed();

    $requests = collect(Http::recorded())
        ->map(fn (array $entry) => $entry[0])
        ->filter(
            fn (Request $request) => str_contains(
                $request->url(),
                'register_webhook'
            )
        );

    expect($requests)->toHaveCount(2);
});

it('supports a dry run without calling paymob', function () {
    createWebhookSubscription(['paymob_id' => 'sub-100']);

    Http::fake();

    $this->artisan('paymob:webhook', [
        '--dry-run' => true,
    ])->assertSuccessful();

    Http::assertNothingSent();
});

function createWebhookSubscription(array $attributes = []): Subscription
{
    return Subscription::query()->create($attributes + [
        'remote_plan_id' => 'plan-100',
        'reference' => (string) Str::uuid(),
        'status' => 'incomplete',
        'amount_cents' => 10000,
        'currency' => 'EGP',
    ]);
}
