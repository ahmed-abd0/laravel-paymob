<?php

namespace Paymob\Laravel\Support;

use Paymob\Laravel\Exceptions\ConfigurationException;

final class SubscriptionWebhookUrl
{
    public function resolve(?string $url = null): string
    {
        $url = $url ?: config('paymob.webhooks.subscription_url');

        if (! $url) {
            if (! app('router')->has('paymob.webhooks.subscription')) {
                throw new ConfigurationException(
                    'The Paymob subscription webhook route is not available. Pass --url or configure PAYMOB_SUBSCRIPTION_WEBHOOK_URL.'
                );
            }

            $secret = config('paymob.webhooks.subscription_secret');

            if (! $secret && config('paymob.webhooks.require_subscription_secret', true)) {
                throw new ConfigurationException(
                    'PAYMOB_SUBSCRIPTION_WEBHOOK_SECRET is required before registering the package subscription webhook route.'
                );
            }

            $url = route(
                'paymob.webhooks.subscription',
                $secret ? ['secret' => $secret] : []
            );
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
            throw new ConfigurationException(
                'The Paymob subscription webhook URL must be an absolute HTTP or HTTPS URL.'
            );
        }

        return $url;
    }

    public function display(string $url): string
    {
        return preg_replace('/([?&]secret=)[^&]+/i', '$1***', $url) ?: $url;
    }
}
