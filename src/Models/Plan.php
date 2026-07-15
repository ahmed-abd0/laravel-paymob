<?php

namespace Paymob\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Paymob\Laravel\Billing\PlanManager;
use Paymob\Laravel\Enums\PlanType;

class Plan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'frequency' => 'integer',
            'amount_cents' => 'integer',
            'integration_id' => 'integer',
            'reminder_days' => 'integer',
            'retrial_days' => 'integer',
            'number_of_deductions' => 'integer',
            'use_transaction_amount' => 'boolean',
            'active' => 'boolean',
            'plan_type' => PlanType::class,
            'payload' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('paymob.tables.plans', parent::getTable());
    }
    public function subscriptions(): HasMany
    {
        return $this->hasMany(config('paymob.models.subscription'), 'paymob_plan_id');
    }
    public function suspend(): static
    {
        app(PlanManager::class)->suspend($this);

        return $this->refresh();
    }
    public function resume(): static
    {
        app(PlanManager::class)->resume($this);

        return $this->refresh();
    }
    public function sync(): static
    {
        app(PlanManager::class)->sync($this);

        return $this->refresh();
    }
}
