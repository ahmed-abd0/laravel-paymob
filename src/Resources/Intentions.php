<?php

namespace Paymob\Laravel\Resources;

use Paymob\Laravel\Data\IntentionData;
use Paymob\Laravel\Exceptions\ConfigurationException;
use Paymob\Laravel\Http\PaymobHttpClient;
use Paymob\Laravel\Support\Checkout;
use Paymob\Laravel\Support\PaymobResponse;

final class Intentions
{
    public function __construct(private readonly PaymobHttpClient $http) {}

    public function create(IntentionData|array $data): PaymobResponse
    {
        return $this->http->secret('POST', '/v1/intention/', $data instanceof IntentionData ? $data->toArray() : $data);
    }

    public function update(string $clientSecret, array $data): PaymobResponse
    {
        return $this->http->secret('PUT', '/v1/intention/'.urlencode($clientSecret), $data);
    }

    public function retrieve(string $clientSecret, ?string $publicKey = null): PaymobResponse
    {
        $publicKey ??= config('paymob.keys.public');
        if (! $publicKey) {
            throw new ConfigurationException('PAYMOB_PUBLIC_KEY is not configured.');
        }

        return $this->http->public('GET', '/v1/intention/element/'.urlencode($publicKey).'/'.urlencode($clientSecret).'/');
    }

    public function checkout(string $clientSecret, ?string $publicKey = null): Checkout
    {
        return new Checkout($this->checkoutUrl($clientSecret, $publicKey), $clientSecret, $this->retrieve($clientSecret, $publicKey));
    }

    public function checkoutUrl(string $clientSecret, ?string $publicKey = null): string
    {
        $publicKey ??= config('paymob.keys.public');
        if (! $publicKey) {
            throw new ConfigurationException('PAYMOB_PUBLIC_KEY is not configured.');
        }

        return $this->http->baseUrl().'/unifiedcheckout/?'.http_build_query(['publicKey' => $publicKey, 'clientSecret' => $clientSecret]);
    }
}
