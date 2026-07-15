<?php

use Paymob\Laravel\Enums\Currency;
use Paymob\Laravel\Enums\Region;
use Paymob\Laravel\Models\PaymentMethod;
use Paymob\Laravel\Models\Plan;
use Paymob\Laravel\Models\Subscription;
use Paymob\Laravel\Models\Transaction;
use Paymob\Laravel\Models\WebhookCall;

return [
    'region' => env('PAYMOB_REGION', Region::EGYPT->value),
    'base_url' => env('PAYMOB_BASE_URL'),
    'moto_base_url' => env('PAYMOB_MOTO_BASE_URL', 'https://accept.paymobsolutions.com'),
    'currency' => env('PAYMOB_CURRENCY', Currency::EGP->value),
    'keys' => [
        'secret' => env('PAYMOB_SECRET_KEY'),
        'public' => env('PAYMOB_PUBLIC_KEY'),
        'api' => env('PAYMOB_API_KEY'),
        'hmac' => env('PAYMOB_HMAC_SECRET'),
    ],
    'integrations' => [
        'default' => env('PAYMOB_INTEGRATION_ID'),
        'card_3ds' => env('PAYMOB_CARD_3DS_INTEGRATION_ID'),
        'moto' => env('PAYMOB_MOTO_INTEGRATION_ID'),
        'verification_3ds' => env('PAYMOB_VERIFICATION_3DS_INTEGRATION_ID'),
    ],
    'checkout' => [
        'notification_url' => env('PAYMOB_NOTIFICATION_URL'),
        'redirection_url' => env('PAYMOB_REDIRECTION_URL'),
        'expiration' => (int) env('PAYMOB_INTENTION_EXPIRATION', 3600),
    ],
    'http' => [
        'timeout' => (int) env('PAYMOB_HTTP_TIMEOUT', 30),
        'connect_timeout' => (int) env('PAYMOB_HTTP_CONNECT_TIMEOUT', 10),
        'retries' => (int) env('PAYMOB_HTTP_RETRIES', 2),
        'retry_sleep_ms' => (int) env('PAYMOB_HTTP_RETRY_SLEEP_MS', 300),
        'retry_methods' => ['GET'],
        'token_cache_seconds' => (int) env('PAYMOB_TOKEN_CACHE_SECONDS', 3300),
    ],
    'webhooks' => [
        'enabled' => env('PAYMOB_WEBHOOKS_ENABLED', true),
        'prefix' => env('PAYMOB_WEBHOOK_PREFIX', 'paymob/webhooks'),
        'middleware' => ['api'],
        'verify_transaction_hmac' => env('PAYMOB_VERIFY_HMAC', true),
        'subscription_secret' => env('PAYMOB_SUBSCRIPTION_WEBHOOK_SECRET'),
        'require_subscription_secret' => env('PAYMOB_REQUIRE_SUBSCRIPTION_WEBHOOK_SECRET', true),
        'dispatch_after_commit' => true,
        'retain_days' => (int) env('PAYMOB_WEBHOOK_RETAIN_DAYS', 90),
    ],
    'models' => [
        'plan' => Plan::class,
        'subscription' => Subscription::class,
        'transaction' => Transaction::class,
        'payment_method' => PaymentMethod::class,
        'webhook_call' => WebhookCall::class,
    ],
    'tables' => [
        'plans' => 'paymob_plans',
        'subscriptions' => 'paymob_subscriptions',
        'transactions' => 'paymob_transactions',
        'payment_methods' => 'paymob_payment_methods',
        'webhook_calls' => 'paymob_webhook_calls',
    ],
];
