<?php

namespace Paymob\Laravel\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Paymob\Laravel\Billing\SubscriptionBuilder;
use Paymob\Laravel\Models\Plan;
use Paymob\Laravel\Models\Subscription;

trait Billable
{
    public function paymobSubscriptions(): MorphMany
    {
        return $this->morphMany(config('paymob.models.subscription'), 'billable');
    }

    public function paymobTransactions(): MorphMany
    {
        return $this->morphMany(config('paymob.models.transaction'), 'billable');
    }

    public function paymobPaymentMethods(): MorphMany
    {
        return $this->morphMany(config('paymob.models.payment_method'), 'billable');
    }

    public function subscription(string $name = 'default'): ?Subscription
    {
        return $this->paymobSubscriptions()->where('name', $name)->latest('id')->first();
    }

    public function subscribed(string $name = 'default'): bool
    {
        return $this->subscription($name)?->valid() ?? false;
    }

    public function newSubscription(string $name, Plan|int|string $plan): SubscriptionBuilder
    {
        return app(SubscriptionBuilder::class)->for($this, $name, $plan);
    }

    public function paymobBillingData(): array
    {
        $name = trim((string) ($this->name ?? 'Customer'));
        $parts = preg_split('/\s+/', $name, 2);
        return [
            'first_name' => $this->first_name ?? $parts[0] ?? 'Customer',
            'last_name' => $this->last_name ?? $parts[1] ?? 'Customer',
            'email' => (string) ($this->email ?? ''),
            'phone_number' => (string) ($this->phone ?? $this->phone_number ?? ''),
            'country' => 'EG'
        ];
    }
}
