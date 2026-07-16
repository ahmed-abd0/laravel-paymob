<?php

use Paymob\Laravel\Exceptions\ConfigurationException;
use Paymob\Laravel\Support\SubscriptionWebhookUrl;

it('builds the package subscription route with its configured secret', function () {
    config()->set('app.url', 'https://billing.example.com');
    config()->set('paymob.webhooks.subscription_secret', 'test-secret');
    config()->set('paymob.webhooks.require_subscription_secret', true);

    $url = app(SubscriptionWebhookUrl::class)->resolve();

    expect($url)
        ->toContain('/paymob/webhooks/subscription')
        ->toContain('secret=test-secret');
});

it('requires a secret when the package route requires subscription authentication', function () {
    config()->set('paymob.webhooks.subscription_secret', null);
    config()->set('paymob.webhooks.subscription_url', null);
    config()->set('paymob.webhooks.require_subscription_secret', true);

    app(SubscriptionWebhookUrl::class)->resolve();
})->throws(
    ConfigurationException::class,
    'PAYMOB_SUBSCRIPTION_WEBHOOK_SECRET'
);

it('rejects a relative or non-http webhook url', function () {
    app(SubscriptionWebhookUrl::class)
        ->resolve('/paymob/webhooks/subscription');
})->throws(
    ConfigurationException::class,
    'absolute HTTP or HTTPS URL'
);

it('redacts the subscription secret when displaying a webhook url', function () {
    $url = app(SubscriptionWebhookUrl::class)->display(
        'https://app.test/webhook?secret=sensitive&tenant=1'
    );

    expect($url)->toBe(
        'https://app.test/webhook?secret=***&tenant=1'
    );
});
