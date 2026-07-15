<?php

namespace Paymob\Laravel\Http;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Factory;
use Paymob\Laravel\Enums\Region;
use Paymob\Laravel\Exceptions\ApiException;
use Paymob\Laravel\Exceptions\ConfigurationException;

final class TokenManager
{
    public function __construct(private readonly Factory $http, private readonly Repository $cache) {}

    public function token(bool $fresh = false): string
    {
        $apiKey = config('paymob.keys.api');
        if (! $apiKey) {
            throw new ConfigurationException('PAYMOB_API_KEY is not configured.');
        }
        $key = 'paymob:token:'.config('paymob.region').':'.hash('sha256', $apiKey);
        if ($fresh) {
            $this->cache->forget($key);
        }

        return $this->cache->remember($key, config('paymob.http.token_cache_seconds', 3300), fn () => $this->requestToken($apiKey));
    }

    public function forget(): void
    {
        $apiKey = config('paymob.keys.api');
        if ($apiKey) {
            $this->cache->forget('paymob:token:'.config('paymob.region').':'.hash('sha256', $apiKey));
        }
    }

    private function requestToken(string $apiKey): string
    {
        $response = $this->http->baseUrl($this->baseUrl())->acceptJson()->asJson()
            ->timeout(config('paymob.http.timeout', 30))->connectTimeout(config('paymob.http.connect_timeout', 10))
            ->post('/api/auth/tokens', ['api_key' => $apiKey]);
        if ($response->failed()) {
            throw ApiException::fromResponse($response);
        }
        $token = $response->json('token');
        if (! $token) {
            throw new ApiException($response->status(), $response->json(), 'Paymob authentication response did not contain a token.');
        }

        return $token;
    }

    private function baseUrl(): string
    {
        if ($url = config('paymob.base_url')) {
            return rtrim($url, '/');
        }

        return Region::from(config('paymob.region', Region::EGYPT->value))->baseUrl();
    }
}
