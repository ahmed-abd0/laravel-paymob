<?php

namespace Paymob\Laravel\Resources;

use Paymob\Laravel\Data\SubscriptionPlanData;
use Paymob\Laravel\Http\PaymobHttpClient;
use Paymob\Laravel\Support\PaymobResponse;

final class SubscriptionPlans
{
    public function __construct(private readonly PaymobHttpClient $http) {}

    public function create(SubscriptionPlanData|array $data): PaymobResponse
    {
        return $this->http->bearer('POST', '/api/acceptance/subscription-plans', $data instanceof SubscriptionPlanData ? $data->toArray() : $data);
    }

    public function all(array $query = []): PaymobResponse
    {
        return $this->http->bearer('GET', '/api/acceptance/subscription-plans', $query);
    }
    public function update(int|string $planId, array $data): PaymobResponse
    {
        return $this->http->bearer('PUT', "/api/acceptance/subscription-plans/{$planId}", $data);
    }
    public function suspend(int|string $planId): PaymobResponse
    {
        return $this->http->bearer('POST', "/api/acceptance/subscription-plans/{$planId}/suspend");
    }
    public function resume(int|string $planId): PaymobResponse
    {
        return $this->http->bearer('POST', "/api/acceptance/subscription-plans/{$planId}/resume");
    }
}
