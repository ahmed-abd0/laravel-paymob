<?php

namespace Paymob\Laravel\Data;

use Illuminate\Contracts\Support\Arrayable;
use Paymob\Laravel\Enums\PlanFrequency;
use Paymob\Laravel\Enums\PlanType;
use InvalidArgumentException;

final readonly class SubscriptionPlanData implements Arrayable
{
    public function __construct(
        public string $name,
        public int $amountCents,
        public int $integration,
        public PlanFrequency|int $frequency = PlanFrequency::MONTHLY,
        public PlanType|string $planType = PlanType::RENT,
        public int $reminderDays = 2,
        public int $retrialDays = 2,
        public ?int $numberOfDeductions = null,
        public bool $useTransactionAmount = false,
        public bool $active = true,
        public ?string $webhookUrl = null
    ) {}

    public function toArray(): array
    {
        $frequency = $this->frequency instanceof PlanFrequency ? $this->frequency->value : $this->frequency;
        if (!in_array($frequency, array_column(PlanFrequency::cases(), 'value'), true)) throw new InvalidArgumentException('Unsupported Paymob subscription frequency.');
        if ($this->amountCents <= 0 || $this->integration <= 0) throw new InvalidArgumentException('Plan amount and MOTO integration ID must be positive.');
        return array_filter([
            'frequency' => $frequency,
            'name' => $this->name,
            'reminder_days' => $this->reminderDays,
            'retrial_days' => $this->retrialDays,
            'plan_type' => $this->planType instanceof PlanType ? $this->planType->value : $this->planType,
            'number_of_deductions' => $this->numberOfDeductions,
            'amount_cents' => $this->amountCents,
            'use_transaction_amount' => $this->useTransactionAmount,
            'is_active' => $this->active,
            'integration' => $this->integration,
            'webhook_url' => $this->webhookUrl
        ], fn($value) => $value !== null);
    }
}
