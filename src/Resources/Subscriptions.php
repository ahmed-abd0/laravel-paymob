<?php

namespace Paymob\Laravel\Resources;

use Paymob\Laravel\Data\IntentionData;
use Paymob\Laravel\Http\PaymobHttpClient;
use Paymob\Laravel\Support\PaymobResponse;

final class Subscriptions
{
    public function __construct(private readonly PaymobHttpClient $http, private readonly Intentions $intentions) {}

    public function create(IntentionData|array $data): PaymobResponse
    {
        return $this->intentions->create($data);
    }
    public function find(int|string $subscriptionId, array $query = []): PaymobResponse
    {
        return $this->http->bearer('GET', "/api/acceptance/subscriptions/{$subscriptionId}", $query);
    }
    public function update(int|string $subscriptionId, array $data): PaymobResponse
    {
        return $this->http->bearer('PUT', "/api/acceptance/subscriptions/{$subscriptionId}", $data);
    }
    public function suspend(int|string $subscriptionId): PaymobResponse
    {
        return $this->http->bearer('POST', "/api/acceptance/subscriptions/{$subscriptionId}/suspend");
    }
    public function resume(int|string $subscriptionId): PaymobResponse
    {
        return $this->http->bearer('POST', "/api/acceptance/subscriptions/{$subscriptionId}/resume");
    }
    public function cancel(int|string $subscriptionId): PaymobResponse
    {
        return $this->http->bearer('POST', "/api/acceptance/subscriptions/{$subscriptionId}/cancel");
    }
    public function lastTransaction(int|string $subscriptionId): PaymobResponse
    {
        return $this->http->bearer('GET', "/api/acceptance/subscriptions/{$subscriptionId}/last-transaction");
    }
    public function transactions(int|string $subscriptionId, array $query = []): PaymobResponse
    {
        return $this->http->bearer('GET', "/api/acceptance/subscriptions/{$subscriptionId}/transactions", $query);
    }
    public function cards(int|string $subscriptionId): PaymobResponse
    {
        return $this->http->bearer('GET', "/api/acceptance/subscriptions/{$subscriptionId}/card-tokens");
    }
    public function addCard(IntentionData|array $data): PaymobResponse
    {
        return $this->intentions->create($data);
    }
    public function deleteCard(int|string $subscriptionId, int|string $cardId): PaymobResponse
    {
        return $this->http->bearer('POST', "/api/acceptance/subscriptions/{$subscriptionId}/delete-card", ['card' => $cardId]);
    }
    public function changePrimaryCard(int|string $subscriptionId, int|string $cardId): PaymobResponse
    {
        return $this->http->bearer('POST', "/api/acceptance/subscriptions/{$subscriptionId}/change-primary-card", ['card' => $cardId]);
    }
    public function registerWebhook(int|string $planId, string $url): PaymobResponse
    {
        return $this->http->bearer('POST', "/api/acceptance/subscriptions/{$planId}/register_webhook", ['url' => $url]);
    }
}
