<?php

namespace Paymob\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Paymob\Laravel\Billing\SubscriptionManager;
use Paymob\Laravel\Enums\SubscriptionStatus;

class Subscription extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'status' => SubscriptionStatus::class,
            'amount_cents' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'next_billing_at' => 'date',
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'canceled_at' => 'datetime',
            'activated_at' => 'datetime',
            'synced_at' => 'datetime',
            'metadata' => 'array',
            'payload' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('paymob.tables.subscriptions', parent::getTable());
    }
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
    public function plan(): BelongsTo
    {
        return $this->belongsTo(config('paymob.models.plan'), 'paymob_plan_id');
    }
    public function transactions(): HasMany
    {
        return $this->hasMany(config('paymob.models.transaction'));
    }
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(config('paymob.models.payment_method'));
    }

    public function active(): bool
    {
        return $this->status === SubscriptionStatus::ACTIVE && (! $this->ends_at || $this->ends_at->copy()->endOfDay()->isFuture());
    }
    public function incomplete(): bool
    {
        return in_array($this->status, [SubscriptionStatus::INCOMPLETE, SubscriptionStatus::INCOMPLETE_EXPIRED], true);
    }
    public function canceled(): bool
    {
        return $this->status === SubscriptionStatus::CANCELED;
    }
    public function suspended(): bool
    {
        return $this->status === SubscriptionStatus::SUSPENDED;
    }
    public function pastDue(): bool
    {
        return $this->status === SubscriptionStatus::PAST_DUE;
    }
    public function onTrial(): bool
    {
        return $this->trial_ends_at?->isFuture() ?? false;
    }
    public function onGracePeriod(): bool
    {
        return in_array($this->status, [SubscriptionStatus::CANCELED, SubscriptionStatus::SUSPENDED], true) && ($this->next_billing_at?->copy()->endOfDay()->isFuture() ?? false);
    }
    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    public function suspend(): static
    {
        app(SubscriptionManager::class)->suspend($this);

        return $this->refresh();
    }
    public function resume(?string $nextBilling = null): static
    {
        app(SubscriptionManager::class)->resume($this, $nextBilling);

        return $this->refresh();
    }

    public function registerWebhook(?string $url = null): static
    {
        app(SubscriptionManager::class)->registerWebhook($this, $url);

        return $this->refresh();
    }

    public function cancel(): static
    {
        app(SubscriptionManager::class)->cancel($this);

        return $this->refresh();
    }
    public function sync(bool $withRelations = false): static
    {
        app(SubscriptionManager::class)->sync($this, $withRelations);

        return $this->refresh();
    }
    public function syncTransactions(): int
    {
        return app(SubscriptionManager::class)->syncTransactions($this);
    }
    public function syncPaymentMethods(): int
    {
        return app(SubscriptionManager::class)->syncPaymentMethods($this);
    }
    public function updateBilling(array $data): static
    {
        app(SubscriptionManager::class)->update($this, $data);

        return $this->refresh();
    }
}
