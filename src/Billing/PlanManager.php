<?php

namespace Paymob\Laravel\Billing;

use Illuminate\Support\Arr;
use Paymob\Laravel\Data\SubscriptionPlanData;
use Paymob\Laravel\Models\Plan;
use Paymob\Laravel\Resources\SubscriptionPlans;

final class PlanManager
{
    public function __construct(private readonly SubscriptionPlans $api) {}

    public function create(SubscriptionPlanData|array $data): Plan
    {
        $payload = $data instanceof SubscriptionPlanData ? $data->toArray() : $data;
        $payload['webhook_url'] ??= $this->defaultWebhookUrl();
        $response = $this->api->create($payload)->toArray();
        return $this->persist($response + $payload);
    }

    public function update(Plan $plan, array $data): Plan
    {
        $response = $this->api->update($plan->paymob_id, $data)->toArray();
        return $this->persist($response + $data, $plan);
    }

    public function suspend(Plan $plan): Plan
    {
        $response = $this->api->suspend($plan->paymob_id)->toArray();
        return $this->persist($response + ['is_active' => false], $plan);
    }

    public function resume(Plan $plan): Plan
    {
        $response = $this->api->resume($plan->paymob_id)->toArray();
        return $this->persist($response + ['is_active' => true], $plan);
    }

    public function sync(Plan $plan): Plan
    {
        $data = $this->api->all()->toArray();
        $plans = Arr::isList($data) ? $data : ($data['results'] ?? $data['data'] ?? []);
        $remote = collect($plans)->first(fn(array $item) => (string) ($item['id'] ?? '') === (string) $plan->paymob_id);
        return $remote ? $this->persist($remote, $plan) : $plan;
    }

    public function syncAll(): int
    {
        $data = $this->api->all()->toArray();
        $plans = Arr::isList($data) ? $data : ($data['results'] ?? $data['data'] ?? []);
        foreach ($plans as $plan) $this->persist($plan);
        return count($plans);
    }

    private function persist(array $data, ?Plan $plan = null): Plan
    {
        $model = $plan ?? new (config('paymob.models.plan'));
        $model->fill([
            'paymob_id' => (string) ($data['id'] ?? $model->paymob_id),
            'name' => $data['name'] ?? $model->name,
            'frequency' => (int) ($data['frequency'] ?? $model->frequency ?? 30),
            'plan_type' => $data['plan_type'] ?? $model->plan_type ?? 'rent',
            'amount_cents' => (int) ($data['amount_cents'] ?? $model->amount_cents ?? 0),
            'currency' => $data['currency'] ?? $model->currency ?? config('paymob.currency', 'EGP'),
            'integration_id' => (int) ($data['integration'] ?? $data['integration_id'] ?? $model->integration_id ?? 0),
            'reminder_days' => (int) ($data['reminder_days'] ?? $model->reminder_days ?? 2),
            'retrial_days' => (int) ($data['retrial_days'] ?? $model->retrial_days ?? 2),
            'number_of_deductions' => $data['number_of_deductions'] ?? $model->number_of_deductions,
            'use_transaction_amount' => (bool) ($data['use_transaction_amount'] ?? $model->use_transaction_amount ?? false),
            'active' => (bool) ($data['is_active'] ?? $data['active'] ?? $model->active ?? true),
            'webhook_url' => $data['webhook_url'] ?? $model->webhook_url,
            'payload' => $data
        ])->save();
        return $model;
    }

    private function defaultWebhookUrl(): ?string
    {
        if (!app('router')->has('paymob.webhooks.subscription')) return null;
        $secret = config('paymob.webhooks.subscription_secret');
        return route('paymob.webhooks.subscription', $secret ? ['secret' => $secret] : []);
    }
}
