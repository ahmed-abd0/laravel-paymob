<?php

namespace Paymob\Laravel\Http;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Paymob\Laravel\Enums\Region;
use Paymob\Laravel\Exceptions\ApiException;
use Paymob\Laravel\Exceptions\ConfigurationException;
use Paymob\Laravel\Support\PaymobResponse;

final class PaymobHttpClient
{
    public function __construct(private readonly Factory $http, private readonly TokenManager $tokens) {}

    public function secret(string $method, string $path, array $data = [], array $options = []): PaymobResponse
    {
        $secret = config('paymob.keys.secret');
        if (! $secret) {
            throw new ConfigurationException('PAYMOB_SECRET_KEY is not configured.');
        }

        return $this->send($method, $path, $data, $options, fn (PendingRequest $request) => $request->withToken($secret, 'Token'));
    }

    public function bearer(string $method, string $path, array $data = [], array $options = []): PaymobResponse
    {
        try {
            return $this->send($method, $path, $data, $options, fn (PendingRequest $request) => $request->withToken($this->tokens->token()));
        } catch (ApiException $e) {
            if ($e->status !== 401) {
                throw $e;
            }

            return $this->send($method, $path, $data, $options, fn (PendingRequest $request) => $request->withToken($this->tokens->token(true)));
        }
    }

    public function public(string $method, string $path, array $data = [], array $options = []): PaymobResponse
    {
        return $this->send($method, $path, $data, $options);
    }

    private function send(string $method, string $path, array $data, array $options, ?callable $authenticate = null): PaymobResponse
    {
        $method = strtoupper($method);
        $request = $this->http->acceptJson()->withUserAgent('paymob-community/laravel-paymob')
            ->timeout(config('paymob.http.timeout', 30))->connectTimeout(config('paymob.http.connect_timeout', 10));
        if (in_array($method, config('paymob.http.retry_methods', ['GET']), true)) {
            $request = $request->retry(config('paymob.http.retries', 2), config('paymob.http.retry_sleep_ms', 300), throw: false);
        }
        if (! isset($options['multipart'])) {
            $request = $request->asJson();
        }
        if ($authenticate) {
            $request = $authenticate($request);
        }
        if (! str_starts_with($path, 'http')) {
            $request = $request->baseUrl($this->baseUrl());
        }
        if ($headers = ($options['headers'] ?? null)) {
            $request = $request->withHeaders($headers);
        }
        if (($options['form'] ?? false) === true) {
            $request = $request->asForm();
        }
        $sendOptions = [];
        if ($multipart = ($options['multipart'] ?? null)) {
            $sendOptions['multipart'] = $multipart;
        } elseif (strtoupper($method) === 'GET') {
            $sendOptions['query'] = $data;
        } else {
            $sendOptions['json'] = $data;
        }
        $response = $request->send($method, $path, $sendOptions);
        if ($response->failed()) {
            throw ApiException::fromResponse($response);
        }

        return new PaymobResponse($response->status(), $response->json() ?? [], $response->headers());
    }

    public function baseUrl(): string
    {
        if ($url = config('paymob.base_url')) {
            return rtrim($url, '/');
        }

        return Region::from(config('paymob.region', Region::EGYPT->value))->baseUrl();
    }
}
